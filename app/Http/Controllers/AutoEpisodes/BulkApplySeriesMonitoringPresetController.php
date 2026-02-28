<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Actions\AutoEpisodes\ComputeNextRunAt;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutoEpisodes\BulkUpdateSeriesMonitorsRequest;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class BulkApplySeriesMonitoringPresetController extends Controller
{
    public function __invoke(BulkUpdateSeriesMonitorsRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $validated = $request->validated();

        $schedule = $this->schedulePreset((string) $validated['preset']);
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
