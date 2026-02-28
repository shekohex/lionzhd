<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Actions\AutoEpisodes\ComputeNextRunAt;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Http\Controllers\Controller;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SeriesMonitoringController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $watchlist = $this->watchlistForSeries($user, $model);

        if (! $watchlist instanceof Watchlist) {
            throw ValidationException::withMessages([
                'series' => 'Add this series to your watchlist before enabling monitoring.',
            ]);
        }

        $schedule = $this->validatedSchedule($request);

        $monitor = SeriesMonitor::query()->firstOrNew([
            'user_id' => $user->id,
            'series_id' => $model->series_id,
        ]);

        $monitor->watchlist_id = $watchlist->id;
        $monitor->enabled = true;
        $monitor->timezone = $schedule['timezone'];
        $monitor->schedule_type = $schedule['schedule_type'];
        $monitor->schedule_daily_time = $schedule['schedule_daily_time'];
        $monitor->schedule_weekly_days = $schedule['schedule_weekly_days'];
        $monitor->schedule_weekly_time = $schedule['schedule_weekly_time'];
        $monitor->monitored_seasons = $schedule['monitored_seasons'];
        $monitor->per_run_cap = $this->resolvedPerRunCap($monitor->per_run_cap);
        $monitor->next_run_at = $this->computeNextRunAt($schedule);
        $monitor->save();

        return back()->with('success', 'Series monitoring enabled.');
    }

    public function update(Request $request, #[CurrentUser] User $user, Series $model): RedirectResponse
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

        $schedule = $this->validatedSchedule($request);

        $monitor->watchlist_id = $watchlist->id;
        $monitor->enabled = true;
        $monitor->timezone = $schedule['timezone'];
        $monitor->schedule_type = $schedule['schedule_type'];
        $monitor->schedule_daily_time = $schedule['schedule_daily_time'];
        $monitor->schedule_weekly_days = $schedule['schedule_weekly_days'];
        $monitor->schedule_weekly_time = $schedule['schedule_weekly_time'];
        $monitor->monitored_seasons = $schedule['monitored_seasons'];
        $monitor->next_run_at = $this->computeNextRunAt($schedule);
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

    public function bulkApply(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $validated = $request->validate([
            'series_ids' => ['required', 'array', 'min:1'],
            'series_ids.*' => ['integer', 'min:1', 'distinct'],
            'preset' => ['required', Rule::in(['hourly', 'daily', 'weekly'])],
        ]);

        $schedule = $this->schedulePreset($validated['preset']);
        $seriesIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $validated['series_ids'])));

        $monitors = SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->whereIn('series_id', $seriesIds)
            ->get();

        foreach ($monitors as $monitor) {
            $monitor->forceFill([
                'schedule_type' => $schedule['schedule_type'],
                'schedule_daily_time' => $schedule['schedule_daily_time'],
                'schedule_weekly_days' => $schedule['schedule_weekly_days'],
                'schedule_weekly_time' => $schedule['schedule_weekly_time'],
                'next_run_at' => ComputeNextRunAt::run(
                    nowUtc: now()->toImmutable(),
                    timezone: $monitor->timezone,
                    scheduleType: $schedule['schedule_type'],
                    dailyTime: $schedule['schedule_daily_time'],
                    weeklyDays0Sun: $schedule['schedule_weekly_days'],
                    weeklyTime: $schedule['schedule_weekly_time'],
                ),
            ])->save();
        }

        return back()->with('success', sprintf('Applied schedule preset to %d series monitor(s).', $monitors->count()));
    }

    private function validatedSchedule(Request $request): array
    {
        $validated = $request->validate([
            'timezone' => ['required', 'timezone'],
            'schedule_type' => ['required', Rule::in(array_map(static fn (MonitorScheduleType $type): string => $type->value, MonitorScheduleType::cases()))],
            'schedule_daily_time' => ['nullable', 'string', Rule::in(config('auto_episodes.preset_times', []))],
            'schedule_weekly_days' => ['nullable', 'array', 'min:1'],
            'schedule_weekly_days.*' => ['integer', 'between:0,6'],
            'schedule_weekly_time' => ['nullable', 'string', Rule::in(config('auto_episodes.preset_times', []))],
            'monitored_seasons' => ['required', 'array', 'min:1'],
            'monitored_seasons.*' => ['integer', 'min:1'],
        ]);

        $scheduleType = MonitorScheduleType::from($validated['schedule_type']);
        $dailyTime = null;
        $weeklyDays = [];
        $weeklyTime = null;

        if ($scheduleType === MonitorScheduleType::Daily) {
            $dailyTime = $validated['schedule_daily_time'] ?? null;

            if (! is_string($dailyTime)) {
                throw ValidationException::withMessages([
                    'schedule_daily_time' => 'Daily schedule requires one preset time.',
                ]);
            }
        }

        if ($scheduleType === MonitorScheduleType::Weekly) {
            $weeklyDaysInput = $validated['schedule_weekly_days'] ?? null;
            $weeklyTime = $validated['schedule_weekly_time'] ?? null;

            if (! is_array($weeklyDaysInput) || ! is_string($weeklyTime)) {
                throw ValidationException::withMessages([
                    'schedule_weekly_days' => 'Weekly schedule requires days and one preset time.',
                ]);
            }

            $weeklyDays = array_values(array_unique(array_map(static fn (mixed $day): int => (int) $day, $weeklyDaysInput)));
            sort($weeklyDays);
        }

        $monitoredSeasons = array_values(array_unique(array_map(static fn (mixed $season): int => (int) $season, $validated['monitored_seasons'])));
        sort($monitoredSeasons);

        return [
            'timezone' => $validated['timezone'],
            'schedule_type' => $scheduleType,
            'schedule_daily_time' => $dailyTime,
            'schedule_weekly_days' => $weeklyDays,
            'schedule_weekly_time' => $weeklyTime,
            'monitored_seasons' => $monitoredSeasons,
        ];
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

        if ($resolved > 0) {
            return $resolved;
        }

        return max(1, (int) config('auto_episodes.default_per_run_cap', 5));
    }

    private function schedulePreset(string $preset): array
    {
        $presetTimes = config('auto_episodes.preset_times', []);
        $defaultTime = is_array($presetTimes) && isset($presetTimes[0]) && is_string($presetTimes[0])
            ? $presetTimes[0]
            : '06:00';

        return match ($preset) {
            'hourly' => [
                'schedule_type' => MonitorScheduleType::Hourly,
                'schedule_daily_time' => null,
                'schedule_weekly_days' => [],
                'schedule_weekly_time' => null,
            ],
            'daily' => [
                'schedule_type' => MonitorScheduleType::Daily,
                'schedule_daily_time' => $defaultTime,
                'schedule_weekly_days' => [],
                'schedule_weekly_time' => null,
            ],
            'weekly' => [
                'schedule_type' => MonitorScheduleType::Weekly,
                'schedule_daily_time' => null,
                'schedule_weekly_days' => [1],
                'schedule_weekly_time' => $defaultTime,
            ],
        };
    }
}
