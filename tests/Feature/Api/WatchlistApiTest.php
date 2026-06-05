<?php

declare(strict_types=1);

use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists watchlist entries with polymorphic json api relationships', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    $movie = apiWatchlistCreateMovie(['stream_id' => 10, 'name' => 'Watch Movie']);
    $series = apiWatchlistCreateSeries(['series_id' => 20, 'name' => 'Watch Series']);

    $movieItem = $user->watchlists()->create(['watchable_type' => VodStream::class, 'watchable_id' => $movie->stream_id]);
    $seriesItem = $user->watchlists()->create(['watchable_type' => Series::class, 'watchable_id' => $series->series_id]);

    $this->withToken($token)
        ->getJson('/api/v1/watchlist', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.0.type', 'watchlist-items')
        ->assertJsonPath('data.0.id', (string) $seriesItem->id)
        ->assertJsonPath('data.0.relationships.watchable.data.type', 'series')
        ->assertJsonPath('data.0.relationships.watchable.data.id', '20')
        ->assertJsonPath('data.1.id', (string) $movieItem->id)
        ->assertJsonPath('data.1.relationships.watchable.data.type', 'movies')
        ->assertJsonPath('data.1.relationships.watchable.data.id', '10');

    $this->withToken($token)
        ->getJson('/api/v1/watchlist?filter=movies', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.*.id', [(string) $movieItem->id]);

    $this->withToken($token)
        ->getJson('/api/v1/watchlist?filter=series', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.*.id', [(string) $seriesItem->id]);
});

it('adds and removes watchlist entries through collection endpoints', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    apiWatchlistCreateMovie(['stream_id' => 30, 'name' => 'Post Movie']);

    $response = $this->withToken($token)
        ->postJson('/api/v1/watchlist', ['media_type' => 'movie', 'media_id' => 30], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'watchlist-items')
        ->assertJsonPath('data.attributes.media_type', 'movie')
        ->assertJsonPath('data.relationships.watchable.data.type', 'movies')
        ->assertJsonPath('data.relationships.watchable.data.id', '30');

    $watchlistId = (int) $response->json('data.id');
    expect($user->fresh()->watchlists()->count())->toBe(1);

    $this->withToken($token)
        ->deleteJson("/api/v1/watchlist/{$watchlistId}", [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.id', (string) $watchlistId);

    expect(Watchlist::query()->whereKey($watchlistId)->exists())->toBeFalse();
});

it('validates watchlist store payloads and scopes deletion to the owner', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    $otherUser = User::factory()->create();
    $movie = apiWatchlistCreateMovie(['stream_id' => 40, 'name' => 'Other Movie']);
    $otherItem = $otherUser->watchlists()->create(['watchable_type' => VodStream::class, 'watchable_id' => $movie->stream_id]);

    $this->withToken($token)
        ->postJson('/api/v1/watchlist', ['media_type' => 'episode', 'media_id' => 40], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.source.parameter', 'media_type');

    $this->withToken($token)
        ->deleteJson("/api/v1/watchlist/{$otherItem->id}", [], ['Accept' => 'application/vnd.api+json'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/vnd.api+json');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function apiWatchlistCreateMovie(array $attributes = []): VodStream
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
            'added' => now(),
            'is_adult' => false,
            'category_id' => 'action',
            'container_extension' => 'mp4',
        ])->save();

        return $movie;
    });

    return $movie;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function apiWatchlistCreateSeries(array $attributes = []): Series
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
