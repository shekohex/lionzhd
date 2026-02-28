<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs\AutoEpisodes;

use App\Jobs\AutoEpisodes\DispatchDueMonitors;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('it dispatches run monitor scans only for due enabled monitors', function (): void {
    Queue::fake();

    $dueMonitor = createMonitor(nextRunAt: now()->subMinute());
    $futureMonitor = createMonitor(nextRunAt: now()->addMinute());
    $disabledMonitor = createMonitor(enabled: false, nextRunAt: now()->subMinute());

    $dispatcher = new DispatchDueMonitors;
    $dispatcher->withFakeQueueInteractions()
        ->assertNotFailed()
        ->handle();

    Queue::assertPushed(RunMonitorScan::class, function (RunMonitorScan $scanJob) use ($dueMonitor): bool {
        return $scanJob->monitorId === $dueMonitor->id;
    });

    Queue::assertNotPushed(RunMonitorScan::class, function (RunMonitorScan $scanJob) use ($futureMonitor): bool {
        return $scanJob->monitorId === $futureMonitor->id;
    });

    Queue::assertNotPushed(RunMonitorScan::class, function (RunMonitorScan $scanJob) use ($disabledMonitor): bool {
        return $scanJob->monitorId === $disabledMonitor->id;
    });

    Queue::assertPushed(RunMonitorScan::class, 1);
});

test('it skips due monitors for paused users', function (): void {
    Queue::fake();

    $activeDueMonitor = createMonitor(nextRunAt: now()->subMinute());
    $pausedUser = User::factory()->create([
        'auto_episodes_paused_at' => now(),
    ]);
    $pausedDueMonitor = createMonitor(user: $pausedUser, nextRunAt: now()->subMinute());

    $dispatcher = new DispatchDueMonitors;
    $dispatcher->withFakeQueueInteractions()
        ->assertNotFailed()
        ->handle();

    Queue::assertPushed(RunMonitorScan::class, function (RunMonitorScan $scanJob) use ($activeDueMonitor): bool {
        return $scanJob->monitorId === $activeDueMonitor->id;
    });

    Queue::assertNotPushed(RunMonitorScan::class, function (RunMonitorScan $scanJob) use ($pausedDueMonitor): bool {
        return $scanJob->monitorId === $pausedDueMonitor->id;
    });

    Queue::assertPushed(RunMonitorScan::class, 1);
});

function createMonitor(?User $user = null, bool $enabled = true, mixed $nextRunAt = null): SeriesMonitor
{
    static $seriesId = 10_000;

    $owner = $user ?? User::factory()->create();
    $seriesId++;

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return SeriesMonitor::query()->create([
        'user_id' => $owner->id,
        'series_id' => $seriesId,
        'enabled' => $enabled,
        'timezone' => 'UTC',
        'schedule_type' => 'hourly',
        'monitored_seasons' => [],
        'per_run_cap' => 5,
        'next_run_at' => $nextRunAt,
    ]);
}
