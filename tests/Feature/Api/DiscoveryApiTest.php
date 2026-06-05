<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('returns featured media through the discover endpoint', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createDiscoveryMovie(['stream_id' => 10, 'name' => 'Newest Movie']);
    createDiscoverySeries(['series_id' => 20, 'name' => 'Newest Series']);

    $this->withToken($token)
        ->getJson('/api/v1/discover', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'discover')
        ->assertJsonPath('data.id', 'featured')
        ->assertJsonPath('data.attributes.movies.data.0.type', 'movies')
        ->assertJsonPath('data.attributes.movies.data.0.attributes.name', 'Newest Movie')
        ->assertJsonPath('data.attributes.series.data.0.type', 'series')
        ->assertJsonPath('data.attributes.series.data.0.attributes.name', 'Newest Series');
});

it('lists categories for a media type while respecting hidden and ignored preferences', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createApiCategory('movie-visible', 'Visible', inVod: true);
    createApiCategory('movie-hidden', 'Hidden', inVod: true);
    createApiCategory('series-only', 'Series Only', inSeries: true);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Movie,
        'category_provider_id' => 'movie-hidden',
        'pin_rank' => null,
        'sort_order' => 1,
        'is_hidden' => true,
        'is_ignored' => false,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/categories?for=movie', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.0.type', 'categories')
        ->assertJsonFragment(['id' => 'movie-visible'])
        ->assertJsonFragment(['name' => 'Visible'])
        ->assertJsonMissing(['id' => 'movie-hidden'])
        ->assertJsonMissing(['id' => 'series-only']);
});

it('lists unfiltered categories while respecting movie and series hidden and ignored preferences', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createApiCategory('movie-visible', 'Movie Visible', inVod: true);
    createApiCategory('series-visible', 'Series Visible', inSeries: true);
    createApiCategory('movie-hidden', 'Movie Hidden', inVod: true);
    createApiCategory('series-ignored', 'Series Ignored', inSeries: true);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Movie,
        'category_provider_id' => 'movie-hidden',
        'pin_rank' => null,
        'sort_order' => 1,
        'is_hidden' => true,
        'is_ignored' => false,
    ]);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Series,
        'category_provider_id' => 'series-ignored',
        'pin_rank' => null,
        'sort_order' => 1,
        'is_hidden' => false,
        'is_ignored' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/categories', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonFragment(['id' => 'movie-visible'])
        ->assertJsonFragment(['id' => 'series-visible'])
        ->assertJsonMissing(['id' => 'movie-hidden'])
        ->assertJsonMissing(['id' => 'series-ignored']);
});

it('does not expose dual-use categories in unfiltered lists when hidden for one media type', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createApiCategory('dual-hidden', 'Dual Hidden', inVod: true, inSeries: true);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Movie,
        'category_provider_id' => 'dual-hidden',
        'pin_rank' => null,
        'sort_order' => 1,
        'is_hidden' => true,
        'is_ignored' => false,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/categories', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonMissing(['id' => 'dual-hidden']);
});

it('returns category details by provider id', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createApiCategory('movie-action', 'Action', inVod: true);

    $this->withToken($token)
        ->getJson('/api/v1/categories/movie-action', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.type', 'categories')
        ->assertJsonPath('data.id', 'movie-action')
        ->assertJsonPath('data.attributes.name', 'Action');
});

it('does not expose model class names for missing api resources in production', function (): void {
    config(['app.debug' => false]);

    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/categories/missing-category', ['Accept' => 'application/vnd.api+json'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '404')
        ->assertJsonPath('errors.0.detail', 'Not Found')
        ->assertJsonMissing(['detail' => 'No query results for model [App\\Models\\Category].']);
});

it('formats search validation errors as json api errors', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/search', ['page' => ['size' => 100]], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '422');
});

it('returns search results as json api resource attributes', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createDiscoveryMovie(['stream_id' => 30, 'name' => 'Searchable Movie']);

    $this->withToken($token)
        ->postJson('/api/v1/search', [
            'q' => 'Searchable',
            'media_type' => MediaType::Movie->value,
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'search-results')
        ->assertJsonPath('data.attributes.movies.data.0.type', 'movies')
        ->assertJsonPath('data.attributes.movies.data.0.attributes.name', 'Searchable Movie')
        ->assertJsonPath('data.attributes.series.data', []);
});

it('requires the read ability for lightweight search', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['server-download'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/search/lightweight', ['q' => 'Searchable'], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '403');
});

it('returns lightweight search results as json api resource attributes', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createDiscoveryMovie(['stream_id' => 31, 'name' => 'Lightweight Movie']);

    $this->withToken($token)
        ->postJson('/api/v1/search/lightweight', [
            'q' => 'Lightweight',
            'media_type' => MediaType::Movie->value,
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'search-results')
        ->assertJsonPath('data.attributes.movies.data.0.type', 'movies')
        ->assertJsonPath('data.attributes.movies.data.0.attributes.name', 'Lightweight Movie')
        ->assertJsonPath('data.attributes.series.data', []);
});

function createApiCategory(string $providerId, string $name, bool $inVod = false, bool $inSeries = false): Category
{
    return Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => $inVod,
        'in_series' => $inSeries,
        'is_system' => false,
    ]);
}

function createDiscoveryMovie(array $attributes = []): VodStream
{
    /** @var VodStream $movie */
    $movie = VodStream::withoutSyncingToSearch(static function () use ($attributes): VodStream {
        $movie = new VodStream;

        $movie->forceFill([
            'stream_id' => $attributes['stream_id'] ?? 1,
            'num' => $attributes['num'] ?? 1,
            'name' => $attributes['name'] ?? 'Movie',
            'stream_type' => 'movie',
            'stream_icon' => 'https://example.test/poster.jpg',
            'rating' => 'PG-13',
            'rating_5based' => 4.5,
            'added' => Carbon::parse('2026-01-01'),
            'is_adult' => false,
            'category_id' => 'movie-visible',
            'container_extension' => 'mp4',
            'custom_sid' => null,
            'direct_source' => null,
        ])->save();

        return $movie;
    });

    return $movie;
}

function createDiscoverySeries(array $attributes = []): Series
{
    /** @var Series $series */
    $series = Series::withoutSyncingToSearch(static function () use ($attributes): Series {
        $series = new Series;

        $series->forceFill([
            'num' => $attributes['num'] ?? 1,
            'series_id' => $attributes['series_id'] ?? 1,
            'name' => $attributes['name'] ?? 'Series',
            'cover' => 'https://example.test/cover.jpg',
            'plot' => 'Plot',
            'cast' => 'Cast',
            'director' => 'Director',
            'genre' => 'Drama',
            'backdrop_path' => [],
            'releaseDate' => '2026-01-01',
            'last_modified' => Carbon::parse('2026-01-01'),
            'category_id' => 'series-visible',
            'rating' => 4.0,
            'rating_5based' => 4.0,
        ])->save();

        return $series;
    });

    return $series;
}
