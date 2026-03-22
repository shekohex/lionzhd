<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Actions\SyncCategories;
use App\Enums\CategorySyncRunStatus;
use App\Http\Integrations\LionzTv\Requests\GetSeriesCategoriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodCategoriesRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Jobs\SyncCategories as SyncCategoriesJob;
use App\Models\Category;
use App\Models\CategorySyncRun;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

use function collect;

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
});

test('it updates category name by provider id identity', function (): void {
    Category::query()->create([
        'provider_id' => '10',
        'name' => 'Old Name',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    fakeCategories(
        vodPayload: [['category_id' => '10', 'category_name' => 'New Name']],
        seriesPayload: [['category_id' => '22', 'category_name' => 'Drama']],
    );

    $run = SyncCategories::run();

    expect(Category::query()->where('provider_id', '10')->value('name'))->toBe('New Name')
        ->and($run->status)->toBe(CategorySyncRunStatus::Success);
});

test('it persists canonical payload order independently for vod and series categories', function (): void {
    fakeCategories(
        vodPayload: [
            ['category_id' => 'shared-category', 'category_name' => 'Shared Category'],
            ['category_id' => 'vod-second', 'category_name' => 'Vod Second'],
            ['category_id' => 'vod-third', 'category_name' => 'Vod Third'],
        ],
        seriesPayload: [
            ['category_id' => 'series-first', 'category_name' => 'Series First'],
            ['category_id' => 'shared-category', 'category_name' => 'Shared Category'],
            ['category_id' => 'series-third', 'category_name' => 'Series Third'],
        ],
    );

    SyncCategories::run();

    expect(Category::query()->pluck('vod_sync_order', 'provider_id')->all())
        ->toMatchArray([
            Category::UNCATEGORIZED_VOD_PROVIDER_ID => null,
            Category::UNCATEGORIZED_SERIES_PROVIDER_ID => null,
            'shared-category' => 0,
            'vod-second' => 1,
            'vod-third' => 2,
            'series-first' => null,
            'series-third' => null,
        ])
        ->and(Category::query()->pluck('series_sync_order', 'provider_id')->all())
        ->toMatchArray([
            Category::UNCATEGORIZED_VOD_PROVIDER_ID => null,
            Category::UNCATEGORIZED_SERIES_PROVIDER_ID => null,
            'shared-category' => 1,
            'vod-second' => null,
            'vod-third' => null,
            'series-first' => 0,
            'series-third' => 2,
        ]);
});

test('it removes missing vod categories and moves vod content to vod uncategorized', function (): void {
    Category::query()->create([
        'provider_id' => 'missing-vod',
        'name' => 'Legacy',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    insertVodStream(streamId: 1001, categoryId: 'missing-vod');

    fakeCategories(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    SyncCategories::run();

    $vod = DB::table('vod_streams')->where('stream_id', 1001)->first();

    expect($vod)->not->toBeNull()
        ->and($vod->category_id)->toBe(Category::UNCATEGORIZED_VOD_PROVIDER_ID)
        ->and($vod->previous_category_id)->toBe('missing-vod')
        ->and(Category::query()->where('provider_id', 'missing-vod')->exists())->toBeFalse();
});

test('it remaps vod content back when missing vod category reappears', function (): void {
    Category::query()->create([
        'provider_id' => 'missing-vod',
        'name' => 'Legacy',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    insertVodStream(streamId: 1002, categoryId: 'missing-vod');

    fakeCategories(
        vodPayload: [['category_id' => 'vod-other', 'category_name' => 'Action']],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    SyncCategories::run();

    fakeCategories(
        vodPayload: [
            ['category_id' => 'vod-other', 'category_name' => 'Action'],
            ['category_id' => 'missing-vod', 'category_name' => 'Legacy'],
        ],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    SyncCategories::run();

    $vod = DB::table('vod_streams')->where('stream_id', 1002)->first();

    expect($vod)->not->toBeNull()
        ->and($vod->category_id)->toBe('missing-vod')
        ->and($vod->previous_category_id)->toBeNull();
});

test('it removes missing series categories and remaps back on series category reappearance', function (): void {
    Category::query()->create([
        'provider_id' => 'missing-series',
        'name' => 'Legacy Series',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    insertSeries(seriesId: 2001, categoryId: 'missing-series');

    fakeCategories(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [['category_id' => 'series-other', 'category_name' => 'Drama']],
    );

    SyncCategories::run();

    $seriesAfterRemoval = DB::table('series')->where('series_id', 2001)->first();

    expect($seriesAfterRemoval)->not->toBeNull()
        ->and($seriesAfterRemoval->category_id)->toBe(Category::UNCATEGORIZED_SERIES_PROVIDER_ID)
        ->and($seriesAfterRemoval->previous_category_id)->toBe('missing-series')
        ->and(Category::query()->where('provider_id', 'missing-series')->exists())->toBeFalse();

    fakeCategories(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [
            ['category_id' => 'series-other', 'category_name' => 'Drama'],
            ['category_id' => 'missing-series', 'category_name' => 'Legacy Series'],
        ],
    );

    SyncCategories::run();

    $seriesAfterRemap = DB::table('series')->where('series_id', 2001)->first();

    expect($seriesAfterRemap)->not->toBeNull()
        ->and($seriesAfterRemap->category_id)->toBe('missing-series')
        ->and($seriesAfterRemap->previous_category_id)->toBeNull();
});

test('it keeps successful-side changes and skips global cleanup when one source fails', function (): void {
    Category::query()->create([
        'provider_id' => 'vod-keep',
        'name' => 'Before Rename',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-stale',
        'name' => 'Stale',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'series-stable',
        'name' => 'Series Stable',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    insertSeries(
        seriesId: 2002,
        categoryId: Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
        previousCategoryId: 'series-stable',
    );

    fakeCategories(
        vodPayload: [['category_id' => 'vod-keep', 'category_name' => 'After Rename']],
        seriesPayload: [],
        seriesStatus: 500,
    );

    $run = SyncCategories::run();

    $series = DB::table('series')->where('series_id', 2002)->first();

    expect(Category::query()->where('provider_id', 'vod-keep')->value('name'))->toBe('After Rename')
        ->and(Category::query()->where('provider_id', 'vod-stale')->exists())->toBeTrue()
        ->and(Category::query()->where('provider_id', 'series-stable')->value('in_series'))->toBeTrue()
        ->and($series)->not->toBeNull()
        ->and($series->category_id)->toBe(Category::UNCATEGORIZED_SERIES_PROVIDER_ID)
        ->and($series->previous_category_id)->toBe('series-stable')
        ->and($run->status)->toBe(CategorySyncRunStatus::SuccessWithWarnings)
        ->and(collect($run->top_issues)->contains(fn (string $issue): bool => str_contains($issue, 'Series categories fetch failed')))->toBeTrue();
});

test('it safeguards empty source apply until forced confirmation', function (): void {
    Category::query()->create([
        'provider_id' => 'vod-missing',
        'name' => 'Legacy',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    insertVodStream(streamId: 1003, categoryId: 'vod-missing');

    fakeCategories(
        vodPayload: [],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $blockedRun = SyncCategories::run();
    $blockedVod = DB::table('vod_streams')->where('stream_id', 1003)->first();

    expect($blockedVod)->not->toBeNull()
        ->and($blockedVod->category_id)->toBe('vod-missing')
        ->and($blockedVod->previous_category_id)->toBeNull()
        ->and(Category::query()->where('provider_id', 'vod-missing')->value('in_vod'))->toBeTrue()
        ->and($blockedRun->status)->toBe(CategorySyncRunStatus::SuccessWithWarnings)
        ->and(collect($blockedRun->top_issues)->contains(fn (string $issue): bool => str_contains($issue, 'explicit confirmation')))->toBeTrue();

    fakeCategories(
        vodPayload: [],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    SyncCategories::run(forceEmptyVod: true);
    $forcedVod = DB::table('vod_streams')->where('stream_id', 1003)->first();

    expect($forcedVod)->not->toBeNull()
        ->and($forcedVod->category_id)->toBe(Category::UNCATEGORIZED_VOD_PROVIDER_ID)
        ->and($forcedVod->previous_category_id)->toBe('vod-missing')
        ->and(Category::query()->where('provider_id', 'vod-missing')->exists())->toBeFalse();
});

test('it requeues job when lock is already held', function (): void {
    fakeCategories(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $lock = Cache::lock(SyncCategoriesJob::LOCK_KEY, 600);
    expect($lock->get())->toBeTrue();

    try {
        $job = new SyncCategoriesJob;
        $job->withFakeQueueInteractions()->handle();
        $job->assertReleased(15);

        expect(CategorySyncRun::query()->count())->toBe(0);
    } finally {
        if ($lock->owner() !== null) {
            $lock->release();
        }
    }
});

test('it passes force-empty options from job into action run', function (): void {
    Category::query()->create([
        'provider_id' => 'vod-deleted-on-force',
        'name' => 'Legacy',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    insertVodStream(streamId: 1004, categoryId: 'vod-deleted-on-force');

    fakeCategories(
        vodPayload: [],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $job = new SyncCategoriesJob(forceEmptyVod: true);
    $job->withFakeQueueInteractions()
        ->assertNotReleased()
        ->assertNotFailed()
        ->handle();

    $vod = DB::table('vod_streams')->where('stream_id', 1004)->first();

    expect($vod)->not->toBeNull()
        ->and($vod->category_id)->toBe(Category::UNCATEGORIZED_VOD_PROVIDER_ID)
        ->and(Category::query()->where('provider_id', 'vod-deleted-on-force')->exists())->toBeFalse();
});

/**
 * @param array<int, array<string, mixed>> $vodPayload
 * @param array<int, array<string, mixed>> $seriesPayload
 */
function fakeCategories(array $vodPayload, array $seriesPayload, int $vodStatus = 200, int $seriesStatus = 200): void
{
    app()->bind(XtreamCodesConnector::class, function () use ($vodPayload, $seriesPayload, $vodStatus, $seriesStatus): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetVodCategoriesRequest::class => MockResponse::make($vodPayload, $vodStatus),
            GetSeriesCategoriesRequest::class => MockResponse::make($seriesPayload, $seriesStatus),
        ]));
    });
}

function insertVodStream(int $streamId, ?string $categoryId, ?string $previousCategoryId = null): void
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

function insertSeries(int $seriesId, ?string $categoryId, ?string $previousCategoryId = null): void
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
