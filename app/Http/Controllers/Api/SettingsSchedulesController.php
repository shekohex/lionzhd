<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AutoEpisodes\ComputeNextRunAt;
use App\Data\AutoEpisodes\SeriesMonitorData;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SettingsResource;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SettingsSchedulesController extends Controller
{
    public function index(#[CurrentUser] User $user): SettingsResource
    {
        Gate::authorize('auto-download-schedules');

        return new SettingsResource(['id' => 'schedules', 'type' => 'settings', 'attributes' => [
            'is_paused' => $user->auto_episodes_paused_at !== null,
            'auto_episodes_paused_at' => $user->auto_episodes_paused_at,
            'monitors' => SeriesMonitor::query()->where('user_id', $user->id)->with(['series:series_id,name,cover'])->get()->map(static fn (SeriesMonitor $monitor): array => SeriesMonitorData::fromModel($monitor)->toArray())->values()->all(),
        ]]);
    }

    public function update(Request $request, #[CurrentUser] User $user, string $operation): SettingsResource
    {
        Gate::authorize('auto-download-schedules');

        if ($operation === 'pause') {
            $validated = $request->validate(['paused' => ['required', 'boolean']]);
            $user->forceFill(['auto_episodes_paused_at' => ((bool) $validated['paused']) ? now()->toImmutable() : null])->save();

            return $this->index($user->refresh());
        }

        $validated = $request->validate(['series_ids' => ['required', 'array', 'list', 'min:1'], 'series_ids.*' => ['integer', 'min:1', 'distinct'], 'preset' => ['required', Rule::in(['hourly', 'daily', 'weekly'])]]);
        $schedule = $this->schedulePreset((string) $validated['preset']);
        $monitors = SeriesMonitor::query()->where('user_id', $user->id)->whereIn('series_id', $validated['series_ids'])->get();

        foreach ($monitors as $monitor) {
            $monitor->forceFill(['schedule_type' => $schedule['schedule_type'], 'schedule_daily_time' => $schedule['schedule_daily_time'], 'schedule_weekly_days' => $schedule['schedule_weekly_days'], 'schedule_weekly_time' => $schedule['schedule_weekly_time'], 'next_run_at' => ComputeNextRunAt::run(now()->toImmutable(), $monitor->timezone, $schedule['schedule_type'], $schedule['schedule_daily_time'], $schedule['schedule_weekly_days'], $schedule['schedule_weekly_time'])])->save();
        }

        return $this->index($user);
    }

    /** @return array{schedule_type: MonitorScheduleType, schedule_daily_time: ?string, schedule_weekly_days: list<int>, schedule_weekly_time: ?string} */
    private function schedulePreset(string $preset): array
    {
        $presetTimes = config('auto_episodes.preset_times', []);
        $defaultTime = is_array($presetTimes) && isset($presetTimes[0]) && is_string($presetTimes[0]) ? $presetTimes[0] : '06:00';

        return match ($preset) {
            'hourly' => ['schedule_type' => MonitorScheduleType::Hourly, 'schedule_daily_time' => null, 'schedule_weekly_days' => [], 'schedule_weekly_time' => null],
            'daily' => ['schedule_type' => MonitorScheduleType::Daily, 'schedule_daily_time' => $defaultTime, 'schedule_weekly_days' => [], 'schedule_weekly_time' => null],
            'weekly' => ['schedule_type' => MonitorScheduleType::Weekly, 'schedule_daily_time' => null, 'schedule_weekly_days' => [1], 'schedule_weekly_time' => $defaultTime],
            default => ['schedule_type' => MonitorScheduleType::Hourly, 'schedule_daily_time' => null, 'schedule_weekly_days' => [], 'schedule_weekly_time' => null],
        };
    }
}
