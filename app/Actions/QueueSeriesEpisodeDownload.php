<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * @method static array{gid: string, existing: bool, preparing: bool} run(User $user, Series $series, SeriesInformation $dto, Episode $episode)
 */
final class QueueSeriesEpisodeDownload
{
    use AsAction;

    private const int DOWNLOAD_LOCK_TTL_SECONDS = 15;

    private const int DOWNLOAD_LOCK_WAIT_SECONDS = 3;

    private const int SAVE_ATTEMPTS = 5;

    /**
     * @return array{gid: string, existing: bool, preparing: bool}
     */
    public function __invoke(User $user, Series $series, SeriesInformation $dto, Episode $episode): array
    {
        try {
            return Cache::lock(
                MediaDownloadRef::lockKeyForSeriesEpisode($user, $series, $episode),
                self::DOWNLOAD_LOCK_TTL_SECONDS,
            )->block(self::DOWNLOAD_LOCK_WAIT_SECONDS, fn (): array => $this->queue($user, $series, $dto, $episode));
        } catch (LockTimeoutException) {
            $activeDownload = GetActiveDownloads::run($series, $episode, $user);

            if ($activeDownload === null) {
                return ['gid' => '', 'existing' => false, 'preparing' => true];
            }

            return ['gid' => $activeDownload->gid, 'existing' => true, 'preparing' => false];
        }
    }

    /**
     * @return array{gid: string, existing: bool, preparing: bool}
     */
    private function queue(User $user, Series $series, SeriesInformation $dto, Episode $episode): array
    {
        $activeDownload = GetActiveDownloads::run($series, $episode, $user);

        if ($activeDownload !== null) {
            return ['gid' => $activeDownload->gid, 'existing' => true, 'preparing' => false];
        }

        $gid = DownloadMedia::run(CreateXtreamcodesDownloadUrl::run($episode), ['out' => CreateDownloadOut::run($dto, $episode)]);
        $this->persistDownloadRef(MediaDownloadRef::fromSeriesAndEpisode($gid, $series, $episode, $user));

        return ['gid' => $gid, 'existing' => false, 'preparing' => false];
    }

    private function persistDownloadRef(MediaDownloadRef $downloadRef): void
    {
        $saved = DB::transaction(static fn (): bool => $downloadRef->save(), attempts: self::SAVE_ATTEMPTS);

        if (! $saved) {
            throw new RuntimeException('Failed to save download reference.');
        }
    }
}
