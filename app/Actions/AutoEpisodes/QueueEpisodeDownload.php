<?php

declare(strict_types=1);

namespace App\Actions\AutoEpisodes;

use App\Actions\CreateDownloadOut;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final readonly class QueueEpisodeDownload
{
    use AsAction;

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_DUPLICATE = 'duplicate';

    public const string STATUS_ERROR = 'error';

    private const int UNSIGNED_INT32_MAX = 4294967295;

    public function handle(SeriesMonitor $monitor, Episode $episode, SeriesInformation $seriesInfo): array
    {
        $downloadableId = $this->normalizeEpisodeId($episode->id);

        if ($downloadableId === null) {
            return [
                'status' => self::STATUS_ERROR,
                'reason' => 'unsafe_episode_id',
                'message' => sprintf('Unsafe Xtream episode id [%s].', $episode->id),
                'episode_id' => $episode->id,
            ];
        }

        $lockKey = self::lockKey($monitor->user_id, $monitor->series_id, $downloadableId);

        try {
            return Cache::lock($lockKey, 120)->block(5, function () use ($monitor, $episode, $seriesInfo, $downloadableId): array {
                $existingRef = MediaDownloadRef::query()
                    ->where('user_id', $monitor->user_id)
                    ->where('media_type', Series::class)
                    ->where('media_id', $monitor->series_id)
                    ->where('downloadable_id', $downloadableId)
                    ->first();

                if ($existingRef !== null) {
                    return [
                        'status' => self::STATUS_DUPLICATE,
                        'reason' => 'existing_download_ref',
                        'media_download_ref_id' => $existingRef->id,
                        'downloadable_id' => $downloadableId,
                        'episode_id' => $episode->id,
                    ];
                }

                $series = $monitor->relationLoaded('series') ? $monitor->series : $monitor->series()->first();
                if ($series === null) {
                    return [
                        'status' => self::STATUS_ERROR,
                        'reason' => 'series_not_found',
                        'message' => sprintf('Series [%d] was not found.', $monitor->series_id),
                        'downloadable_id' => $downloadableId,
                        'episode_id' => $episode->id,
                    ];
                }

                $url = CreateXtreamcodesDownloadUrl::run($episode);
                $gid = DownloadMedia::run($url, ['out' => CreateDownloadOut::run($seriesInfo, $episode)]);

                $downloadRef = MediaDownloadRef::fromSeriesAndEpisode($gid, $series, $episode, $monitor->user_id);
                $downloadRef->downloadable_id = $downloadableId;
                $downloadRef->saveOrFail();

                return [
                    'status' => self::STATUS_QUEUED,
                    'media_download_ref_id' => $downloadRef->id,
                    'downloadable_id' => $downloadableId,
                    'episode_id' => $episode->id,
                ];
            });
        } catch (LockTimeoutException) {
            return [
                'status' => self::STATUS_DUPLICATE,
                'reason' => 'queue_lock_timeout',
                'downloadable_id' => $downloadableId,
                'episode_id' => $episode->id,
            ];
        }
    }

    public static function lockKey(int $userId, int $seriesId, int $downloadableId): string
    {
        return sprintf('auto:episodes:queue:user:%d:series:%d:episode:%d', $userId, $seriesId, $downloadableId);
    }

    private function normalizeEpisodeId(string $episodeId): ?int
    {
        if (! preg_match('/^\d+$/', $episodeId)) {
            return null;
        }

        $normalized = ltrim($episodeId, '0');

        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > 10) {
            return null;
        }

        if (strlen($normalized) === 10 && strcmp($normalized, (string) self::UNSIGNED_INT32_MAX) > 0) {
            return null;
        }

        $downloadableId = (int) $normalized;

        if ($downloadableId <= 0) {
            return null;
        }

        return $downloadableId;
    }
}
