<?php

declare(strict_types=1);

namespace App\Actions\AutoEpisodes;

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ManageSeriesMonitoring
{
    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(User $user, Series $series, array $validated): SeriesMonitor
    {
        $watchlist = $this->watchlistForSeries($user, $series);

        if (! $watchlist instanceof Watchlist) {
            throw ValidationException::withMessages(['series' => 'Add this series to your watchlist before enabling monitoring.']);
        }

        $monitor = SeriesMonitor::query()->firstOrNew([
            'user_id' => $user->id,
            'series_id' => $series->series_id,
        ]);

        $this->fillMonitor($monitor, $validated, $watchlist);
        $monitor->save();

        return $monitor;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, Series $series, array $validated): SeriesMonitor
    {
        $monitor = $this->monitorForSeries($user, $series);

        if (! $monitor instanceof SeriesMonitor) {
            throw ValidationException::withMessages(['series' => 'Monitoring has not been enabled for this series.']);
        }

        $watchlist = $this->watchlistForSeries($user, $series);

        if (! $watchlist instanceof Watchlist) {
            throw ValidationException::withMessages(['series' => 'Add this series to your watchlist before updating monitoring.']);
        }

        $this->fillMonitor($monitor, $validated, $watchlist);
        $monitor->save();

        return $monitor;
    }

    public function disable(User $user, Series $series, bool $removeFromWatchlist, bool $requireExisting = true): ?SeriesMonitor
    {
        $monitor = $this->monitorForSeries($user, $series);

        if (! $monitor instanceof SeriesMonitor) {
            if ($requireExisting) {
                throw ValidationException::withMessages(['series' => 'Monitoring has not been enabled for this series.']);
            }

            if ($removeFromWatchlist) {
                $this->watchlistQuery($user, $series)->delete();
            }

            return null;
        }

        $monitor->forceFill(['enabled' => false, 'next_run_at' => null])->save();
        $monitor->load('series');

        if ($removeFromWatchlist) {
            $this->watchlistQuery($user, $series)->delete();
        }

        return $monitor;
    }

    public function runNow(User $user, Series $series): SeriesMonitor
    {
        $monitor = $this->enabledMonitorForAction($user, $series, 'Enable monitoring before running this series now.');

        if ($monitor->run_now_available_at !== null && $monitor->run_now_available_at->isFuture()) {
            throw ValidationException::withMessages(['run_now' => sprintf('Run now is cooling down until %s.', $monitor->run_now_available_at->toDateTimeString())]);
        }

        RunMonitorScan::dispatch($monitor->id, SeriesMonitorRunTrigger::Manual);

        $monitor->forceFill([
            'run_now_available_at' => now()->toImmutable()->addSeconds(max(0, (int) config('auto_episodes.run_now_cooldown_seconds', 300))),
        ])->save();

        return $monitor;
    }

    public function backfill(User $user, Series $series, int $backfillCount): SeriesMonitor
    {
        if (! $this->watchlistForSeries($user, $series) instanceof Watchlist) {
            throw ValidationException::withMessages(['series' => 'Add this series to your watchlist before requesting backfill.']);
        }

        $monitor = $this->enabledMonitorForAction($user, $series, 'Enable monitoring before requesting backfill for this series.');

        RunMonitorScan::dispatch($monitor->id, SeriesMonitorRunTrigger::Backfill, ['backfill_count' => $backfillCount]);

        return $monitor;
    }

    public function monitorForSeries(User $user, Series $series): ?SeriesMonitor
    {
        return SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $series->series_id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillMonitor(SeriesMonitor $monitor, array $validated, Watchlist $watchlist): void
    {
        $currentScheduleType = $monitor->exists && $monitor->schedule_type instanceof MonitorScheduleType ? $monitor->schedule_type : MonitorScheduleType::Hourly;
        $scheduleType = array_key_exists('schedule_type', $validated) ? MonitorScheduleType::from((string) $validated['schedule_type']) : $currentScheduleType;
        $timezone = array_key_exists('timezone', $validated) ? (string) $validated['timezone'] : $monitor->timezone;
        $dailyTimeInput = array_key_exists('schedule_daily_time', $validated) ? $validated['schedule_daily_time'] : $monitor->schedule_daily_time;
        $weeklyDaysInput = array_key_exists('schedule_weekly_days', $validated) ? $validated['schedule_weekly_days'] : $monitor->schedule_weekly_days;
        $weeklyTimeInput = array_key_exists('schedule_weekly_time', $validated) ? $validated['schedule_weekly_time'] : $monitor->schedule_weekly_time;
        $monitoredSeasonsInput = array_key_exists('monitored_seasons', $validated) ? $validated['monitored_seasons'] : $monitor->monitored_seasons;

        $scheduleDailyTime = $scheduleType === MonitorScheduleType::Daily && is_string($dailyTimeInput) ? $dailyTimeInput : null;
        $scheduleWeeklyDays = $scheduleType === MonitorScheduleType::Weekly && is_array($weeklyDaysInput) ? $this->normalizeIntegerList($weeklyDaysInput) : [];
        $scheduleWeeklyTime = $scheduleType === MonitorScheduleType::Weekly && is_string($weeklyTimeInput) ? $weeklyTimeInput : null;
        $monitoredSeasons = is_array($monitoredSeasonsInput) ? $this->normalizeIntegerList($monitoredSeasonsInput) : [];

        $monitor->watchlist_id = $watchlist->id;
        $monitor->enabled = true;
        $monitor->timezone = $timezone;
        $monitor->schedule_type = $scheduleType;
        $monitor->schedule_daily_time = $scheduleDailyTime;
        $monitor->schedule_weekly_days = $scheduleWeeklyDays;
        $monitor->schedule_weekly_time = $scheduleWeeklyTime;
        $monitor->monitored_seasons = $monitoredSeasons;
        $monitor->per_run_cap = $this->resolvedPerRunCap($validated['per_run_cap'] ?? $monitor->per_run_cap);
        $monitor->next_run_at = $this->computeNextRunAt($timezone, $scheduleType, $scheduleDailyTime, $scheduleWeeklyDays, $scheduleWeeklyTime);
    }

    /**
     * @param  list<int>  $weeklyDays0Sun
     */
    private function computeNextRunAt(string $timezone, MonitorScheduleType $scheduleType, ?string $dailyTime, array $weeklyDays0Sun, ?string $weeklyTime): CarbonImmutable
    {
        return ComputeNextRunAt::run(now()->toImmutable(), $timezone, $scheduleType, $dailyTime, $weeklyDays0Sun, $weeklyTime);
    }

    private function enabledMonitorForAction(User $user, Series $series, string $message): SeriesMonitor
    {
        $monitor = $this->monitorForSeries($user, $series);

        if (! $monitor instanceof SeriesMonitor || ! $monitor->enabled) {
            throw ValidationException::withMessages(['series' => $message]);
        }

        return $monitor;
    }

    private function watchlistForSeries(User $user, Series $series): ?Watchlist
    {
        $watchlist = $this->watchlistQuery($user, $series)->first();

        return $watchlist instanceof Watchlist ? $watchlist : null;
    }

    private function watchlistQuery(User $user, Series $series): Builder
    {
        return Watchlist::query()
            ->where('user_id', $user->id)
            ->where('watchable_type', Series::class)
            ->where('watchable_id', $series->series_id);
    }

    private function resolvedPerRunCap(mixed $currentValue): int
    {
        $resolved = (int) $currentValue;
        $maxPerRunCap = max(1, (int) config('auto_episodes.max_per_run_cap', 100));

        return $resolved > 0 ? min($resolved, $maxPerRunCap) : min(max(1, (int) config('auto_episodes.default_per_run_cap', 5)), $maxPerRunCap);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<int>
     */
    private function normalizeIntegerList(array $values): array
    {
        $normalized = array_values(array_unique(array_map(static fn (mixed $value): int => (int) $value, $values)));
        sort($normalized);

        return $normalized;
    }
}
