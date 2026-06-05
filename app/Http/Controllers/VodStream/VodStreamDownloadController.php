<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\CreateSignedDirectLink;
use App\Actions\QueueVodStreamDownload;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
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
        $queuedDownload = QueueVodStreamDownload::run($user, $model, $dto);

        if ($queuedDownload['preparing']) {
            return back()->withErrors([
                'download' => 'Download is already being prepared. Please try again.',
            ]);
        }

        return $this->downloadsRedirect($request, [
            'downloadable_id' => $model->stream_id,
            'gid' => $queuedDownload['gid'],
        ])->with('success', $queuedDownload['existing'] ? 'Download already in progress.' : 'Download started.');
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
