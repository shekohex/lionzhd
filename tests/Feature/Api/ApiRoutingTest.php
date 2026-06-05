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

it('returns json api unauthorized errors for curl style api requests', function (): void {
    $this->get('/api/v1/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '401');

    $this->get('/api/v1/me', ['Accept' => '*/*'])
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

it('rate limits api requests per authenticated user', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    for ($i = 0; $i < 120; $i++) {
        $this->withToken($token)
            ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
            ->assertOk();
    }

    $this->withToken($token)
        ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '429');
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

it('preserves intentional production api client error details', function (): void {
    config(['app.debug' => false]);

    Route::middleware('AcceptJsonApi')->get('/api/v1/__conflict', static function (): never {
        abort(409, 'Download is already being prepared. Please try again.');
    });

    Route::middleware('AcceptJsonApi')->get('/api/v1/__forbidden', static function (): never {
        abort(403, 'External accounts cannot use server downloads. Use Direct Download instead.');
    });

    $this->getJson('/api/v1/__conflict', ['Accept' => 'application/vnd.api+json'])
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.detail', 'Download is already being prepared. Please try again.');

    $this->getJson('/api/v1/__forbidden', ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.detail', 'External accounts cannot use server downloads. Use Direct Download instead.');
});

it('redacts production model binding not found details only', function (): void {
    config(['app.debug' => false]);

    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/movies/404404', ['Accept' => 'application/vnd.api+json'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.detail', 'Not Found')
        ->assertJsonMissing(['detail' => 'No query results for model']);
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

it('documents action resources and json api error media types in openapi', function (): void {
    $response = $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('components.schemas.ApiActionResource.properties.attributes.type', 'object')
        ->assertJsonPath('components.schemas.ApiActionResource.properties.attributes.properties.gid.type', ['string', 'null'])
        ->assertJsonPath('components.schemas.JsonApiErrorDocument.properties.errors.type', 'array');

    $document = $response->json();

    expect($document['components']['responses']['AuthorizationException']['content']['application/vnd.api+json']['schema']['properties']['errors']['type'])->toBe('array')
        ->and($document['components']['responses']['ModelNotFoundException']['content']['application/vnd.api+json']['schema']['properties']['errors']['type'])->toBe('array');
});

it('documents download json api resource attributes in openapi', function (): void {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('components.schemas.MediaDownloadResource.properties.attributes.properties.gid.type', 'string')
        ->assertJsonPath('components.schemas.MediaDownloadResource.properties.attributes.properties.desired_paused.type', 'boolean')
        ->assertJsonPath('components.schemas.MediaDownloadResource.properties.attributes.properties.downloadStatus.type', ['object', 'null']);
});

it('documents movie detail include contract and vod info response schema', function (): void {
    $document = $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('components.schemas.MovieResource.properties.attributes.properties.vod_info.type', ['object', 'null'])
        ->assertJsonPath('components.schemas.MovieResource.properties.attributes.properties.vod_info.properties.vodId.type', 'integer')
        ->json();

    $parameters = collect($document['paths']['/movies/{movie}']['get']['parameters']);

    expect($parameters->pluck('name')->all())->toContain('include')
        ->not->toContain('category')
        ->not->toContain('page[number]')
        ->not->toContain('page[size]');

    expect($parameters->firstWhere('name', 'include')['schema']['enum'])->toBe(['vod-info']);
});
