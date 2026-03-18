<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

it('returns movie sidebar preferences isolated from series state and other users', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createMovieCategory('movie-action', 'Action');
    createMovieCategory('movie-comedy', 'Comedy');
    createMovieCategory('movie-drama', 'Drama');
    createSeriesCategory('series-crime', 'Crime');
    createSeriesCategory('series-family', 'Family');

    seedPersonalizedMovieRecord(1001, 'Movie Action', 'movie-action');
    seedPersonalizedMovieRecord(1002, 'Movie Comedy', 'movie-comedy');
    seedPersonalizedMovieRecord(1003, 'Movie Drama', 'movie-drama');

    updateMoviePreferences($user, [
        'pinned_ids' => ['movie-comedy'],
        'visible_ids' => ['movie-comedy', 'movie-action'],
        'hidden_ids' => ['movie-drama'],
    ]);

    updateSeriesPreferences($user, [
        'pinned_ids' => ['series-family'],
        'visible_ids' => ['series-family'],
        'hidden_ids' => ['series-crime'],
    ]);

    updateMoviePreferences($otherUser, [
        'pinned_ids' => [],
        'visible_ids' => ['movie-drama'],
        'hidden_ids' => ['movie-action', 'movie-comedy'],
    ]);

    $response = movieBrowseResponse($user);

    $response->assertOk();
    $response->assertJsonPath('component', 'movies/index');
    $response->assertJsonPath('props.categories.selectedCategoryIsHidden', false);
    $response->assertJsonPath('props.categories.selectedCategoryName', null);
    $response->assertJsonPath('props.categories.canReset', true);

    expect(movieVisibleIds($response))->toBe(['all-categories', 'movie-comedy', 'movie-action', Category::UNCATEGORIZED_VOD_PROVIDER_ID]);
    expect(movieHiddenIds($response))->toBe(['movie-drama']);
});

it('covers hidden selected category read path smoke for movies', function (): void {
    $user = User::factory()->create();

    createMovieCategory('movie-action', 'Action');
    createMovieCategory('movie-drama', 'Drama');

    seedPersonalizedMovieRecord(1101, 'Movie Action', 'movie-action');
    seedPersonalizedMovieRecord(1102, 'Movie Drama', 'movie-drama');

    updateMoviePreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => ['movie-action'],
        'hidden_ids' => ['movie-drama'],
    ]);

    $response = movieBrowseResponse($user, ['category' => 'movie-drama']);

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', 'movie-drama');
    $response->assertJsonPath('props.movies.total', 1);
    $response->assertJsonPath('props.movies.data.0.category_id', 'movie-drama');
    $response->assertJsonPath('props.categories.selectedCategoryIsHidden', true);
    $response->assertJsonPath('props.categories.selectedCategoryName', 'Drama');

    expect(movieVisibleIds($response))->toBe(['all-categories', 'movie-action', Category::UNCATEGORIZED_VOD_PROVIDER_ID]);
    expect(movieHiddenIds($response))->toBe(['movie-drama']);
});

it('ignored all categories excludes movies for the authenticated user', function (): void {
    $user = User::factory()->create();

    createMovieCategory('movie-action', 'Action');
    createMovieCategory('movie-comedy', 'Comedy');
    createMovieCategory('movie-drama', 'Drama');

    seedPersonalizedMovieRecord(1301, 'Movie Action', 'movie-action');
    seedPersonalizedMovieRecord(1302, 'Movie Comedy', 'movie-comedy');
    seedPersonalizedMovieRecord(1303, 'Movie Drama', 'movie-drama');

    updateMoviePreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => ['movie-action', 'movie-drama'],
        'hidden_ids' => [],
        'ignored_ids' => ['movie-comedy'],
    ]);

    $response = movieBrowseResponse($user);

    $response->assertOk();
    $response->assertJsonPath('props.movies.total', 2);

    expect(movieBrowseNames($response))->toBe(['Movie Drama', 'Movie Action']);
});

it('ignored category filtering stays applied while browsing a non-ignored movie category', function (): void {
    $user = User::factory()->create();

    createMovieCategory('movie-action', 'Action');
    createMovieCategory('movie-comedy', 'Comedy');

    seedPersonalizedMovieRecord(1401, 'Action Feature', 'movie-action');
    seedPersonalizedMovieRecord(1402, 'Comedy Feature', 'movie-comedy');

    updateMoviePreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => ['movie-action'],
        'hidden_ids' => [],
        'ignored_ids' => ['movie-comedy'],
    ]);

    $response = movieBrowseResponse($user, ['category' => 'movie-action']);

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', 'movie-action');
    $response->assertJsonPath('props.movies.total', 1);

    expect(movieBrowseNames($response))->toBe(['Action Feature']);
});

it('ignored movie filtering is isolated from other users and series preferences', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createMovieCategory('movie-action', 'Action');
    createMovieCategory('movie-comedy', 'Comedy');
    createSeriesCategory('series-drama', 'Drama');

    seedPersonalizedMovieRecord(1501, 'Movie Action', 'movie-action');
    seedPersonalizedMovieRecord(1502, 'Movie Comedy', 'movie-comedy');

    updateMoviePreferences($otherUser, [
        'pinned_ids' => [],
        'visible_ids' => ['movie-comedy'],
        'hidden_ids' => [],
        'ignored_ids' => ['movie-action'],
    ]);

    updateSeriesPreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => [],
        'hidden_ids' => [],
        'ignored_ids' => ['series-drama'],
    ]);

    $response = movieBrowseResponse($user);

    $response->assertOk();
    $response->assertJsonPath('props.movies.total', 2);

    expect(movieBrowseNames($response))->toBe(['Movie Comedy', 'Movie Action']);
});

it('resets only movie preferences and restores default order after new categories appear', function (): void {
    $user = User::factory()->create();

    createMovieCategory('movie-action', 'Action');
    createMovieCategory('movie-comedy', 'Comedy');
    createMovieCategory('movie-drama', 'Drama');
    createSeriesCategory('series-crime', 'Crime');
    createSeriesCategory('series-family', 'Family');

    seedPersonalizedMovieRecord(1201, 'Movie Action', 'movie-action');
    seedPersonalizedMovieRecord(1202, 'Movie Comedy', 'movie-comedy');
    seedPersonalizedMovieRecord(1203, 'Movie Drama', 'movie-drama');

    updateMoviePreferences($user, [
        'pinned_ids' => ['movie-comedy'],
        'visible_ids' => ['movie-comedy', 'movie-action'],
        'hidden_ids' => ['movie-drama'],
    ]);

    updateSeriesPreferences($user, [
        'pinned_ids' => [],
        'visible_ids' => ['series-crime'],
        'hidden_ids' => ['series-family'],
    ]);

    createMovieCategory('movie-zulu', 'Zulu');
    seedPersonalizedMovieRecord(1204, 'Movie Zulu', 'movie-zulu');

    $beforeReset = movieBrowseResponse($user);

    $beforeReset->assertOk();

    expect(movieVisibleIds($beforeReset))->toBe(['all-categories', 'movie-comedy', 'movie-action', 'movie-zulu', Category::UNCATEGORIZED_VOD_PROVIDER_ID]);
    expect(movieHiddenIds($beforeReset))->toBe(['movie-drama']);

    test()->actingAs($user)
        ->from(route('movies'))
        ->delete(route('category-preferences.reset', ['mediaType' => MediaType::Movie->value]))
        ->assertRedirect(route('movies'))
        ->assertSessionHasNoErrors();

    $moviesAfterReset = movieBrowseResponse($user);
    $seriesAfterReset = seriesBrowseResponseForMovieSuite($user);

    $moviesAfterReset->assertOk();
    $moviesAfterReset->assertJsonPath('props.categories.canReset', false);
    $moviesAfterReset->assertJsonPath('props.categories.selectedCategoryIsHidden', false);

    expect(movieVisibleIds($moviesAfterReset))->toBe(['all-categories', 'movie-action', 'movie-comedy', 'movie-drama', 'movie-zulu', Category::UNCATEGORIZED_VOD_PROVIDER_ID]);
    expect(movieHiddenIds($moviesAfterReset))->toBe([]);

    $seriesAfterReset->assertOk();
    $seriesAfterReset->assertJsonPath('props.categories.canReset', true);

    expect(collect($seriesAfterReset->json('props.categories.hiddenItems'))->pluck('id')->all())->toBe(['series-family']);
});

function movieBrowseResponse(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(personalizedMovieInertiaHeaders())
        ->get(route('movies', $query));
}

function seriesBrowseResponseForMovieSuite(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(personalizedMovieInertiaHeaders())
        ->get(route('series', $query));
}

function updateMoviePreferences(User $user, array $payload): void
{
    test()->actingAs($user)
        ->from(route('movies'))
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Movie->value]), $payload)
        ->assertRedirect(route('movies'))
        ->assertSessionHasNoErrors();
}

function updateSeriesPreferences(User $user, array $payload): void
{
    test()->actingAs($user)
        ->from(route('series'))
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Series->value]), $payload)
        ->assertRedirect(route('series'))
        ->assertSessionHasNoErrors();
}

function createMovieCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);
}

function createSeriesCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);
}

function seedPersonalizedMovieRecord(int $streamId, string $name, ?string $categoryId): void
{
    VodStream::withoutSyncingToSearch(static function () use ($streamId, $name, $categoryId): void {
        VodStream::unguarded(static function () use ($streamId, $name, $categoryId): void {
            VodStream::query()->create([
                'stream_id' => $streamId,
                'num' => $streamId,
                'name' => $name,
                'stream_type' => 'movie',
                'added' => now()->toIso8601String(),
                'category_id' => $categoryId,
                'container_extension' => 'mp4',
            ]);
        });
    });
}

function movieVisibleIds(TestResponse $response): array
{
    return collect($response->json('props.categories.visibleItems'))->pluck('id')->all();
}

function movieHiddenIds(TestResponse $response): array
{
    return collect($response->json('props.categories.hiddenItems'))->pluck('id')->all();
}

function movieBrowseNames(TestResponse $response): array
{
    return collect($response->json('props.movies.data'))->pluck('name')->all();
}

function personalizedMovieInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
