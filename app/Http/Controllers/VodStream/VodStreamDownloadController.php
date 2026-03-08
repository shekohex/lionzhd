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
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class VodStreamDownloadController extends Controller
{
    private const int DOWNLOAD_LOCK_TTL_SECONDS = 15;

    private const int DOWNLOAD_LOCK_WAIT_SECONDS = 3;

    private const int SAVE_ATTEMPTS = 5;

    /**
     * Trigger a download of the Video on demand stream.
     */
    public function create(#[CurrentUser] User $user, Request $request, XtreamCodesConnector $client, VodStream $model): RedirectResponse
    {

        $vod = $client->send(new GetVodInfoRequest($model->stream_id));
        $dto = $vod->dtoOrFail();
        try {
            $queuedDownload = Cache::lock(
                MediaDownloadRef::lockKeyForVodStream($user, $model),
                self::DOWNLOAD_LOCK_TTL_SECONDS,
            )->block(self::DOWNLOAD_LOCK_WAIT_SECONDS, fn (): array => $this->queueVodDownload($user, $model, $dto));
        } catch (LockTimeoutException) {
            $activeDownload = GetActiveDownloads::run($model, user: $user);

            if ($activeDownload !== null) {
                return $this->downloadsRedirect($request, [
                    'downloadable_id' => $model->stream_id,
                    'gid' => $activeDownload->gid,
                ])->with('success', 'Download already in progress.');
            }

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

    private function queueVodDownload(User $user, VodStream $model, VodInformation $dto): array
    {
        $activeDownload = GetActiveDownloads::run($model, user: $user);

        if ($activeDownload !== null) {
            return [
                'gid' => $activeDownload->gid,
                'existing' => true,
            ];
        }

        $url = CreateXtreamcodesDownloadUrl::run($dto);
        $gid = DownloadMedia::run($url, ['out' => CreateDownloadOut::run($dto)]);

        $this->persistDownloadRef(MediaDownloadRef::fromVodStream($gid, $model, $user));

        return [
            'gid' => $gid,
            'existing' => false,
        ];
    }

    private function persistDownloadRef(MediaDownloadRef $downloadRef): void
    {
        $saved = DB::transaction(static fn (): bool => $downloadRef->save(), attempts: self::SAVE_ATTEMPTS);

        if (! $saved) {
            throw new RuntimeException('Failed to save download reference.');
        }
    }
}
