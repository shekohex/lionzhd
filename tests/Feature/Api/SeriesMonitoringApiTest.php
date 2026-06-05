<?php

declare(strict_types=1);

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates shows updates and disables series monitoring as json api resources', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['read', 'monitoring:admin'])->plainTextToken;
    $series = apiMonitoringCreateSeries(['series_id' => 10, 'name' => 'Monitor Series']);
    $watchlist = $user->watchlists()->create(['watchable_type' => Series::class, 'watchable_id' => $series->series_id]);

    $this->withToken($token)
        ->postJson('/api/v1/series/10/monitoring', [
            'timezone' => 'UTC',
            'schedule_type' => 'hourly',
            'monitored_seasons' => [2, 1],
            'per_run_cap' => 3,
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'series-monitors')
        ->assertJsonPath('data.attributes.series_id', 10)
        ->assertJsonPath('data.attributes.enabled', true)
        ->assertJsonPath('data.attributes.schedule_type', 'hourly')
        ->assertJsonPath('data.attributes.monitored_seasons', [1, 2]);

    $monitor = SeriesMonitor::query()->where('user_id', $user->id)->where('series_id', 10)->firstOrFail();

    $this->withToken($token)
        ->getJson('/api/v1/series/10/monitoring', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $monitor->id)
        ->assertJsonPath('data.attributes.series_name', 'Monitor Series');

    $this->withToken($token)
        ->patchJson('/api/v1/series/10/monitoring', [
            'schedule_type' => 'daily',
            'schedule_daily_time' => '06:00',
            'monitored_seasons' => [3],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.schedule_type', 'daily')
        ->assertJsonPath('data.attributes.schedule_daily_time', '06:00')
        ->assertJsonPath('data.attributes.monitored_seasons', [3]);

    $deleteResponse = $this->withToken($token)
        ->deleteJson('/api/v1/series/10/monitoring', ['remove_from_watchlist' => true], ['Accept' => 'application/vnd.api+json']);

    $deleteResponse
        ->assertOk()
        ->assertJsonPath('data.attributes.enabled', false)
        ->assertJsonPath('data.attributes.next_run_at', null);

    expect($watchlist->fresh())->toBeNull();
});

it('queues run now and backfill monitoring scans', function (): void {
    Queue::fake();

    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['monitoring:admin'])->plainTextToken;
    $series = apiMonitoringCreateSeries(['series_id' => 20, 'name' => 'Queue Series']);
    $watchlist = $user->watchlists()->create(['watchable_type' => Series::class, 'watchable_id' => $series->series_id]);
    $monitor = apiMonitoringCreateMonitor($user, $series, $watchlist->id);

    $this->withToken($token)
        ->postJson('/api/v1/series/20/monitoring/run-now', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $monitor->id)
        ->assertJsonPath('data.attributes.run_now_available_at', fn (mixed $value): bool => is_string($value));

    Queue::assertPushed(RunMonitorScan::class, fn (RunMonitorScan $job): bool => $job->monitorId === $monitor->id && $job->trigger === SeriesMonitorRunTrigger::Manual);

    $this->withToken($token)
        ->postJson('/api/v1/series/20/monitoring/backfill', ['backfill_count' => 3], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $monitor->id);

    Queue::assertPushed(RunMonitorScan::class, fn (RunMonitorScan $job): bool => $job->monitorId === $monitor->id && $job->trigger === SeriesMonitorRunTrigger::Backfill && $job->options === ['backfill_count' => 3]);
});

it('does not remove watchlist when strict api monitoring delete fails for missing monitor', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['monitoring:admin'])->plainTextToken;
    $series = apiMonitoringCreateSeries(['series_id' => 25, 'name' => 'No Monitor Series']);
    $watchlist = $user->watchlists()->create(['watchable_type' => Series::class, 'watchable_id' => $series->series_id]);

    $this->withToken($token)
        ->deleteJson('/api/v1/series/25/monitoring', ['remove_from_watchlist' => true], ['Accept' => 'application/vnd.api+json'])
        ->assertNotFound()
        ->assertJsonPath('errors.0.source.parameter', 'series');

    expect($watchlist->fresh())->not->toBeNull();
});

it('enforces monitoring ability gate and json api validation', function (): void {
    $internal = User::factory()->memberInternal()->create();
    $external = User::factory()->memberExternal()->create();
    apiMonitoringCreateSeries(['series_id' => 30, 'name' => 'Denied Series']);
    $internal->watchlists()->create(['watchable_type' => Series::class, 'watchable_id' => 30]);

    $readToken = $internal->createToken('external-api', ['read'])->plainTextToken;

    $this->withToken($readToken)
        ->postJson('/api/v1/series/30/monitoring', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json');

    Sanctum::actingAs($external, ['monitoring:admin']);

    $this->flushHeaders()
        ->postJson('/api/v1/series/30/monitoring', [
            'timezone' => 'UTC',
            'schedule_type' => 'hourly',
            'monitored_seasons' => [],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.detail', 'External accounts cannot perform this action');

    Sanctum::actingAs($internal, ['monitoring:admin']);

    $this->flushHeaders()
        ->postJson('/api/v1/series/30/monitoring', [
            'timezone' => 'Not/AZone',
            'schedule_type' => 'hourly',
            'monitored_seasons' => [],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.source.parameter', 'timezone');

    $this->flushHeaders()
        ->postJson('/api/v1/series/30/monitoring', [
            'timezone' => 'UTC',
            'schedule_type' => 'daily',
            'monitored_seasons' => [],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.source.parameter', 'schedule_daily_time');

    $this->flushHeaders()
        ->postJson('/api/v1/series/30/monitoring/run-now', [], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.title', 'Unprocessable Content')
        ->assertJsonPath('errors.0.source.parameter', 'series');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function apiMonitoringCreateSeries(array $attributes = []): Series
{
    /** @var Series $series */
    $series = Series::withoutSyncingToSearch(static function () use ($attributes): Series {
        $series = new Series;

        $series->forceFill([
            'series_id' => $attributes['series_id'] ?? 1,
            'num' => $attributes['num'] ?? 1,
            'name' => $attributes['name'] ?? 'Series',
            'cover' => 'https://example.test/cover.jpg',
            'plot' => 'Plot',
            'cast' => 'Cast',
            'director' => 'Director',
            'genre' => 'Drama',
            'releaseDate' => '2026-01-01',
            'last_modified' => now(),
            'rating' => 8.0,
            'rating_5based' => 4.0,
            'backdrop_path' => ['https://example.test/backdrop.jpg'],
            'category_id' => 'drama',
        ])->save();

        return $series;
    });

    return $series;
}

function apiMonitoringCreateMonitor(User $user, Series $series, int $watchlistId): SeriesMonitor
{
    return SeriesMonitor::query()->create([
        'user_id' => $user->id,
        'series_id' => $series->series_id,
        'watchlist_id' => $watchlistId,
        'enabled' => true,
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly,
        'schedule_daily_time' => null,
        'schedule_weekly_days' => [],
        'schedule_weekly_time' => null,
        'monitored_seasons' => [1],
        'per_run_cap' => 5,
        'next_run_at' => now()->addHour(),
    ]);
}
