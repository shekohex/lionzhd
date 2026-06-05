<?php

declare(strict_types=1);

namespace App\Actions\AutoEpisodes;

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\User;
use InvalidArgumentException;

final class ApplySeriesMonitoringPreset
{
    /** @param list<int> $seriesIds */
    public function handle(User $user, array $seriesIds, string $preset): int
    {
        $schedule = $this->schedulePreset($preset);
        $seriesIds = array_values(array_unique($seriesIds));
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

        return $monitors->count();
    }

    /** @return array{schedule_type: MonitorScheduleType, schedule_daily_time: ?string, schedule_weekly_days: list<int>, schedule_weekly_time: ?string} */
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
            default => throw new InvalidArgumentException("Unsupported monitoring schedule preset [{$preset}]."),
        };
    }
}
