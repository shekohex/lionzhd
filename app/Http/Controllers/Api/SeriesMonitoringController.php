<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AutoEpisodes\ComputeNextRunAt;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteSeriesMonitoringRequest;
use App\Http\Requests\Api\StoreSeriesMonitoringRequest;
use App\Http\Requests\Api\UpdateSeriesMonitoringRequest;
use App\Http\Resources\Api\SeriesMonitorResource;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use App\Support\JsonApiErrorResponse;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

final class SeriesMonitoringController extends Controller
{
    public function show(#[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        $monitor = $this->monitorForSeries($user, $series);

        if (! $monitor instanceof SeriesMonitor) {
            throw new HttpResponseException(JsonApiErrorResponse::make(404, 'Monitoring has not been enabled for this series.'));
        }

        return new SeriesMonitorResource($monitor->load('series'));
    }

    public function store(StoreSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        $watchlist = $this->watchlistForSeries($user, $series);

        if (! $watchlist instanceof Watchlist) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, 'Add this series to your watchlist before enabling monitoring.', 'series'));
        }

        $monitor = SeriesMonitor::query()->firstOrNew([
            'user_id' => $user->id,
            'series_id' => $series->series_id,
        ]);

        $this->fillMonitor($monitor, $request->validated(), $watchlist);
        $monitor->save();

        return new SeriesMonitorResource($monitor->refresh()->load('series'));
    }

    public function update(UpdateSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        $monitor = $this->monitorForSeries($user, $series);

        if (! $monitor instanceof SeriesMonitor) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, 'Monitoring has not been enabled for this series.', 'series'));
        }

        $watchlist = $this->watchlistForSeries($user, $series);

        if (! $watchlist instanceof Watchlist) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, 'Add this series to your watchlist before updating monitoring.', 'series'));
        }

        $this->fillMonitor($monitor, $request->validated(), $watchlist);
        $monitor->save();

        return new SeriesMonitorResource($monitor->refresh()->load('series'));
    }

    public function destroy(DeleteSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        $monitor = $this->monitorForSeries($user, $series);

        if (! $monitor instanceof SeriesMonitor) {
            throw new HttpResponseException(JsonApiErrorResponse::make(404, 'Monitoring has not been enabled for this series.'));
        }

        $monitor->forceFill(['enabled' => false, 'next_run_at' => null])->save();
        $monitor->load('series');

        if ($request->removeFromWatchlist()) {
            $this->watchlistQuery($user, $series)->delete();
        }

        return new SeriesMonitorResource($monitor);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillMonitor(SeriesMonitor $monitor, array $validated, Watchlist $watchlist): void
    {
        $currentScheduleType = $monitor->exists && $monitor->schedule_type instanceof MonitorScheduleType
            ? $monitor->schedule_type
            : MonitorScheduleType::Hourly;
        $scheduleType = array_key_exists('schedule_type', $validated)
            ? MonitorScheduleType::from((string) $validated['schedule_type'])
            : $currentScheduleType;
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

    private function monitorForSeries(User $user, Series $series): ?SeriesMonitor
    {
        return SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $series->series_id)
            ->first();
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
