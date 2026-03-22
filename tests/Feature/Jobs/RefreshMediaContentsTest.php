<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Jobs\RefreshMediaContents;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Category;
use App\Models\User;
use App\Models\XtreamCodesConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig(
        [
            'host' => 'http://test.api',
            'port' => 80,
            'username' => 'test_user',
            'password' => 'test_pass',
        ]
    ));

    app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([], 200),
            GetVodStreamsRequest::class => MockResponse::make([], 200),
        ]));
    });
});

test('it works', function (): void {
    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();
});

test('it skips search operations when search backend is unavailable', function (): void {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.meilisearch.host', 'http://127.0.0.1:59999');

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();
});

test('it bumps xtream dto cache namespace after sync', function (): void {
    Cache::store()->forever(XtreamCodesConnector::DTO_CACHE_NAMESPACE_KEY, 4);

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();

    expect(Cache::store()->get(XtreamCodesConnector::DTO_CACHE_NAMESPACE_KEY))->toBe(5);
});

test('it preserves series monitors when the synced series still exists upstream', function (): void {
    $seriesId = 12_345;
    $user = User::factory()->create();

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => 'Existing Series',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $monitor = SeriesMonitor::query()->create([
        'user_id' => $user->id,
        'series_id' => $seriesId,
        'enabled' => true,
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly,
        'monitored_seasons' => [1],
        'per_run_cap' => 5,
    ]);

    app()->bind(XtreamCodesConnector::class, function () use ($seriesId): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([
                [
                    'series_id' => $seriesId,
                    'num' => $seriesId,
                    'name' => 'Existing Series Updated',
                    'backdrop_path' => [],
                ],
            ], 200),
            GetVodStreamsRequest::class => MockResponse::make([], 200),
        ]));
    });

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();

    expect(SeriesMonitor::query()->find($monitor->id))
        ->not->toBeNull()
        ->and(DB::table('series')->where('series_id', $seriesId)->value('name'))
        ->toBe('Existing Series Updated');
});

test('it removes series that are no longer present upstream', function (): void {
    DB::table('series')->insert([
        [
            'series_id' => 20_001,
            'num' => 20_001,
            'name' => 'Keep Me',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'series_id' => 20_002,
            'num' => 20_002,
            'name' => 'Remove Me',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([
                [
                    'series_id' => 20_001,
                    'num' => 20_001,
                    'name' => 'Keep Me Updated',
                    'backdrop_path' => [],
                ],
            ], 200),
            GetVodStreamsRequest::class => MockResponse::make([], 200),
        ]));
    });

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();

    expect(DB::table('series')->pluck('name', 'series_id')->all())
        ->toBe([20_001 => 'Keep Me Updated']);
});

test('it replaces stale vod streams when upstream reuses a num for a new stream id', function (): void {
    DB::table('vod_streams')->insert([
        'stream_id' => 30_001,
        'num' => 777,
        'name' => 'Old Stream',
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => null,
        'rating_5based' => 0,
        'added' => '2025-01-01 00:00:00',
        'is_adult' => false,
        'category_id' => 'legacy',
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([], 200),
            GetVodStreamsRequest::class => MockResponse::make([
                [
                    'stream_id' => 30_002,
                    'num' => 777,
                    'name' => 'Replacement Stream',
                    'stream_type' => 'movie',
                    'stream_icon' => null,
                    'rating' => null,
                    'rating_5based' => 0,
                    'added' => '2025-01-02 00:00:00',
                    'is_adult' => false,
                    'category_id' => 'fresh',
                    'container_extension' => 'mkv',
                    'custom_sid' => null,
                    'direct_source' => null,
                ],
            ], 200),
        ]));
    });

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();

    expect(DB::table('vod_streams')->pluck('name', 'stream_id')->all())
        ->toBe([30_002 => 'Replacement Stream']);
});

test('it tolerates duplicate vod nums within the same upstream payload', function (): void {
    app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([], 200),
            GetVodStreamsRequest::class => MockResponse::make([
                [
                    'stream_id' => 40_001,
                    'num' => 888,
                    'name' => 'First Duplicate',
                    'stream_type' => 'movie',
                    'stream_icon' => null,
                    'rating' => null,
                    'rating_5based' => 0,
                    'added' => '2025-01-01 00:00:00',
                    'is_adult' => false,
                    'category_id' => 'one',
                    'container_extension' => 'mp4',
                    'custom_sid' => null,
                    'direct_source' => null,
                ],
                [
                    'stream_id' => 40_002,
                    'num' => 888,
                    'name' => 'Second Duplicate',
                    'stream_type' => 'movie',
                    'stream_icon' => null,
                    'rating' => null,
                    'rating_5based' => 0,
                    'added' => '2025-01-02 00:00:00',
                    'is_adult' => false,
                    'category_id' => 'two',
                    'container_extension' => 'mkv',
                    'custom_sid' => null,
                    'direct_source' => null,
                ],
            ], 200),
        ]));
    });

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();

    expect(DB::table('vod_streams')->pluck('name', 'stream_id')->all())
        ->toBe([
            40_001 => 'First Duplicate',
            40_002 => 'Second Duplicate',
        ]);
});

test('it normalizes movie category assignments, preserves source order, and dedupes repeated ids', function (): void {
    Category::query()->insert([
        [
            'provider_id' => 'movie-action',
            'name' => 'Movie Action',
            'in_vod' => true,
            'in_series' => false,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'provider_id' => 'movie-drama',
            'name' => 'Movie Drama',
            'in_vod' => true,
            'in_series' => false,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'provider_id' => 'movie-thriller',
            'name' => 'Movie Thriller',
            'in_vod' => true,
            'in_series' => false,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    insertRefreshMediaVodStream(streamId: 50_001, categoryId: 'legacy-movie-category');

    app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([], 200),
            GetVodStreamsRequest::class => MockResponse::make([
                [
                    'stream_id' => 50_001,
                    'num' => 50_001,
                    'name' => 'Assigned Movie',
                    'stream_type' => 'movie',
                    'stream_icon' => null,
                    'rating' => null,
                    'rating_5based' => 0,
                    'added' => '2025-02-01 00:00:00',
                    'is_adult' => false,
                    'category_id' => 'legacy-movie-category',
                    'category_ids' => ['movie-drama', 'movie-action', 'movie-drama', 'movie-thriller'],
                    'container_extension' => 'mp4',
                    'custom_sid' => null,
                    'direct_source' => null,
                ],
            ], 200),
        ]));
    });

    app()->make(RefreshMediaContents::class)
        ->withFakeQueueInteractions()
        ->assertNotFailed()
        ->handle();

    expect(assignmentRowsFor('vod', '50001'))
        ->toBe([
            ['category_provider_id' => 'movie-drama', 'source_order' => 0],
            ['category_provider_id' => 'movie-action', 'source_order' => 1],
            ['category_provider_id' => 'movie-thriller', 'source_order' => 2],
        ]);
});

test('it normalizes series category assignments and keeps legacy single-category backfill authoritative before refresh', function (): void {
    Category::query()->insert([
        [
            'provider_id' => 'legacy-series-category',
            'name' => 'Legacy Series Category',
            'in_vod' => false,
            'in_series' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'provider_id' => 'series-documentary',
            'name' => 'Series Documentary',
            'in_vod' => false,
            'in_series' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'provider_id' => 'series-comedy',
            'name' => 'Series Comedy',
            'in_vod' => false,
            'in_series' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    insertRefreshMediaSeries(seriesId: 60_001, categoryId: 'legacy-series-category');
    insertRefreshMediaSeries(seriesId: 60_002, categoryId: Category::UNCATEGORIZED_SERIES_PROVIDER_ID);
    Schema::drop('media_category_assignments');

    $migration = require base_path('database/migrations/2026_03_22_000002_create_media_category_assignments_table.php');
    $migration->up();

    expect(assignmentRowsFor('series', '60001'))
        ->toBe([
            ['category_provider_id' => 'legacy-series-category', 'source_order' => 0],
        ])
        ->and(assignmentRowsFor('series', '60002'))
        ->toBe([
            ['category_provider_id' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID, 'source_order' => 0],
        ]);

    app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([
                [
                    'series_id' => 60_001,
                    'num' => 60_001,
                    'name' => 'Series With Rich Categories',
                    'category_id' => 'legacy-series-category',
                    'category_ids' => ['series-documentary', 'series-comedy', 'series-documentary'],
                    'backdrop_path' => [],
                ],
                [
                    'series_id' => 60_002,
                    'num' => 60_002,
                    'name' => 'Series Without Rich Categories',
                    'category_id' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
                    'backdrop_path' => [],
                ],
            ], 200),
            GetVodStreamsRequest::class => MockResponse::make([], 200),
        ]));
    });

    app()->make(RefreshMediaContents::class)
        ->withFakeQueueInteractions()
        ->assertNotFailed()
        ->handle();

    expect(assignmentRowsFor('series', '60001'))
        ->toBe([
            ['category_provider_id' => 'series-documentary', 'source_order' => 0],
            ['category_provider_id' => 'series-comedy', 'source_order' => 1],
        ])
        ->and(assignmentRowsFor('series', '60002'))
        ->toBe([
            ['category_provider_id' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID, 'source_order' => 0],
        ]);
});

function assignmentRowsFor(string $mediaType, string $mediaProviderId): array
{
    return DB::table('media_category_assignments')
        ->where('media_type', $mediaType)
        ->where('media_provider_id', $mediaProviderId)
        ->orderBy('source_order')
        ->get(['category_provider_id', 'source_order'])
        ->map(fn (object $row): array => [
            'category_provider_id' => $row->category_provider_id,
            'source_order' => $row->source_order,
        ])
        ->all();
}

function insertRefreshMediaVodStream(int $streamId, ?string $categoryId, ?string $previousCategoryId = null): void
{
    DB::table('vod_streams')->insert([
        'stream_id' => $streamId,
        'num' => $streamId,
        'name' => sprintf('Vod %d', $streamId),
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => null,
        'rating_5based' => 0,
        'added' => '2024-01-01 00:00:00',
        'is_adult' => false,
        'category_id' => $categoryId,
        'previous_category_id' => $previousCategoryId,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertRefreshMediaSeries(int $seriesId, ?string $categoryId, ?string $previousCategoryId = null): void
{
    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'cover' => null,
        'plot' => null,
        'cast' => null,
        'director' => null,
        'genre' => null,
        'releaseDate' => null,
        'last_modified' => null,
        'rating' => null,
        'rating_5based' => null,
        'backdrop_path' => null,
        'youtube_trailer' => null,
        'episode_run_time' => null,
        'category_id' => $categoryId,
        'previous_category_id' => $previousCategoryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
