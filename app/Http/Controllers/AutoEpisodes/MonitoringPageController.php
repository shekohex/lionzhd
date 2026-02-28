<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Http\Controllers\Controller;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class MonitoringPageController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        $monitors = SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->with(['series:series_id,name,cover'])
            ->orderByDesc('enabled')
            ->orderBy('next_run_at')
            ->get()
            ->map(static fn (SeriesMonitor $monitor): array => [
                'id' => $monitor->id,
                'series_id' => $monitor->series_id,
                'series_name' => $monitor->series?->name,
                'series_cover' => $monitor->series?->cover,
                'enabled' => $monitor->enabled,
                'timezone' => $monitor->timezone,
                'schedule_type' => $monitor->schedule_type?->value ?? (string) $monitor->schedule_type,
                'schedule_daily_time' => $monitor->schedule_daily_time,
                'schedule_weekly_days' => $monitor->schedule_weekly_days ?? [],
                'schedule_weekly_time' => $monitor->schedule_weekly_time,
                'monitored_seasons' => $monitor->monitored_seasons ?? [],
                'per_run_cap' => $monitor->per_run_cap,
                'next_run_at' => $monitor->next_run_at?->toIso8601String(),
                'last_attempt_at' => $monitor->last_attempt_at?->toIso8601String(),
                'last_attempt_status' => $monitor->last_attempt_status?->value,
                'last_successful_check_at' => $monitor->last_successful_check_at?->toIso8601String(),
                'run_now_available_at' => $monitor->run_now_available_at?->toIso8601String(),
            ])
            ->values();

        $presetTimes = array_values(array_filter(
            config('auto_episodes.preset_times', []),
            static fn (mixed $value): bool => is_string($value),
        ));

        return Inertia::render('settings/schedules', [
            'can_manage_schedules' => $user->can('auto-download-schedules'),
            'is_paused' => $user->auto_episodes_paused_at !== null,
            'auto_episodes_paused_at' => $user->auto_episodes_paused_at?->toIso8601String(),
            'preset_times' => $presetTimes,
            'monitors' => $monitors,
        ]);
    }
}
