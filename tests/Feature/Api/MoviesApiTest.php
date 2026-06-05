<?php

declare(strict_types=1);

use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\AddUriRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Aria2Config;
use App\Models\Category;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\VodStream;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('lists movies as paginated json api resources', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    createMovie(['stream_id' => 10, 'num' => 1, 'name' => 'First Movie']);
    createMovie(['stream_id' => 20, 'num' => 2, 'name' => 'Second Movie']);

    $response = $this->withToken($token)
        ->getJson('/api/v1/movies?page[number]=2&page[size]=1', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.0.type', 'movies')
        ->assertJsonPath('data.0.id', '20')
        ->assertJsonPath('data.0.attributes.name', 'Second Movie')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonStructure([
            'data' => [
                '*' => ['type', 'id', 'attributes'],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta',
        ]);

    $previousLink = (string) $response->json('links.prev');
    parse_str((string) parse_url($previousLink, PHP_URL_QUERY), $previousQuery);

    expect($previousQuery)->toMatchArray([
        'page' => [
            'number' => '1',
            'size' => '1',
        ],
    ]);
});

it('requires the read ability for movie list routes', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['server-download'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/movies', ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '403');
});

it('shows a movie as a json api resource', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    createMovie(['stream_id' => 40, 'name' => 'Detail Movie']);

    $this->withToken($token)
        ->getJson('/api/v1/movies/40', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'movies')
        ->assertJsonPath('data.id', '40')
        ->assertJsonPath('data.attributes.name', 'Detail Movie');
});

it('filters movies by category while respecting hidden ignored and uncategorized preferences', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    apiCreateMovieCategory('action');
    apiCreateMovieCategory('hidden');
    apiCreateMovieCategory('ignored');
    apiCreateMovieCategory(Category::UNCATEGORIZED_VOD_PROVIDER_ID, 'Uncategorized');

    createMovie(['stream_id' => 10, 'name' => 'Action Movie', 'category_id' => 'action']);
    createMovie(['stream_id' => 20, 'name' => 'Hidden Movie', 'category_id' => 'hidden']);
    createMovie(['stream_id' => 30, 'name' => 'Ignored Movie', 'category_id' => 'ignored']);
    createMovie(['stream_id' => 40, 'name' => 'Null Category Movie', 'category_id' => null]);
    createMovie(['stream_id' => 50, 'name' => 'Blank Category Movie', 'category_id' => '']);
    createMovie(['stream_id' => 60, 'name' => 'System Uncategorized Movie', 'category_id' => Category::UNCATEGORIZED_VOD_PROVIDER_ID]);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => 'movie',
        'category_provider_id' => 'hidden',
        'sort_order' => 0,
        'is_hidden' => true,
        'is_ignored' => false,
    ]);
    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => 'movie',
        'category_provider_id' => 'ignored',
        'sort_order' => 1,
        'is_hidden' => false,
        'is_ignored' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/movies', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.*.id', ['10', '40', '50', '60']);

    $this->withToken($token)
        ->getJson('/api/v1/movies?category=hidden', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.*.id', ['20']);

    $this->withToken($token)
        ->getJson('/api/v1/movies?category=ignored', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->withToken($token)
        ->getJson('/api/v1/movies?category='.Category::UNCATEGORIZED_VOD_PROVIDER_ID, ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.*.id', ['40', '50', '60']);
});

it('validates unknown movie category filters', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/movies?category=missing', ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.source.parameter', 'category');
});

it('includes vod info for movie detail when requested', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    createMovie(['stream_id' => 70, 'name' => 'Extended Movie']);
    apiBindXtreamVodInfo(70, 'Extended Movie');

    $this->withToken($token)
        ->getJson('/api/v1/movies/70?include=vod-info', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'movies')
        ->assertJsonPath('data.attributes.vod_info.vodId', 70)
        ->assertJsonPath('data.attributes.vod_info.movie.name', 'Extended Movie');
});

it('adds and removes a movie from the authenticated users watchlist', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    createMovie(['stream_id' => 50, 'name' => 'Watchlist Movie']);

    $this->withToken($token)
        ->postJson('/api/v1/movies/50/watchlist', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'movies')
        ->assertJsonPath('data.id', '50');

    expect($user->inMyWatchlist(50, VodStream::class))->toBeTrue();

    $this->withToken($token)
        ->deleteJson('/api/v1/movies/50/watchlist', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'movies')
        ->assertJsonPath('data.id', '50');

    expect($user->fresh()->inMyWatchlist(50, VodStream::class))->toBeFalse();
});

it('formats movie list validation errors as json api errors', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/movies?page[size]=101', ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '422')
        ->assertJsonPath('errors.0.source.parameter', 'page.size');
});

it('queues movie downloads through the api as json api action resources', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['server-download'])->plainTextToken;
    createMovie(['stream_id' => 80, 'name' => 'Download Movie']);
    apiBindXtreamVodInfo(80, 'Download Movie');
    $aria2Mock = apiBindAria2AddUri('api-download-gid');

    $this->withToken($token)
        ->postJson('/api/v1/movies/80/download', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'download-requests')
        ->assertJsonPath('data.id', 'movie-download:80')
        ->assertJsonPath('data.attributes.gid', 'api-download-gid')
        ->assertJsonPath('data.attributes.existing', false);

    expect(MediaDownloadRef::query()->where('gid', 'api-download-gid')->exists())->toBeTrue();
    $aria2Mock->assertSent(AddUriRequest::class);
});

it('requires both server download ability and gate permission for api movie downloads', function (): void {
    $externalUser = User::factory()->memberExternal()->create();
    $wrongScopeToken = $externalUser->createToken('external-api', ['read'])->plainTextToken;
    createMovie(['stream_id' => 81, 'name' => 'Denied Download Movie']);

    $this->withToken($wrongScopeToken)
        ->postJson('/api/v1/movies/81/download', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.status', '403');

    Sanctum::actingAs($externalUser, ['server-download']);

    $this->flushHeaders()
        ->postJson('/api/v1/movies/81/download', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.detail', 'External accounts cannot use server downloads. Use Direct Download instead.');
});

it('returns signed direct movie links through the api', function (): void {
    Config::set('features.direct_download_links', true);
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    createMovie(['stream_id' => 90, 'name' => 'Direct Movie']);
    apiBindXtreamVodInfo(90, 'Direct Movie');

    $this->withToken($token)
        ->getJson('/api/v1/movies/90/direct', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'direct-links')
        ->assertJsonPath('data.id', 'movie-direct:90')
        ->assertJsonPath('data.attributes.expires_in_seconds', 14400)
        ->assertJsonStructure(['data' => ['attributes' => ['url']]]);
});

it('clears movie cache only for users passing both admin token ability and admin gate', function (): void {
    $member = User::factory()->memberInternal()->create();
    $memberToken = $member->createToken('external-api', ['admin'])->plainTextToken;
    $admin = User::factory()->admin()->create();
    createMovie(['stream_id' => 100, 'name' => 'Cache Movie']);
    $xtreamMock = apiBindXtreamVodInfo(100, 'Cache Movie');

    $this->withToken($memberToken)
        ->deleteJson('/api/v1/movies/100/cache', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.detail', 'Admin-only');

    $xtreamMock->assertNotSent(GetVodInfoRequest::class);

    Sanctum::actingAs($admin, ['admin']);

    $this->flushHeaders()
        ->deleteJson('/api/v1/movies/100/cache', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'cache-invalidations')
        ->assertJsonPath('data.id', 'movie-cache:100')
        ->assertJsonPath('data.attributes.status', 'invalidated');

    $xtreamMock->assertSent(GetVodInfoRequest::class);
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
            'category_id' => array_key_exists('category_id', $attributes) ? $attributes['category_id'] : 'action',
            'container_extension' => $attributes['container_extension'] ?? 'mp4',
            'custom_sid' => $attributes['custom_sid'] ?? null,
            'direct_source' => $attributes['direct_source'] ?? null,
        ])->save();

        return $movie;
    });

    return $movie;
}

function apiCreateMovieCategory(string $providerId, ?string $name = null): Category
{
    return Category::query()->updateOrCreate(
        ['provider_id' => $providerId],
        [
            'name' => $name ?? ucfirst($providerId),
            'in_vod' => true,
            'in_series' => false,
            'is_system' => $providerId === Category::UNCATEGORIZED_VOD_PROVIDER_ID,
        ]
    );
}

function apiBindXtreamVodInfo(int $vodId, string $name): MockClient
{
    $mockClient = new MockClient([
        GetVodInfoRequest::class => MockResponse::make(apiVodInfoPayload($vodId, $name), 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

function apiBindAria2AddUri(string $gid): MockClient
{
    $mockClient = new MockClient([
        AddUriRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $gid,
            'result' => $gid,
        ]),
    ]);

    app()->bind(JsonRpcConnector::class, static function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

/**
 * @return array<string, mixed>
 */
function apiVodInfoPayload(int $vodId, string $name): array
{
    return [
        'info' => [
            'movie_image' => 'https://example.test/movie.jpg',
            'tmdb_id' => 'tmdb-'.$vodId,
            'backdrop' => 'https://example.test/backdrop.jpg',
            'youtube_trailer' => '',
            'genre' => 'Action',
            'plot' => 'Plot',
            'cast' => 'Cast',
            'rating' => '7.5',
            'director' => 'Director',
            'releasedate' => '2026-01-01',
            'backdrop_path' => ['https://example.test/backdrop.jpg'],
            'duration_secs' => 3600,
            'duration' => '01:00:00',
            'video' => [],
            'audio' => [],
            'bitrate' => 1000,
        ],
        'movie_data' => [
            'stream_id' => $vodId,
            'name' => $name,
            'added' => '2026-01-01 00:00:00',
            'category_id' => 'action',
            'container_extension' => 'mp4',
            'custom_sid' => '',
            'direct_source' => '',
        ],
    ];
}
