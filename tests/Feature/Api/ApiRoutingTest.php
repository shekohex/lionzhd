<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('requires sanctum authentication for api v1 routes', function (): void {
    $this->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '401');
});

it('returns json api content for authenticated api v1 routes', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.attributes.email', $user->email);
});

it('requires the read ability for the profile endpoint', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['server-download'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '403');
});

it('does not expose raw exception details for production api server errors', function (): void {
    config(['app.debug' => false]);

    Route::middleware('AcceptJsonApi')->get('/api/v1/__boom', static function (): never {
        throw new RuntimeException('secret database path /tmp/lionz.sqlite');
    });

    $this->getJson('/api/v1/__boom', ['Accept' => 'application/vnd.api+json'])
        ->assertInternalServerError()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '500')
        ->assertJsonPath('errors.0.detail', 'Internal Server Error')
        ->assertJsonMissing(['detail' => 'secret database path /tmp/lionz.sqlite']);
});

it('serves openapi docs json outside local environments', function (): void {
    app()->detectEnvironment(static fn (): string => 'production');

    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0');
});

it('documents movie json api resource attributes in openapi', function (): void {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('components.schemas.MovieResource.type', 'object')
        ->assertJsonPath('components.schemas.MovieResource.properties.attributes.type', 'object')
        ->assertJsonPath('components.schemas.MovieResource.properties.attributes.properties.name.type', 'string')
        ->assertJsonPath('components.schemas.MovieResource.properties.attributes.properties.stream_id.type', 'integer')
        ->assertJsonPath('components.schemas.MovieResource.properties.attributes.properties.rating_5based.type', 'number')
        ->assertJsonPath('components.schemas.MovieResource.properties.attributes.properties.is_adult.type', 'boolean')
        ->assertJsonPath('components.schemas.SeriesResource.type', 'object')
        ->assertJsonPath('components.schemas.SeriesResource.properties.attributes.properties.series_id.type', 'integer')
        ->assertJsonPath('components.schemas.DiscoverResource.properties.attributes.properties.movies.properties.data.items.properties.attributes.properties.name.type', 'string')
        ->assertJsonPath('components.schemas.SearchResultResource.properties.attributes.properties.series.properties.data.items.properties.attributes.properties.name.type', 'string');
});
