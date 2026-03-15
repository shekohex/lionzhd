<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

it('returns series sidebar preferences isolated from movie state and other users', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createSeriesPersonalCategory('series-action', 'Action');
    createSeriesPersonalCategory('series-crime', 'Crime');
    createSeriesPersonalCategory('series-drama', 'Drama');
    createMoviePersonalCategory('movie-comedy', 'Comedy');
    createMoviePersonalCategory('movie-horror', 'Horror');

    seedPersonalizedSeriesRecord(2001, 'series-action');
    seedPersonalizedSeriesRecord(2002, 'series-crime');
    seedPersonalizedSeriesRecord(2003, 'series-drama');

    updateSeriesPersonalPreferences($user, [
        'pinned_ids' => ['series-crime'],
        'visible_ids' => ['series-crime', 'series-action'],
        'hidden_ids' => ['series-drama'],
    ]);

    updateMoviePersonalPreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => ['movie-comedy'],
        'hidden_ids' => ['movie-horror'],
    ]);

    updateSeriesPersonalPreferences($otherUser, [
        'pinned_ids' => [],
        'visible_ids' => ['series-drama'],
        'hidden_ids' => ['series-action', 'series-crime'],
    ]);

    $response = seriesPersonalBrowseResponse($user);

    $response->assertOk();
    $response->assertJsonPath('component', 'series/index');
    $response->assertJsonPath('props.categories.selectedCategoryIsHidden', false);
    $response->assertJsonPath('props.categories.selectedCategoryName', null);
    $response->assertJsonPath('props.categories.canReset', true);

    expect(seriesVisibleIds($response))->toBe(['all-categories', 'series-crime', 'series-action', Category::UNCATEGORIZED_SERIES_PROVIDER_ID]);
    expect(seriesHiddenIds($response))->toBe(['series-drama']);
});

it('covers hidden selected category read path smoke for series', function (): void {
    $user = User::factory()->create();

    createSeriesPersonalCategory('series-action', 'Action');
    createSeriesPersonalCategory('series-drama', 'Drama');

    seedPersonalizedSeriesRecord(2101, 'series-action');
    seedPersonalizedSeriesRecord(2102, 'series-drama');

    updateSeriesPersonalPreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => ['series-action'],
        'hidden_ids' => ['series-drama'],
    ]);

    $response = seriesPersonalBrowseResponse($user, ['category' => 'series-drama']);

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', 'series-drama');
    $response->assertJsonPath('props.series.total', 1);
    $response->assertJsonPath('props.series.data.0.category_id', 'series-drama');
    $response->assertJsonPath('props.categories.selectedCategoryIsHidden', true);
    $response->assertJsonPath('props.categories.selectedCategoryName', 'Drama');

    expect(seriesVisibleIds($response))->toBe(['all-categories', 'series-action', Category::UNCATEGORIZED_SERIES_PROVIDER_ID]);
    expect(seriesHiddenIds($response))->toBe(['series-drama']);
});

it('resets only series preferences and restores default order after new categories appear', function (): void {
    $user = User::factory()->create();

    createSeriesPersonalCategory('series-action', 'Action');
    createSeriesPersonalCategory('series-crime', 'Crime');
    createSeriesPersonalCategory('series-drama', 'Drama');
    createMoviePersonalCategory('movie-comedy', 'Comedy');
    createMoviePersonalCategory('movie-horror', 'Horror');

    seedPersonalizedSeriesRecord(2201, 'series-action');
    seedPersonalizedSeriesRecord(2202, 'series-crime');
    seedPersonalizedSeriesRecord(2203, 'series-drama');

    updateSeriesPersonalPreferences($user, [
        'pinned_ids' => ['series-crime'],
        'visible_ids' => ['series-crime', 'series-action'],
        'hidden_ids' => ['series-drama'],
    ]);

    updateMoviePersonalPreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => ['movie-comedy'],
        'hidden_ids' => ['movie-horror'],
    ]);

    createSeriesPersonalCategory('series-zulu', 'Zulu');
    seedPersonalizedSeriesRecord(2204, 'series-zulu');

    $beforeReset = seriesPersonalBrowseResponse($user);

    $beforeReset->assertOk();

    expect(seriesVisibleIds($beforeReset))->toBe(['all-categories', 'series-crime', 'series-action', 'series-zulu', Category::UNCATEGORIZED_SERIES_PROVIDER_ID]);
    expect(seriesHiddenIds($beforeReset))->toBe(['series-drama']);

    test()->actingAs($user)
        ->from(route('series'))
        ->delete(route('category-preferences.reset', ['mediaType' => MediaType::Series->value]))
        ->assertRedirect(route('series'))
        ->assertSessionHasNoErrors();

    $seriesAfterReset = seriesPersonalBrowseResponse($user);
    $moviesAfterReset = movieBrowseResponseForSeriesSuite($user);

    $seriesAfterReset->assertOk();
    $seriesAfterReset->assertJsonPath('props.categories.canReset', false);
    $seriesAfterReset->assertJsonPath('props.categories.selectedCategoryIsHidden', false);

    expect(seriesVisibleIds($seriesAfterReset))->toBe(['all-categories', 'series-action', 'series-crime', 'series-drama', 'series-zulu', Category::UNCATEGORIZED_SERIES_PROVIDER_ID]);
    expect(seriesHiddenIds($seriesAfterReset))->toBe([]);

    $moviesAfterReset->assertOk();
    $moviesAfterReset->assertJsonPath('props.categories.canReset', true);

    expect(collect($moviesAfterReset->json('props.categories.hiddenItems'))->pluck('id')->all())->toBe(['movie-horror']);
});

function seriesPersonalBrowseResponse(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(personalizedSeriesInertiaHeaders())
        ->get(route('series', $query));
}

function movieBrowseResponseForSeriesSuite(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(personalizedSeriesInertiaHeaders())
        ->get(route('movies', $query));
}

function updateSeriesPersonalPreferences(User $user, array $payload): void
{
    test()->actingAs($user)
        ->from(route('series'))
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Series->value]), $payload)
        ->assertRedirect(route('series'))
        ->assertSessionHasNoErrors();
}

function updateMoviePersonalPreferences(User $user, array $payload): void
{
    test()->actingAs($user)
        ->from(route('movies'))
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Movie->value]), $payload)
        ->assertRedirect(route('movies'))
        ->assertSessionHasNoErrors();
}

function createSeriesPersonalCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);
}

function createMoviePersonalCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);
}

function seedPersonalizedSeriesRecord(int $seriesId, ?string $categoryId): void
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

function seriesVisibleIds(TestResponse $response): array
{
    return collect($response->json('props.categories.visibleItems'))->pluck('id')->all();
}

function seriesHiddenIds(TestResponse $response): array
{
    return collect($response->json('props.categories.hiddenItems'))->pluck('id')->all();
}

function personalizedSeriesInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
