<?php

declare(strict_types=1);

namespace App\Actions\AutoEpisodes;

use App\Concerns\AsAction;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Enums\AutoEpisodes\SeriesMonitorEventType;
use App\Enums\AutoEpisodes\SeriesMonitorRunStatus;
use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\AutoEpisodes\SeriesMonitorEpisode;
use App\Models\AutoEpisodes\SeriesMonitorEvent;
use App\Models\AutoEpisodes\SeriesMonitorRun;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class ScanSeriesForNewEpisodes
{
    use AsAction;

    public function __construct(private XtreamCodesConnector $xtreamCodesConnector) {}

    public function handle(int $monitorId, SeriesMonitorRunTrigger $trigger = SeriesMonitorRunTrigger::Scheduled, array $options = []): void
    {
        $monitor = SeriesMonitor::query()
            ->with(['user', 'series'])
            ->find($monitorId);

        if (! $monitor instanceof SeriesMonitor) {
            return;
        }

        $windowEndAt = now()->toImmutable();

        $run = SeriesMonitorRun::query()->create([
            'monitor_id' => $monitor->id,
            'trigger' => $trigger,
            'window_start_at' => $monitor->last_successful_check_at,
            'window_end_at' => $windowEndAt,
            'status' => SeriesMonitorRunStatus::Running,
            'started_at' => $windowEndAt,
        ]);

        try {
            $seriesInfo = $this->xtreamCodesConnector->send(new GetSeriesInfoRequest($monitor->series_id))->dtoOrFail();
            $episodes = $this->collectEpisodes($seriesInfo, $monitor->monitored_seasons);
            $syncedEpisodes = $this->syncEpisodes($monitor, $episodes, $windowEndAt);

            if ($monitor->last_successful_check_at === null && $trigger !== SeriesMonitorRunTrigger::Backfill) {
                $this->markBaselineAsSkipped($syncedEpisodes);

                $this->finalizeRun(
                    $monitor,
                    $run,
                    status: SeriesMonitorRunStatus::Success,
                    windowEndAt: $windowEndAt,
                    queuedCount: 0,
                    duplicateCount: 0,
                    deferredCount: 0,
                    errorCount: 0,
                    updateLastSuccessfulAt: true,
                );

                return;
            }

            $queuedCount = 0;
            $duplicateCount = 0;
            $deferredCount = 0;
            $errorCount = 0;

            $candidates = $this->queueCandidates($syncedEpisodes);
            $effectiveCap = $this->resolvePerRunCap($monitor, $trigger, $options);

            foreach ($candidates as $index => $candidate) {
                $episode = $candidate['episode'];
                $episodeState = $candidate['state'];

                if ($index >= $effectiveCap) {
                    $episodeState->state = SeriesMonitorEpisode::STATE_PENDING;
                    $episodeState->save();

                    SeriesMonitorEvent::query()->create([
                        'run_id' => $run->id,
                        'monitor_id' => $monitor->id,
                        'episode_id' => $episode->id,
                        'season' => $episode->season,
                        'episode_num' => $episode->episodeNum,
                        'type' => SeriesMonitorEventType::Deferred,
                        'reason' => 'per_run_cap_reached',
                        'meta' => ['per_run_cap' => $effectiveCap],
                    ]);

                    $deferredCount++;

                    continue;
                }

                $queueResult = QueueEpisodeDownload::run($monitor, $episode, $seriesInfo);
                $status = $queueResult['status'] ?? QueueEpisodeDownload::STATUS_ERROR;

                if ($status === QueueEpisodeDownload::STATUS_QUEUED) {
                    $episodeState->state = SeriesMonitorEpisode::STATE_QUEUED;
                    $episodeState->last_queued_at = $windowEndAt;
                    $episodeState->last_download_ref_id = (int) ($queueResult['media_download_ref_id'] ?? 0);
                    $episodeState->save();

                    SeriesMonitorEvent::query()->create([
                        'run_id' => $run->id,
                        'monitor_id' => $monitor->id,
                        'episode_id' => $episode->id,
                        'season' => $episode->season,
                        'episode_num' => $episode->episodeNum,
                        'type' => SeriesMonitorEventType::Queued,
                        'meta' => [
                            'media_download_ref_id' => $episodeState->last_download_ref_id,
                        ],
                    ]);

                    $queuedCount++;

                    continue;
                }

                if ($status === QueueEpisodeDownload::STATUS_DUPLICATE) {
                    SeriesMonitorEvent::query()->create([
                        'run_id' => $run->id,
                        'monitor_id' => $monitor->id,
                        'episode_id' => $episode->id,
                        'season' => $episode->season,
                        'episode_num' => $episode->episodeNum,
                        'type' => SeriesMonitorEventType::Duplicate,
                        'reason' => (string) ($queueResult['reason'] ?? 'duplicate'),
                        'meta' => [
                            'media_download_ref_id' => $queueResult['media_download_ref_id'] ?? null,
                            'downloadable_id' => $queueResult['downloadable_id'] ?? null,
                        ],
                    ]);

                    $duplicateCount++;

                    continue;
                }

                $episodeState->state = SeriesMonitorEpisode::STATE_PENDING;
                $episodeState->save();

                SeriesMonitorEvent::query()->create([
                    'run_id' => $run->id,
                    'monitor_id' => $monitor->id,
                    'episode_id' => $episode->id,
                    'season' => $episode->season,
                    'episode_num' => $episode->episodeNum,
                    'type' => SeriesMonitorEventType::Error,
                    'reason' => (string) ($queueResult['reason'] ?? 'queue_failed'),
                    'meta' => [
                        'message' => $queueResult['message'] ?? null,
                        'raw_episode_id' => $queueResult['episode_id'] ?? $episode->id,
                    ],
                ]);

                $errorCount++;
            }

            $runStatus = ($errorCount > 0 || $deferredCount > 0)
                ? SeriesMonitorRunStatus::SuccessWithWarnings
                : SeriesMonitorRunStatus::Success;

            $this->finalizeRun(
                $monitor,
                $run,
                status: $runStatus,
                windowEndAt: $windowEndAt,
                queuedCount: $queuedCount,
                duplicateCount: $duplicateCount,
                deferredCount: $deferredCount,
                errorCount: $errorCount,
                updateLastSuccessfulAt: true,
            );
        } catch (Throwable $throwable) {
            $run->forceFill([
                'status' => SeriesMonitorRunStatus::Failed,
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ])->save();

            $monitor->forceFill([
                'last_attempt_at' => $windowEndAt,
                'last_attempt_status' => SeriesMonitorRunStatus::Failed,
                'next_run_at' => $this->nextRunAt($monitor, $windowEndAt),
            ])->save();

            throw $throwable;
        }
    }

    private function finalizeRun(
        SeriesMonitor $monitor,
        SeriesMonitorRun $run,
        SeriesMonitorRunStatus $status,
        CarbonImmutable $windowEndAt,
        int $queuedCount,
        int $duplicateCount,
        int $deferredCount,
        int $errorCount,
        bool $updateLastSuccessfulAt,
    ): void {
        $run->forceFill([
            'status' => $status,
            'queued_count' => $queuedCount,
            'duplicate_count' => $duplicateCount,
            'deferred_count' => $deferredCount,
            'error_count' => $errorCount,
            'finished_at' => now(),
        ])->save();

        $attributes = [
            'last_attempt_at' => $windowEndAt,
            'last_attempt_status' => $status,
            'next_run_at' => $this->nextRunAt($monitor, $windowEndAt),
        ];

        if ($updateLastSuccessfulAt) {
            $attributes['last_successful_check_at'] = $windowEndAt;
        }

        $monitor->forceFill($attributes)->save();
    }

    private function nextRunAt(SeriesMonitor $monitor, CarbonImmutable $windowEndAt): CarbonImmutable
    {
        $scheduleType = $monitor->schedule_type;
        if (! $scheduleType instanceof MonitorScheduleType) {
            $scheduleType = MonitorScheduleType::from((string) $scheduleType);
        }

        return ComputeNextRunAt::run(
            nowUtc: $windowEndAt,
            timezone: $monitor->timezone,
            scheduleType: $scheduleType,
            dailyTime: $monitor->schedule_daily_time,
            weeklyDays0Sun: $this->normalizeWeeklyDays($monitor->schedule_weekly_days),
            weeklyTime: $monitor->schedule_weekly_time,
        );
    }

    private function normalizeWeeklyDays(mixed $weeklyDays): array
    {
        if (! is_array($weeklyDays)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $day): int => (int) $day, $weeklyDays));
    }

    private function resolvePerRunCap(SeriesMonitor $monitor, SeriesMonitorRunTrigger $trigger, array $options): int
    {
        $cap = max(0, (int) $monitor->per_run_cap);

        if ($trigger !== SeriesMonitorRunTrigger::Backfill) {
            return $cap;
        }

        $backfillCount = $options['backfill_count'] ?? null;

        if (! is_int($backfillCount) && ! is_string($backfillCount)) {
            return $cap;
        }

        $normalizedBackfillCount = (int) $backfillCount;

        if ($normalizedBackfillCount <= 0) {
            return $cap;
        }

        return min($cap, $normalizedBackfillCount);
    }

    private function markBaselineAsSkipped(array $syncedEpisodes): void
    {
        foreach ($syncedEpisodes as $syncedEpisode) {
            $episodeState = $syncedEpisode['state'];
            $episodeState->state = SeriesMonitorEpisode::STATE_SKIPPED;
            $episodeState->save();
        }
    }

    private function queueCandidates(array $syncedEpisodes): array
    {
        $candidates = array_values(array_filter(
            $syncedEpisodes,
            static fn (array $item): bool => in_array(
                $item['state']->state,
                [SeriesMonitorEpisode::STATE_PENDING, SeriesMonitorEpisode::STATE_FAILED],
                true,
            ),
        ));

        usort($candidates, static function (array $left, array $right): int {
            $seasonComparison = $left['episode']->season <=> $right['episode']->season;

            if ($seasonComparison !== 0) {
                return $seasonComparison;
            }

            return $left['episode']->episodeNum <=> $right['episode']->episodeNum;
        });

        return $candidates;
    }

    private function syncEpisodes(SeriesMonitor $monitor, array $episodes, CarbonImmutable $windowEndAt): array
    {
        $syncedEpisodes = [];

        foreach ($episodes as $episode) {
            $episodeState = SeriesMonitorEpisode::query()->firstOrNew([
                'monitor_id' => $monitor->id,
                'episode_id' => $episode->id,
            ]);

            if (! $episodeState->exists) {
                $episodeState->state = SeriesMonitorEpisode::STATE_PENDING;
                $episodeState->first_seen_at = $windowEndAt;
            }

            $episodeState->season = $episode->season;
            $episodeState->episode_num = $episode->episodeNum;
            $episodeState->last_seen_at = $windowEndAt;
            $episodeState->save();

            $syncedEpisodes[] = [
                'episode' => $episode,
                'state' => $episodeState,
            ];
        }

        return $syncedEpisodes;
    }

    private function collectEpisodes(SeriesInformation $seriesInfo, mixed $monitoredSeasons): array
    {
        $seasonFilter = [];
        if (is_array($monitoredSeasons)) {
            foreach ($monitoredSeasons as $season) {
                $seasonFilter[(int) $season] = true;
            }
        }

        $episodes = [];

        foreach ($seriesInfo->seasonsWithEpisodes as $seasonEpisodes) {
            foreach ($seasonEpisodes as $episode) {
                if (! $episode instanceof Episode) {
                    continue;
                }

                if ($seasonFilter !== [] && ! isset($seasonFilter[$episode->season])) {
                    continue;
                }

                $episodes[] = $episode;
            }
        }

        return $episodes;
    }
}
