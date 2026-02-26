<?php

declare(strict_types=1);

use App\Enums\CategorySyncRunStatus;
use App\Enums\MediaType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Integrations\LionzTv\Requests\GetSeriesCategoriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodCategoriesRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Jobs\SyncCategories as SyncCategoriesJob;
use App\Models\Category;
use App\Models\CategorySyncRun;
use App\Models\User;
use App\Models\XtreamCodesConfig;
use App\Actions\BuildCategorySidebarItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig([
        'host' => 'http://test.api',
        'port' => 80,
        'username' => 'test_user',
        'password' => 'test_pass',
    ]));
});

it('requires explicit confirmation when vod preflight is empty and dispatches after force flag', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    fakeCategoryPreflight(
        vodPayload: [],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $blockedResponse = $this->actingAs($admin)->patch(route('synccategories.update'));

    $blockedResponse->assertRedirect();
    $blockedResponse->assertSessionHasErrors(['confirmation', 'forceEmptyVod']);
    Queue::assertNothingPushed();

    fakeCategoryPreflight(
        vodPayload: [],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $forcedResponse = $this->actingAs($admin)->patch(route('synccategories.update'), [
        'forceEmptyVod' => 1,
    ]);

    $forcedResponse->assertRedirect();
    $forcedResponse->assertSessionHas('success', 'Category sync queued successfully.');
    Queue::assertPushed(SyncCategoriesJob::class, function (SyncCategoriesJob $job) use ($admin): bool {
        return $job->forceEmptyVod === true
            && $job->forceEmptySeries === false
            && $job->requestedByUserId === $admin->id;
    });
    Queue::assertPushed(SyncCategoriesJob::class, 1);
});

it('requires explicit confirmation when series preflight is empty and dispatches after force flag', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    fakeCategoryPreflight(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [],
    );

    $blockedResponse = $this->actingAs($admin)->patch(route('synccategories.update'));

    $blockedResponse->assertRedirect();
    $blockedResponse->assertSessionHasErrors(['confirmation', 'forceEmptySeries']);
    Queue::assertNothingPushed();

    fakeCategoryPreflight(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [],
    );

    $forcedResponse = $this->actingAs($admin)->patch(route('synccategories.update'), [
        'forceEmptySeries' => true,
    ]);

    $forcedResponse->assertRedirect();
    $forcedResponse->assertSessionHas('success', 'Category sync queued successfully.');
    Queue::assertPushed(SyncCategoriesJob::class, function (SyncCategoriesJob $job) use ($admin): bool {
        return $job->forceEmptyVod === false
            && $job->forceEmptySeries === true
            && $job->requestedByUserId === $admin->id;
    });
    Queue::assertPushed(SyncCategoriesJob::class, 1);
});

it('dispatches sync immediately when both preflight sources are non-empty', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    fakeCategoryPreflight(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $response = $this->actingAs($admin)->patch(route('synccategories.update'));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Category sync queued successfully.');
    Queue::assertPushed(SyncCategoriesJob::class, function (SyncCategoriesJob $job) use ($admin): bool {
        return $job->forceEmptyVod === false
            && $job->forceEmptySeries === false
            && $job->requestedByUserId === $admin->id;
    });
});

it('renders sync history page with run summaries and issues', function (): void {
    $admin = User::factory()->admin()->create();

    CategorySyncRun::query()->create([
        'requested_by_user_id' => $admin->id,
        'status' => CategorySyncRunStatus::Success,
        'started_at' => now(),
        'finished_at' => now(),
        'summary' => [
            'created' => 2,
            'updated' => 1,
            'removed' => 0,
        ],
        'top_issues' => ['Sample warning'],
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders(inertiaHeaders())
        ->get(route('synccategories.history'));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/synccategories-history');
    $response->assertJsonPath('props.runs.data.0.status', CategorySyncRunStatus::Success->value);
    $response->assertJsonPath('props.runs.data.0.summary.created', 2);
    $response->assertJsonPath('props.runs.data.0.top_issues.0', 'Sample warning');
});

it('builds movie sidebar items ordered A-Z with uncategorized last and selected zero category enabled', function (): void {
    seedMovieSidebarFixture();

    $items = BuildCategorySidebarItems::run(MediaType::Movie, 'vod-empty-selected');

    assertSidebarOrder($items, [
        'vod-alpha',
        'vod-empty',
        'vod-empty-selected',
        'vod-zeta',
        Category::UNCATEGORIZED_VOD_PROVIDER_ID,
    ]);
    assertOnlyUncategorizedFlag($items, Category::UNCATEGORIZED_VOD_PROVIDER_ID);

    expect(sidebarItem($items, 'vod-empty')->disabled)->toBeTrue();
    expect(sidebarItem($items, 'vod-empty-selected')->disabled)->toBeFalse();
    expect(sidebarItem($items, Category::UNCATEGORIZED_VOD_PROVIDER_ID)->disabled)->toBeFalse();
});

it('builds series sidebar items ordered A-Z with uncategorized last and selected zero category enabled', function (): void {
    seedSeriesSidebarFixture();

    $items = BuildCategorySidebarItems::run(MediaType::Series, 'series-empty-selected');

    assertSidebarOrder($items, [
        'series-alpha',
        'series-empty',
        'series-empty-selected',
        'series-zeta',
        Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
    ]);
    assertOnlyUncategorizedFlag($items, Category::UNCATEGORIZED_SERIES_PROVIDER_ID);

    expect(sidebarItem($items, 'series-empty')->disabled)->toBeTrue();
    expect(sidebarItem($items, 'series-empty-selected')->disabled)->toBeFalse();
    expect(sidebarItem($items, Category::UNCATEGORIZED_SERIES_PROVIDER_ID)->disabled)->toBeFalse();
});

function fakeCategoryPreflight(array $vodPayload, array $seriesPayload, int $vodStatus = 200, int $seriesStatus = 200): void
{
    app()->bind(XtreamCodesConnector::class, function () use ($vodPayload, $seriesPayload, $vodStatus, $seriesStatus): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetVodCategoriesRequest::class => MockResponse::make($vodPayload, $vodStatus),
            GetSeriesCategoriesRequest::class => MockResponse::make($seriesPayload, $seriesStatus),
        ]));
    });
}

function inertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}

function seedMovieSidebarFixture(): void
{
    Category::query()->create([
        'provider_id' => 'vod-zeta',
        'name' => 'zeta',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-alpha',
        'name' => 'Alpha',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-empty',
        'name' => 'Bravo',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-empty-selected',
        'name' => 'Charlie',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'series-only',
        'name' => 'Series Only',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    createVodStreamRecord(100, 'vod-alpha');
    createVodStreamRecord(101, 'vod-zeta');
    createVodStreamRecord(102, 'vod-zeta');
    createVodStreamRecord(103, null);
    createVodStreamRecord(104, '');
    createVodStreamRecord(105, Category::UNCATEGORIZED_VOD_PROVIDER_ID);
}

function seedSeriesSidebarFixture(): void
{
    Category::query()->create([
        'provider_id' => 'series-zeta',
        'name' => 'zeta',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'series-alpha',
        'name' => 'Alpha',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'series-empty',
        'name' => 'Bravo',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'series-empty-selected',
        'name' => 'Charlie',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-only',
        'name' => 'Vod Only',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    createSeriesRecord(200, 'series-alpha');
    createSeriesRecord(201, 'series-alpha');
    createSeriesRecord(202, 'series-zeta');
    createSeriesRecord(203, null);
    createSeriesRecord(204, '');
    createSeriesRecord(205, Category::UNCATEGORIZED_SERIES_PROVIDER_ID);
}

function createVodStreamRecord(int $streamId, ?string $categoryId): void
{
    DB::table('vod_streams')->insert([
        'stream_id' => $streamId,
        'num' => $streamId,
        'name' => sprintf('Vod %d', $streamId),
        'stream_type' => 'movie',
        'added' => now()->toIso8601String(),
        'category_id' => $categoryId,
        'container_extension' => 'mp4',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createSeriesRecord(int $seriesId, ?string $categoryId): void
{
    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'category_id' => $categoryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function assertSidebarOrder(array $items, array $expectedIds): void
{
    $actualIds = array_map(static fn ($item): string => $item->id, $items);

    expect($actualIds)->toBe($expectedIds);
}

function assertOnlyUncategorizedFlag(array $items, string $expectedUncategorizedId): void
{
    $uncategorizedIds = [];

    foreach ($items as $item) {
        if ($item->isUncategorized) {
            $uncategorizedIds[] = $item->id;
        }
    }

    expect($uncategorizedIds)->toBe([$expectedUncategorizedId]);
}

function sidebarItem(array $items, string $id): mixed
{
    foreach ($items as $item) {
        if ($item->id === $id) {
            return $item;
        }
    }

    throw new \RuntimeException(sprintf('Sidebar item %s not found.', $id));
}
