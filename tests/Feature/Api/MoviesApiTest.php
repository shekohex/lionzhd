<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('lists movies as paginated json api resources', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['movies:read'])->plainTextToken;

    createMovie(['stream_id' => 10, 'num' => 1, 'name' => 'First Movie']);
    createMovie(['stream_id' => 20, 'num' => 2, 'name' => 'Second Movie']);

    $this->withToken($token)
        ->getJson('/api/v1/movies?page[size]=1', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.0.type', 'movies')
        ->assertJsonPath('data.0.id', '10')
        ->assertJsonPath('data.0.attributes.name', 'First Movie')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonStructure([
            'data' => [
                '*' => ['type', 'id', 'attributes'],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta',
        ]);
});

it('formats movie list validation errors as json api errors', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['movies:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/movies?page[size]=101', ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '422')
        ->assertJsonPath('errors.0.source.parameter', 'page.size');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function createMovie(array $attributes = []): VodStream
{
    /** @var VodStream $movie */
    $movie = VodStream::withoutSyncingToSearch(static function () use ($attributes): VodStream {
        $movie = new VodStream;

        $movie->forceFill([
            'stream_id' => $attributes['stream_id'] ?? 1,
            'num' => $attributes['num'] ?? 1,
            'name' => $attributes['name'] ?? 'Movie',
            'stream_type' => $attributes['stream_type'] ?? 'movie',
            'stream_icon' => $attributes['stream_icon'] ?? 'https://example.test/poster.jpg',
            'rating' => $attributes['rating'] ?? 'PG-13',
            'rating_5based' => $attributes['rating_5based'] ?? 4.5,
            'added' => $attributes['added'] ?? Carbon::parse('2026-01-01'),
            'is_adult' => $attributes['is_adult'] ?? false,
            'category_id' => $attributes['category_id'] ?? 'action',
            'container_extension' => $attributes['container_extension'] ?? 'mp4',
            'custom_sid' => $attributes['custom_sid'] ?? null,
            'direct_source' => $attributes['direct_source'] ?? null,
        ])->save();

        return $movie;
    });

    return $movie;
}
