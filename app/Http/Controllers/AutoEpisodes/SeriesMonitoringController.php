<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Actions\AutoEpisodes\ComputeNextRunAt;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutoEpisodes\StoreSeriesMonitorRequest;
use App\Http\Requests\AutoEpisodes\UpdateSeriesMonitorRequest;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SeriesMonitoringController extends Controller
{
    public function store(StoreSeriesMonitorRequest $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $watchlist = $this->watchlistForSeries($user, $model);

        if (! $watchlist instanceof Watchlist) {
            throw ValidationException::withMessages([
                'series' => 'Add this series to your watchlist before enabling monitoring.',
            ]);
        }

        $monitor = SeriesMonitor::query()->firstOrNew([
            'user_id' => $user->id,
            'series_id' => $model->series_id,
        ]);

        $validated = $request->validated();
        $scheduleType = MonitorScheduleType::from((string) $validated['schedule_type']);

        $scheduleDailyTime = $scheduleType === MonitorScheduleType::Daily
            ? (is_string($validated['schedule_daily_time'] ?? null) ? $validated['schedule_daily_time'] : null)
            : null;

        $scheduleWeeklyDays = $scheduleType === MonitorScheduleType::Weekly
            ? $this->normalizeIntegerList(is_array($validated['schedule_weekly_days'] ?? null) ? $validated['schedule_weekly_days'] : [])
            : [];

        $scheduleWeeklyTime = $scheduleType === MonitorScheduleType::Weekly
            ? (is_string($validated['schedule_weekly_time'] ?? null) ? $validated['schedule_weekly_time'] : null)
            : null;

        $monitoredSeasons = $this->normalizeIntegerList(is_array($validated['monitored_seasons'] ?? null) ? $validated['monitored_seasons'] : []);

        $monitor->watchlist_id = $watchlist->id;
        $monitor->enabled = true;
        $monitor->timezone = (string) $validated['timezone'];
        $monitor->schedule_type = $scheduleType;
        $monitor->schedule_daily_time = $scheduleDailyTime;
        $monitor->schedule_weekly_days = $scheduleWeeklyDays;
        $monitor->schedule_weekly_time = $scheduleWeeklyTime;
        $monitor->monitored_seasons = $monitoredSeasons;
        $monitor->per_run_cap = $this->resolvedPerRunCap($validated['per_run_cap'] ?? $monitor->per_run_cap);
        $monitor->next_run_at = $this->computeNextRunAt([
            'timezone' => (string) $validated['timezone'],
            'schedule_type' => $scheduleType,
            'schedule_daily_time' => $scheduleDailyTime,
            'schedule_weekly_days' => $scheduleWeeklyDays,
            'schedule_weekly_time' => $scheduleWeeklyTime,
        ]);
        $monitor->save();

        return back()->with('success', 'Series monitoring enabled.');
    }

    public function update(UpdateSeriesMonitorRequest $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $monitor = SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $model->series_id)
            ->first();

        if (! $monitor instanceof SeriesMonitor) {
            throw ValidationException::withMessages([
                'series' => 'Monitoring has not been enabled for this series.',
            ]);
        }

        $watchlist = $this->watchlistForSeries($user, $model);

        if (! $watchlist instanceof Watchlist) {
            throw ValidationException::withMessages([
                'series' => 'Add this series to your watchlist before updating monitoring.',
            ]);
        }

        $validated = $request->validated();
        $currentScheduleType = $monitor->schedule_type instanceof MonitorScheduleType
            ? $monitor->schedule_type
            : MonitorScheduleType::from((string) $monitor->schedule_type);

        $scheduleType = array_key_exists('schedule_type', $validated)
            ? MonitorScheduleType::from((string) $validated['schedule_type'])
            : $currentScheduleType;

        $timezone = array_key_exists('timezone', $validated)
            ? (string) $validated['timezone']
            : $monitor->timezone;

        $dailyTimeInput = array_key_exists('schedule_daily_time', $validated)
            ? $validated['schedule_daily_time']
            : $monitor->schedule_daily_time;

        $weeklyDaysInput = array_key_exists('schedule_weekly_days', $validated)
            ? $validated['schedule_weekly_days']
            : $monitor->schedule_weekly_days;

        $weeklyTimeInput = array_key_exists('schedule_weekly_time', $validated)
            ? $validated['schedule_weekly_time']
            : $monitor->schedule_weekly_time;

        $scheduleDailyTime = $scheduleType === MonitorScheduleType::Daily && is_string($dailyTimeInput)
            ? $dailyTimeInput
            : null;

        $scheduleWeeklyDays = $scheduleType === MonitorScheduleType::Weekly && is_array($weeklyDaysInput)
            ? $this->normalizeIntegerList($weeklyDaysInput)
            : [];

        $scheduleWeeklyTime = $scheduleType === MonitorScheduleType::Weekly && is_string($weeklyTimeInput)
            ? $weeklyTimeInput
            : null;

        $monitoredSeasonsInput = array_key_exists('monitored_seasons', $validated)
            ? $validated['monitored_seasons']
            : $monitor->monitored_seasons;

        $monitoredSeasons = is_array($monitoredSeasonsInput)
            ? $this->normalizeIntegerList($monitoredSeasonsInput)
            : [];

        $monitor->watchlist_id = $watchlist->id;
        $monitor->enabled = true;
        $monitor->timezone = $timezone;
        $monitor->schedule_type = $scheduleType;
        $monitor->schedule_daily_time = $scheduleDailyTime;
        $monitor->schedule_weekly_days = $scheduleWeeklyDays;
        $monitor->schedule_weekly_time = $scheduleWeeklyTime;
        $monitor->monitored_seasons = $monitoredSeasons;
        $monitor->per_run_cap = $this->resolvedPerRunCap($validated['per_run_cap'] ?? $monitor->per_run_cap);
        $monitor->next_run_at = $this->computeNextRunAt([
            'timezone' => $timezone,
            'schedule_type' => $scheduleType,
            'schedule_daily_time' => $scheduleDailyTime,
            'schedule_weekly_days' => $scheduleWeeklyDays,
            'schedule_weekly_time' => $scheduleWeeklyTime,
        ]);
        $monitor->save();

        return back()->with('success', 'Series monitoring updated.');
    }

    public function destroy(Request $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $validated = $request->validate([
            'remove_from_watchlist' => ['sometimes', 'boolean'],
        ]);

        $removeFromWatchlist = (bool) ($validated['remove_from_watchlist'] ?? false);

        $monitor = SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $model->series_id)
            ->first();

        if ($monitor instanceof SeriesMonitor) {
            $monitor->forceFill([
                'enabled' => false,
                'next_run_at' => null,
            ])->save();
        }

        if ($removeFromWatchlist) {
            Watchlist::query()
                ->where('user_id', $user->id)
                ->where('watchable_type', Series::class)
                ->where('watchable_id', $model->series_id)
                ->delete();
        }

        return back()->with('success', 'Series monitoring disabled.');
    }

    private function computeNextRunAt(array $schedule): \Carbon\CarbonImmutable
    {
        return ComputeNextRunAt::run(
            nowUtc: now()->toImmutable(),
            timezone: $schedule['timezone'],
            scheduleType: $schedule['schedule_type'],
            dailyTime: $schedule['schedule_daily_time'],
            weeklyDays0Sun: $schedule['schedule_weekly_days'],
            weeklyTime: $schedule['schedule_weekly_time'],
        );
    }

    private function watchlistForSeries(User $user, Series $series): ?Watchlist
    {
        return Watchlist::query()
            ->where('user_id', $user->id)
            ->where('watchable_type', Series::class)
            ->where('watchable_id', $series->series_id)
            ->first();
    }

    private function resolvedPerRunCap(mixed $currentValue): int
    {
        $resolved = (int) $currentValue;
        $maxPerRunCap = max(1, (int) config('auto_episodes.max_per_run_cap', 100));

        if ($resolved > 0) {
            return min($resolved, $maxPerRunCap);
        }

        return min(max(1, (int) config('auto_episodes.default_per_run_cap', 5)), $maxPerRunCap);
    }

    private function normalizeIntegerList(array $values): array
    {
        $normalized = array_values(array_unique(array_map(static fn (mixed $value): int => (int) $value, $values)));
        sort($normalized);

        return $normalized;
    }
}
