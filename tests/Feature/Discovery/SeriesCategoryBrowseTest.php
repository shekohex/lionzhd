<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

it('filters series by a valid category and returns selected filter props', function (): void {
    $user = User::factory()->create();

    seriesBrowseCreateCategory('series-action', 'Action');
    seriesBrowseCreateCategory('series-drama', 'Drama');

    seriesBrowseCreateSeriesRecord(1001, 'series-action');
    seriesBrowseCreateSeriesRecord(1002, 'series-action');
    seriesBrowseCreateSeriesRecord(1003, 'series-drama');

    $response = seriesBrowseIndexResponse($user, ['category' => 'series-action']);

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'series/index');
    $response->assertJsonPath('props.filters.category', 'series-action');

    $seriesRows = collect($response->json('props.series.data'));

    expect($seriesRows->pluck('series_id')->sort()->values()->all())->toBe([1001, 1002]);
    expect($seriesRows->pluck('category_id')->unique()->values()->all())->toBe(['series-action']);
});

it('filters uncategorized series for null empty and system uncategorized ids', function (): void {
    $user = User::factory()->create();

    seriesBrowseCreateCategory('series-drama', 'Drama');

    seriesBrowseCreateSeriesRecord(2001, null);
    seriesBrowseCreateSeriesRecord(2002, '');
    seriesBrowseCreateSeriesRecord(2003, Category::UNCATEGORIZED_SERIES_PROVIDER_ID);
    seriesBrowseCreateSeriesRecord(2004, 'series-drama');

    $response = seriesBrowseIndexResponse($user, ['category' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID]);

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', Category::UNCATEGORIZED_SERIES_PROVIDER_ID);

    $seriesIds = collect($response->json('props.series.data'))
        ->pluck('series_id')
        ->sort()
        ->values()
        ->all();

    expect($seriesIds)->toBe([2001, 2002, 2003]);
});

it('redirects invalid category filters to all series with warning flash', function (): void {
    $user = User::factory()->create();

    seriesBrowseCreateCategory('series-drama', 'Drama');

    $response = test()->actingAs($user)
        ->withHeaders(seriesBrowseInertiaHeaders())
        ->get(route('series', ['category' => 'missing-series-category']));

    $response->assertRedirect(route('series'));
    $response->assertSessionHas('warning', 'Category not found. Showing all categories.');

    $followed = seriesBrowseIndexResponse($user);

    $followed->assertOk();
    $followed->assertJsonPath('component', 'series/index');
});

it('keeps selected zero-item category active without redirect and without disabling it', function (): void {
    $user = User::factory()->create();

    seriesBrowseCreateCategory('series-empty-selected', 'Empty Selected');
    seriesBrowseCreateCategory('series-populated', 'Populated');
    seriesBrowseCreateSeriesRecord(3001, 'series-populated');

    $response = seriesBrowseIndexResponse($user, ['category' => 'series-empty-selected']);

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', 'series-empty-selected');
    $response->assertJsonPath('props.series.total', 0);

    expect(seriesBrowseCategoryItem($response, 'series-empty-selected')['disabled'])->toBeFalse();
});

it('disables zero-item category when it is not selected', function (): void {
    $user = User::factory()->create();

    seriesBrowseCreateCategory('series-empty', 'Empty');
    seriesBrowseCreateCategory('series-populated', 'Populated');
    seriesBrowseCreateSeriesRecord(4001, 'series-populated');

    $response = seriesBrowseIndexResponse($user);

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', null);

    expect(seriesBrowseCategoryItem($response, 'series-empty')['disabled'])->toBeTrue();
});

it('preserves active category query on paginator next page url', function (): void {
    $user = User::factory()->create();

    seriesBrowseCreateCategory('series-paginated', 'Paginated');

    foreach (range(1, 21) as $offset) {
        seriesBrowseCreateSeriesRecord(5000 + $offset, 'series-paginated');
    }

    $response = seriesBrowseIndexResponse($user, ['category' => 'series-paginated']);

    $response->assertOk();

    $nextPageUrl = $response->json('props.series.next_page_url');

    expect($nextPageUrl)->not->toBeNull();
    expect($nextPageUrl)->toContain('category=series-paginated');
});

it('orders categories case-insensitively and keeps uncategorized last', function (): void {
    $user = User::factory()->create();

    seriesBrowseCreateCategory('series-zeta', 'zeta');
    seriesBrowseCreateCategory('series-alpha', 'Alpha');
    seriesBrowseCreateCategory('series-bravo', 'bravo');

    seriesBrowseCreateSeriesRecord(6001, 'series-alpha');

    $response = seriesBrowseIndexResponse($user);

    $response->assertOk();

    $orderedIds = collect($response->json('props.categories'))->pluck('id')->all();

    expect($orderedIds)->toBe([
        'series-alpha',
        'series-bravo',
        'series-zeta',
        Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
    ]);
});

function seriesBrowseIndexResponse(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(seriesBrowseInertiaHeaders())
        ->get(route('series', $query));
}

function seriesBrowseCreateCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);
}

function seriesBrowseCreateSeriesRecord(int $seriesId, ?string $categoryId): void
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

function seriesBrowseCategoryItem(TestResponse $response, string $id): array
{
    $item = collect($response->json('props.categories'))->firstWhere('id', $id);

    if (! is_array($item)) {
        throw new \RuntimeException(sprintf('Category %s not found in response.', $id));
    }

    return $item;
}

function seriesBrowseInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
