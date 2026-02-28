<?php

declare(strict_types=1);

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('keeps schedules page visible to all authenticated users', function (string $role): void {
    $user = match ($role) {
        'external' => User::factory()->memberExternal()->create(),
        'internal' => User::factory()->memberInternal()->create(),
        'admin' => User::factory()->admin()->create(),
    };

    $response = $this->actingAs($user)
        ->withHeaders(autoEpisodesAccessInertiaHeaders())
        ->get(route('schedules'));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/schedules');
})->with(['external', 'internal', 'admin']);

it('allows external members to view series details', function (): void {
    $user = User::factory()->memberExternal()->create();
    $series = createAutoEpisodesSeries();

    fakeAutoEpisodesSeriesShowResponse($series->series_id);

    $response = $this->actingAs($user)
        ->withHeaders(autoEpisodesAccessInertiaHeaders())
        ->get(route('series.show', ['model' => $series->series_id]));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'series/show');
});

it('forbids external members from monitoring mutation endpoints', function (string $method, string $routeName): void {
    $user = User::factory()->memberExternal()->create();
    $series = createAutoEpisodesSeries();

    $response = sendAutoEpisodesMutationRequest(
        testCase: $this,
        user: $user,
        method: $method,
        routeName: $routeName,
        seriesId: $series->series_id,
    );

    $response->assertForbidden();
})->with(autoEpisodesAccessMutationEndpoints());

it('allows internal members and admins to hit monitoring mutation endpoints', function (string $role): void {
    $user = $role === 'admin'
        ? User::factory()->admin()->create()
        : User::factory()->memberInternal()->create();

    $series = createAutoEpisodesSeries();
    $watchlist = createAutoEpisodesWatchlist($user, $series);
    createAutoEpisodesMonitor($user, $series, $watchlist);

    foreach (autoEpisodesAccessMutationEndpoints() as [$method, $routeName]) {
        $response = sendAutoEpisodesMutationRequest(
            testCase: $this,
            user: $user,
            method: $method,
            routeName: $routeName,
            seriesId: $series->series_id,
        );

        expect($response->status())->not->toBe(403);
    }
})->with(['internal', 'admin']);

it('does not auto-dispatch backfill on enable or update and dispatches only via explicit endpoint', function (string $role): void {
    Queue::fake();

    $user = $role === 'admin'
        ? User::factory()->admin()->create()
        : User::factory()->memberInternal()->create();

    $series = createAutoEpisodesSeries();
    createAutoEpisodesWatchlist($user, $series);

    $this->actingAs($user)->post(route('series.monitoring.store', ['model' => $series->series_id]), [
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly->value,
        'monitored_seasons' => [1, 2],
    ])->assertStatus(302);

    $this->actingAs($user)->patch(route('series.monitoring.update', ['model' => $series->series_id]), [
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly->value,
        'monitored_seasons' => [1, 2],
    ])->assertStatus(302);

    Queue::assertNotPushed(RunMonitorScan::class);

    $monitor = SeriesMonitor::query()
        ->where('user_id', $user->id)
        ->where('series_id', $series->series_id)
        ->firstOrFail();

    $backfillCount = (int) (config('auto_episodes.backfill_preset_counts.0') ?? 1);

    $this->actingAs($user)->post(route('series.monitoring.backfill', ['model' => $series->series_id]), [
        'backfill_count' => $backfillCount,
    ])->assertStatus(302);

    Queue::assertPushed(RunMonitorScan::class, function (RunMonitorScan $scanJob) use ($monitor, $backfillCount): bool {
        return $scanJob->monitorId === $monitor->id
            && $scanJob->trigger === SeriesMonitorRunTrigger::Backfill
            && ($scanJob->options['backfill_count'] ?? null) === $backfillCount;
    });
})->with(['internal', 'admin']);

it('applies can auto download schedules middleware to all monitoring mutation routes', function (string $routeName): void {
    $route = app('router')->getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('can:auto-download-schedules');
})->with([
    'series.monitoring.store',
    'series.monitoring.update',
    'series.monitoring.destroy',
    'series.monitoring.run-now',
    'series.monitoring.backfill',
    'schedules.bulk-apply',
    'schedules.pause',
]);

function autoEpisodesAccessMutationEndpoints(): array
{
    return [
        ['post', 'series.monitoring.store'],
        ['patch', 'series.monitoring.update'],
        ['post', 'series.monitoring.run-now'],
        ['post', 'series.monitoring.backfill'],
        ['delete', 'series.monitoring.destroy'],
        ['patch', 'schedules.bulk-apply'],
        ['patch', 'schedules.pause'],
    ];
}

function sendAutoEpisodesMutationRequest(
    mixed $testCase,
    User $user,
    string $method,
    string $routeName,
    int $seriesId,
): \Illuminate\Testing\TestResponse {
    $url = str_starts_with($routeName, 'series.')
        ? route($routeName, ['model' => $seriesId])
        : route($routeName);

    $payload = match ($routeName) {
        'series.monitoring.store', 'series.monitoring.update' => [
            'timezone' => 'UTC',
            'schedule_type' => MonitorScheduleType::Hourly->value,
            'monitored_seasons' => [1, 2],
        ],
        'series.monitoring.destroy' => ['remove_from_watchlist' => false],
        'series.monitoring.backfill' => [
            'backfill_count' => (int) (config('auto_episodes.backfill_preset_counts.0') ?? 1),
        ],
        'schedules.bulk-apply' => [
            'series_ids' => [$seriesId],
            'preset' => 'hourly',
        ],
        'schedules.pause' => ['paused' => true],
        default => [],
    };

    return match ($method) {
        'post' => $testCase->actingAs($user)->post($url, $payload),
        'patch' => $testCase->actingAs($user)->patch($url, $payload),
        'delete' => $testCase->actingAs($user)->delete($url, $payload),
    };
}

function createAutoEpisodesSeries(): Series
{
    static $seriesId = 50_000;

    $seriesId++;

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Series::query()->findOrFail($seriesId);
}

function createAutoEpisodesWatchlist(User $user, Series $series): Watchlist
{
    return Watchlist::query()->create([
        'user_id' => $user->id,
        'watchable_type' => Series::class,
        'watchable_id' => $series->series_id,
    ]);
}

function createAutoEpisodesMonitor(User $user, Series $series, Watchlist $watchlist): SeriesMonitor
{
    return SeriesMonitor::query()->create([
        'user_id' => $user->id,
        'series_id' => $series->series_id,
        'watchlist_id' => $watchlist->id,
        'enabled' => true,
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly,
        'schedule_daily_time' => null,
        'schedule_weekly_days' => [],
        'schedule_weekly_time' => null,
        'monitored_seasons' => [1],
        'per_run_cap' => 5,
        'next_run_at' => now()->subMinute(),
        'run_now_available_at' => null,
    ]);
}

function fakeAutoEpisodesSeriesShowResponse(int $seriesId): void
{
    $mockClient = new MockClient([
        GetSeriesInfoRequest::class => MockResponse::make([
            'info' => [
                'name' => sprintf('Series %d', $seriesId),
                'cover' => 'https://example.test/cover.jpg',
                'plot' => 'plot',
                'cast' => 'cast',
                'director' => 'director',
                'genre' => 'genre',
                'releaseDate' => '2026-01-01',
                'last_modified' => '2026-01-01 00:00:00',
                'rating' => '8.0',
                'rating_5based' => 4.0,
                'backdrop_path' => ['https://example.test/backdrop.jpg'],
                'youtube_trailer' => 'https://youtube.com/watch?v=test',
                'episode_run_time' => '00:45:00',
                'category_id' => '1',
            ],
            'seasons' => ['1'],
            'episodes' => [
                '1' => [
                    [
                        'id' => '101',
                        'season' => 1,
                        'episode_num' => 1,
                        'title' => 'Episode 1',
                        'container_extension' => 'mkv',
                        'custom_sid' => 'sid-101',
                        'added' => '2026-01-01 00:00:00',
                        'direct_source' => '',
                        'info' => [
                            'duration_secs' => 2700,
                            'duration' => '00:45:00',
                            'bitrate' => 1000,
                            'video' => [],
                            'audio' => [],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });
}

function autoEpisodesAccessInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
