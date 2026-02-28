<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Data\AutoEpisodes\MonitoringPageData;
use App\Data\AutoEpisodes\SeriesMonitorData;
use App\Data\AutoEpisodes\SeriesMonitorEventData;
use App\Http\Controllers\Controller;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\AutoEpisodes\SeriesMonitorEvent;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class MonitoringPageController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        $user->refresh();

        $monitors = collect();

        if ($user->exists && Schema::hasTable('series_monitors')) {
            $monitors = SeriesMonitor::query()
                ->where('user_id', $user->id)
                ->with(['series:series_id,name,cover'])
                ->orderByDesc('enabled')
                ->orderBy('next_run_at')
                ->get()
                ->map(static fn (SeriesMonitor $monitor): SeriesMonitorData => SeriesMonitorData::from($monitor))
                ->values();
        }

        $events = collect();

        if ($user->exists && Schema::hasTable('series_monitors') && Schema::hasTable('series_monitor_events')) {
            $events = SeriesMonitorEvent::query()
                ->whereHas('monitor', static function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id);
                })
                ->with(['monitor:id,series_id', 'monitor.series:series_id,name,cover'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(static fn (SeriesMonitorEvent $event): SeriesMonitorEventData => SeriesMonitorEventData::from($event))
                ->values();
        }

        return Inertia::render('settings/schedules', new MonitoringPageData(
            can_manage_schedules: $user->can('auto-download-schedules'),
            is_paused: $user->auto_episodes_paused_at !== null,
            auto_episodes_paused_at: $user->auto_episodes_paused_at,
            monitors: $monitors->all(),
            events: $events->all(),
            preset_times: $this->presetTimes(),
            backfill_preset_counts: $this->backfillPresetCounts(),
            run_now_cooldown_seconds: max(0, (int) config('auto_episodes.run_now_cooldown_seconds', 300)),
        ));
    }

    private function presetTimes(): array
    {
        return collect(config('auto_episodes.preset_times', []))
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
    }

    private function backfillPresetCounts(): array
    {
        return collect(config('auto_episodes.backfill_preset_counts', []))
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
}
