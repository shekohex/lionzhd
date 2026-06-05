<?php

declare(strict_types=1);

use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\AddUriRequest;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Aria2Config;
use App\Models\Category;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('lists series as paginated json api resources with category filtering', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    apiCreateSeriesCategory('drama');
    apiCreateSeriesCategory('hidden');
    apiCreateSeriesCategory('ignored');
    apiCreateSeriesCategory(Category::UNCATEGORIZED_SERIES_PROVIDER_ID, 'Uncategorized');

    apiCreateSeries(['series_id' => 10, 'name' => 'Drama Series', 'category_id' => 'drama']);
    apiCreateSeries(['series_id' => 20, 'name' => 'Hidden Series', 'category_id' => 'hidden']);
    apiCreateSeries(['series_id' => 30, 'name' => 'Ignored Series', 'category_id' => 'ignored']);
    apiCreateSeries(['series_id' => 40, 'name' => 'Null Series', 'category_id' => null]);
    apiCreateSeries(['series_id' => 50, 'name' => 'Blank Series', 'category_id' => '']);
    apiCreateSeries(['series_id' => 60, 'name' => 'System Uncategorized Series', 'category_id' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID]);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => 'series',
        'category_provider_id' => 'hidden',
        'sort_order' => 0,
        'is_hidden' => true,
        'is_ignored' => false,
    ]);
    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => 'series',
        'category_provider_id' => 'ignored',
        'sort_order' => 1,
        'is_hidden' => false,
        'is_ignored' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/series?page[number]=2&page[size]=2', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.*.id', ['50', '60'])
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2);

    $this->withToken($token)
        ->getJson('/api/v1/series?category=hidden', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.*.id', ['20']);

    $this->withToken($token)
        ->getJson('/api/v1/series?category=ignored', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->withToken($token)
        ->getJson('/api/v1/series?category='.Category::UNCATEGORIZED_SERIES_PROVIDER_ID, ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.*.id', ['40', '50', '60']);
});

it('shows series details and includes episodes when requested', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    apiCreateSeries(['series_id' => 70, 'name' => 'Episode Series']);
    apiBindXtreamSeriesInfo(70, 'Episode Series');

    $response = $this->withToken($token)
        ->getJson('/api/v1/series/70?include=episodes', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'series')
        ->assertJsonPath('data.id', '70')
        ->assertJsonPath('data.attributes.seasons', ['1']);

    $episodes = collect($response->json('data.attributes.episodes'))->flatten(1)->all();

    expect($episodes[0]['id'])->toBe('701');

    $this->withToken($token)
        ->getJson('/api/v1/series/70?include=vod-info', ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.source.parameter', 'include');
});

it('adds and removes a series from the authenticated users watchlist', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    apiCreateSeries(['series_id' => 80, 'name' => 'Watchlist Series']);

    $this->withToken($token)
        ->postJson('/api/v1/series/80/watchlist', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'series')
        ->assertJsonPath('data.id', '80');

    expect($user->inMyWatchlist(80, Series::class))->toBeTrue();

    $this->withToken($token)
        ->deleteJson('/api/v1/series/80/watchlist', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'series')
        ->assertJsonPath('data.id', '80');

    expect($user->fresh()->inMyWatchlist(80, Series::class))->toBeFalse();
});

it('queues a single series episode download with token ability and gate enforcement', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['server-download'])->plainTextToken;
    apiCreateSeries(['series_id' => 90, 'name' => 'Download Series']);
    apiBindXtreamSeriesInfo(90, 'Download Series');
    $aria2Mock = apiBindSeriesAria2AddUri('series-episode-gid');

    $this->withToken($token)
        ->postJson('/api/v1/series/90/seasons/1/episodes/0/download', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'download-requests')
        ->assertJsonPath('data.attributes.gid', 'series-episode-gid')
        ->assertJsonPath('data.attributes.episode_id', '901');

    expect(MediaDownloadRef::query()->where('gid', 'series-episode-gid')->exists())->toBeTrue();
    $aria2Mock->assertSent(AddUriRequest::class);

    Sanctum::actingAs(User::factory()->memberExternal()->create(), ['server-download']);

    $this->postJson('/api/v1/series/90/seasons/1/episodes/1/download', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.detail', 'External accounts cannot use server downloads. Use Direct Download instead.');
});

it('queues selected series episodes as a batch download', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['server-download'])->plainTextToken;
    apiCreateSeries(['series_id' => 91, 'name' => 'Batch Series']);
    apiBindXtreamSeriesInfo(91, 'Batch Series');
    $aria2Mock = apiBindAria2Batch(['batch-gid-1', 'batch-gid-2']);

    $this->withToken($token)
        ->postJson('/api/v1/series/91/download', [
            'episodes' => [
                ['season' => 1, 'episode' => 0],
                ['season' => 1, 'episode' => 1],
            ],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'download-requests')
        ->assertJsonPath('data.attributes.count', 2)
        ->assertJsonPath('data.attributes.gids', ['batch-gid-1', 'batch-gid-2']);

    expect(MediaDownloadRef::query()->whereIn('gid', ['batch-gid-1', 'batch-gid-2'])->count())->toBe(2);
    $aria2Mock->assertSent(JsonRpcBatchRequest::class);
});

it('returns direct links for series episodes including direct txt batches', function (): void {
    Config::set('features.direct_download_links', true);
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    apiCreateSeries(['series_id' => 92, 'name' => 'Direct Series']);
    apiBindXtreamSeriesInfo(92, 'Direct Series');

    $this->withToken($token)
        ->getJson('/api/v1/series/92/seasons/1/episodes/0/direct', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'direct-links')
        ->assertJsonPath('data.attributes.episode_id', '921')
        ->assertJsonStructure(['data' => ['attributes' => ['url']]]);

    $response = $this->withToken($token)
        ->postJson('/api/v1/series/92/direct.txt', [
            'episodes' => [
                ['season' => 1, 'episode' => 0],
                ['season' => 1, 'episode' => 1],
            ],
        ], ['Accept' => 'text/plain'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect(mb_substr_count((string) $response->getContent(), '/dl/'))->toBe(2);
});

it('clears series cache only for users passing both admin token ability and admin gate', function (): void {
    $member = User::factory()->memberInternal()->create();
    $memberToken = $member->createToken('external-api', ['admin'])->plainTextToken;
    $admin = User::factory()->admin()->create();
    apiCreateSeries(['series_id' => 100, 'name' => 'Cache Series']);
    $xtreamMock = apiBindXtreamSeriesInfo(100, 'Cache Series');

    $this->withToken($memberToken)
        ->deleteJson('/api/v1/series/100/cache', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.detail', 'Admin-only');

    $xtreamMock->assertNotSent(GetSeriesInfoRequest::class);

    Sanctum::actingAs($admin, ['admin']);

    $this->flushHeaders()
        ->deleteJson('/api/v1/series/100/cache', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'cache-invalidations')
        ->assertJsonPath('data.id', 'series-cache:100')
        ->assertJsonPath('data.attributes.status', 'invalidated');

    $xtreamMock->assertSent(GetSeriesInfoRequest::class);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function apiCreateSeries(array $attributes = []): Series
{
    /** @var Series $series */
    $series = Series::withoutSyncingToSearch(static function () use ($attributes): Series {
        $series = new Series;

        $series->forceFill([
            'series_id' => $attributes['series_id'] ?? 1,
            'num' => $attributes['num'] ?? ($attributes['series_id'] ?? 1),
            'name' => $attributes['name'] ?? 'Series',
            'cover' => $attributes['cover'] ?? 'https://example.test/cover.jpg',
            'plot' => $attributes['plot'] ?? 'Plot',
            'cast' => $attributes['cast'] ?? 'Cast',
            'director' => $attributes['director'] ?? 'Director',
            'genre' => $attributes['genre'] ?? 'Drama',
            'releaseDate' => $attributes['releaseDate'] ?? '2026-01-01',
            'last_modified' => $attributes['last_modified'] ?? now(),
            'rating' => $attributes['rating'] ?? 8.0,
            'rating_5based' => $attributes['rating_5based'] ?? 4.0,
            'backdrop_path' => $attributes['backdrop_path'] ?? ['https://example.test/backdrop.jpg'],
            'category_id' => array_key_exists('category_id', $attributes) ? $attributes['category_id'] : 'drama',
        ])->save();

        return $series;
    });

    return $series;
}

function apiCreateSeriesCategory(string $providerId, ?string $name = null): Category
{
    return Category::query()->updateOrCreate(
        ['provider_id' => $providerId],
        [
            'name' => $name ?? ucfirst($providerId),
            'in_vod' => false,
            'in_series' => true,
            'is_system' => $providerId === Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
        ]
    );
}

function apiBindXtreamSeriesInfo(int $seriesId, string $name): MockClient
{
    $mockClient = new MockClient([
        GetSeriesInfoRequest::class => MockResponse::make(apiSeriesInfoPayload($seriesId, $name), 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

function apiBindSeriesAria2AddUri(string $gid): MockClient
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
 * @param  list<string>  $gids
 */
function apiBindAria2Batch(array $gids): MockClient
{
    $mockClient = new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make(array_map(
            static fn (string $gid): array => ['jsonrpc' => '2.0', 'id' => $gid, 'result' => $gid],
            $gids,
        )),
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
function apiSeriesInfoPayload(int $seriesId, string $name): array
{
    return [
        'info' => [
            'name' => $name,
            'cover' => 'https://example.test/cover.jpg',
            'plot' => 'Plot',
            'cast' => 'Cast',
            'director' => 'Director',
            'genre' => 'Drama',
            'releaseDate' => '2026-01-01',
            'last_modified' => '2026-01-01 00:00:00',
            'rating' => '8.0',
            'rating_5based' => 4.0,
            'backdrop_path' => ['https://example.test/backdrop.jpg'],
            'youtube_trailer' => '',
            'episode_run_time' => '00:45:00',
            'category_id' => 'drama',
        ],
        'seasons' => ['1'],
        'episodes' => [
            '1' => [
                apiEpisodePayload((string) ($seriesId * 10 + 1), 1, 'Episode 1'),
                apiEpisodePayload((string) ($seriesId * 10 + 2), 2, 'Episode 2'),
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function apiEpisodePayload(string $id, int $episodeNumber, string $title): array
{
    return [
        'id' => $id,
        'episode_num' => $episodeNumber,
        'title' => $title,
        'container_extension' => 'mkv',
        'custom_sid' => 'sid-'.$id,
        'added' => '2026-01-01 00:00:00',
        'season' => 1,
        'direct_source' => '',
        'info' => [
            'duration_secs' => 2700,
            'duration' => '00:45:00',
            'bitrate' => 1000,
            'video' => [],
            'audio' => [],
        ],
    ];
}
