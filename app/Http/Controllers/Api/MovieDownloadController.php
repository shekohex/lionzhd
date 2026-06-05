<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateDownloadOut;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Actions\GetActiveDownloads;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class MovieDownloadController extends Controller
{
    private const int DOWNLOAD_LOCK_TTL_SECONDS = 15;

    private const int DOWNLOAD_LOCK_WAIT_SECONDS = 3;

    private const int SAVE_ATTEMPTS = 5;

    public function store(#[CurrentUser] User $user, XtreamCodesConnector $client, VodStream $movie): ApiActionResource
    {
        Gate::authorize('server-download');

        $dto = $client->send(new GetVodInfoRequest($movie->stream_id))->dtoOrFail();

        try {
            $queuedDownload = Cache::lock(
                MediaDownloadRef::lockKeyForVodStream($user, $movie),
                self::DOWNLOAD_LOCK_TTL_SECONDS,
            )->block(self::DOWNLOAD_LOCK_WAIT_SECONDS, fn (): array => $this->queueVodDownload($user, $movie, $dto));
        } catch (LockTimeoutException) {
            $activeDownload = GetActiveDownloads::run($movie, user: $user);

            if ($activeDownload === null) {
                abort(409, 'Download is already being prepared. Please try again.');
            }

            $queuedDownload = ['gid' => $activeDownload->gid, 'existing' => true];
        }

        return new ApiActionResource([
            'id' => "movie-download:{$movie->stream_id}",
            'type' => 'download-requests',
            'attributes' => $queuedDownload,
        ]);
    }

    /**
     * @return array{gid: string, existing: bool}
     */
    private function queueVodDownload(User $user, VodStream $movie, VodInformation $dto): array
    {
        $activeDownload = GetActiveDownloads::run($movie, user: $user);

        if ($activeDownload !== null) {
            return ['gid' => $activeDownload->gid, 'existing' => true];
        }

        $gid = DownloadMedia::run(CreateXtreamcodesDownloadUrl::run($dto), ['out' => CreateDownloadOut::run($dto)]);
        $this->persistDownloadRef(MediaDownloadRef::fromVodStream($gid, $movie, $user));

        return ['gid' => $gid, 'existing' => false];
    }

    private function persistDownloadRef(MediaDownloadRef $downloadRef): void
    {
        $saved = DB::transaction(static fn (): bool => $downloadRef->save(), attempts: self::SAVE_ATTEMPTS);

        if (! $saved) {
            throw new RuntimeException('Failed to save download reference.');
        }
    }
}
