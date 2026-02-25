<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\CreateDownloadOut;
use App\Actions\CreateSignedDirectLink;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Actions\GetActiveDownloads;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VodStreamDownloadController extends Controller
{
    /**
     * Trigger a download of the Video on demand stream.
     */
    public function create(#[CurrentUser] User $user, Request $request, XtreamCodesConnector $client, VodStream $model): RedirectResponse
    {

        $vod = $client->send(new GetVodInfoRequest($model->stream_id));
        $dto = $vod->dtoOrFail();
        // Check if the user has already downloaded this stream and the download is still active
        $firstActive = GetActiveDownloads::run($model, user: $user);
        if ($firstActive) {
            return $this->downloadsRedirect($request, [
                'downloadable_id' => $model->stream_id,
                'gid' => $firstActive->gid,
            ]);
        }

        $url = CreateXtreamcodesDownloadUrl::run($dto);
        $gid = DownloadMedia::run($url, ['out' => CreateDownloadOut::run($dto)]);

        MediaDownloadRef::fromVodStream($gid, $model, $user)->saveOrFail();

        return $this->downloadsRedirect($request, [
            'downloadable_id' => $model->stream_id,
            'gid' => $gid,
        ]);
    }

    /**
     * Create a direct download link for the movie.
     */
    public function direct(#[CurrentUser] User $user, XtreamCodesConnector $client, VodStream $model, Request $request): RedirectResponse|Response
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }

        $vod = $client->send(new GetVodInfoRequest($model->stream_id));
        $dto = $vod->dtoOrFail();

        $signedUrl = CreateSignedDirectLink::run($dto);

        return response()->view('direct-download.start', [
            'signedUrl' => $signedUrl,
        ]);
    }

    private function downloadsRedirect(Request $request, array $parameters = []): RedirectResponse
    {
        $returnTo = $request->query('return_to');

        if (is_string($returnTo) && preg_match('#^/downloads(?:[/?]|$)#', $returnTo) === 1) {
            return redirect()->to($returnTo);
        }

        return redirect()->route('downloads', $parameters);
    }
}
