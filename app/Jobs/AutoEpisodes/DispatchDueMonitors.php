<?php

declare(strict_types=1);

namespace App\Jobs\AutoEpisodes;

use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Models\AutoEpisodes\SeriesMonitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchDueMonitors implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int DISPATCH_LIMIT = 100;

    public int $uniqueFor = 50;

    public int $tries = 1;

    public function handle(): void
    {
        $dueMonitorIds = SeriesMonitor::query()
            ->select('series_monitors.id')
            ->join('users', 'users.id', '=', 'series_monitors.user_id')
            ->where('series_monitors.enabled', true)
            ->whereNotNull('series_monitors.next_run_at')
            ->where('series_monitors.next_run_at', '<=', now())
            ->whereNull('users.auto_episodes_paused_at')
            ->orderBy('series_monitors.next_run_at')
            ->limit(self::DISPATCH_LIMIT)
            ->pluck('series_monitors.id');

        foreach ($dueMonitorIds as $monitorId) {
            RunMonitorScan::dispatch((int) $monitorId, SeriesMonitorRunTrigger::Scheduled);
        }
    }
}
