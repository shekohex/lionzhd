<?php

declare(strict_types=1);

use App\Models\MediaDownloadRef;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Integrations\Aria2\Requests\RemoveDownloadResultRequest;
use App\Models\Aria2Config;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('features.direct_download_links', true);
});

it('forbids external members from movie server downloads', function (): void {
    $user = User::factory()->memberExternal()->create();

    DB::table('vod_streams')->insert([
        'stream_id' => 1001,
        'num' => 1001,
        'name' => 'Test Movie',
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => null,
        'rating_5based' => 0,
        'added' => now()->toDateTimeString(),
        'is_adult' => false,
        'category_id' => null,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('movies.download', ['model' => 1001]));

    $response->assertForbidden();
});

it('forbids external members from download operations', function (string $method, string $routeName): void {
    $user = User::factory()->memberExternal()->create();
    $download = MediaDownloadRef::query()->create([
        'gid' => 'external-gid-1',
        'user_id' => $user->id,
        'media_id' => 1001,
        'media_type' => VodStream::class,
        'downloadable_id' => 1001,
    ]);

    $response = $this->actingAs($user)->{$method}(route($routeName, ['model' => $download->id]));

    $response->assertForbidden();
})->with([
    ['patch', 'downloads.edit'],
    ['delete', 'downloads.destroy'],
]);

it('applies gate assertions for download ownership and role access', function (): void {
    $internal = User::factory()->memberInternal()->create();
    $external = User::factory()->memberExternal()->create();
    $admin = User::factory()->admin()->create();

    $ownDownload = MediaDownloadRef::query()->create([
        'gid' => 'gate-own-gid',
        'user_id' => $internal->id,
        'media_id' => 2001,
        'media_type' => VodStream::class,
        'downloadable_id' => 2001,
    ]);

    $otherUsersDownload = MediaDownloadRef::query()->create([
        'gid' => 'gate-other-gid',
        'user_id' => $external->id,
        'media_id' => 2002,
        'media_type' => VodStream::class,
        'downloadable_id' => 2002,
    ]);

    expect(Gate::forUser($internal)->allows('download-operations', $ownDownload))->toBeTrue();
    expect(Gate::forUser($internal)->denies('download-operations', $otherUsersDownload))->toBeTrue();
    expect(Gate::forUser($admin)->allows('download-operations', $otherUsersDownload))->toBeTrue();
    expect(Gate::forUser($internal)->allows('server-download'))->toBeTrue();
    expect(Gate::forUser($external)->denies('server-download'))->toBeTrue();
});

it('scopes downloads list payload to member-owned rows only', function (): void {
    $internal = User::factory()->memberInternal()->create();
    $otherMember = User::factory()->memberInternal()->create();

    $mockClient = new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'gid' => 'internal-owned-gid',
                    'status' => 'active',
                ],
            ],
        ]),
    ]);

    app()->bind(JsonRpcConnector::class, function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    DB::table('vod_streams')->insert([
        [
            'stream_id' => 3001,
            'num' => 3001,
            'name' => 'Internal Owned',
            'stream_type' => 'movie',
            'stream_icon' => null,
            'rating' => null,
            'rating_5based' => 0,
            'added' => now()->toDateTimeString(),
            'is_adult' => false,
            'category_id' => null,
            'container_extension' => 'mp4',
            'custom_sid' => null,
            'direct_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'stream_id' => 3002,
            'num' => 3002,
            'name' => 'Other Owned',
            'stream_type' => 'movie',
            'stream_icon' => null,
            'rating' => null,
            'rating_5based' => 0,
            'added' => now()->toDateTimeString(),
            'is_adult' => false,
            'category_id' => null,
            'container_extension' => 'mp4',
            'custom_sid' => null,
            'direct_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    MediaDownloadRef::query()->create([
        'gid' => 'internal-owned-gid',
        'user_id' => $internal->id,
        'media_id' => 3001,
        'media_type' => VodStream::class,
        'downloadable_id' => 3001,
    ]);

    MediaDownloadRef::query()->create([
        'gid' => 'other-user-gid',
        'user_id' => $otherMember->id,
        'media_id' => 3002,
        'media_type' => VodStream::class,
        'downloadable_id' => 3002,
    ]);

    Http::fake([
        '*jsonrpc' => Http::response([
            [
                'result' => [
                    'gid' => 'internal-owned-gid',
                    'status' => 'active',
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($internal)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('downloads'));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');

    $downloads = $response->json('props.downloads.data');
    $userIds = collect($downloads)->pluck('user_id')->unique()->values()->all();
    $gids = collect($downloads)->pluck('gid');

    expect($downloads)->not->toBeEmpty();
    expect($userIds)->toBe([$internal->id]);
    expect($gids->contains('other-user-gid'))->toBeFalse();
    expect($response->json('props'))->not->toHaveKey('ownerOptions');
});

it('applies admin owner filters with newest-first ordering and owner options payload', function (): void {
    $admin = User::factory()->admin()->create();
    $ownerA = User::factory()->memberInternal()->create(['name' => 'Owner A', 'email' => 'owner-a@example.test']);
    $ownerB = User::factory()->memberInternal()->create(['name' => 'Owner B', 'email' => 'owner-b@example.test']);
    $ownerC = User::factory()->memberInternal()->create(['name' => 'Owner C', 'email' => 'owner-c@example.test']);

    DB::table('vod_streams')->insert([
        [
            'stream_id' => 3101,
            'num' => 3101,
            'name' => 'Owner A Download',
            'stream_type' => 'movie',
            'stream_icon' => null,
            'rating' => null,
            'rating_5based' => 0,
            'added' => now()->toDateTimeString(),
            'is_adult' => false,
            'category_id' => null,
            'container_extension' => 'mp4',
            'custom_sid' => null,
            'direct_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'stream_id' => 3102,
            'num' => 3102,
            'name' => 'Owner B Newest',
            'stream_type' => 'movie',
            'stream_icon' => null,
            'rating' => null,
            'rating_5based' => 0,
            'added' => now()->toDateTimeString(),
            'is_adult' => false,
            'category_id' => null,
            'container_extension' => 'mp4',
            'custom_sid' => null,
            'direct_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'stream_id' => 3103,
            'num' => 3103,
            'name' => 'Owner B Older',
            'stream_type' => 'movie',
            'stream_icon' => null,
            'rating' => null,
            'rating_5based' => 0,
            'added' => now()->toDateTimeString(),
            'is_adult' => false,
            'category_id' => null,
            'container_extension' => 'mp4',
            'custom_sid' => null,
            'direct_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'stream_id' => 3104,
            'num' => 3104,
            'name' => 'Owner C Excluded',
            'stream_type' => 'movie',
            'stream_icon' => null,
            'rating' => null,
            'rating_5based' => 0,
            'added' => now()->toDateTimeString(),
            'is_adult' => false,
            'category_id' => null,
            'container_extension' => 'mp4',
            'custom_sid' => null,
            'direct_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    MediaDownloadRef::query()->forceCreate([
        'gid' => 'owner-a-mid',
        'user_id' => $ownerA->id,
        'media_id' => 3101,
        'media_type' => VodStream::class,
        'downloadable_id' => 3101,
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    MediaDownloadRef::query()->forceCreate([
        'gid' => 'owner-b-newest',
        'user_id' => $ownerB->id,
        'media_id' => 3102,
        'media_type' => VodStream::class,
        'downloadable_id' => 3102,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    MediaDownloadRef::query()->forceCreate([
        'gid' => 'owner-b-oldest',
        'user_id' => $ownerB->id,
        'media_id' => 3103,
        'media_type' => VodStream::class,
        'downloadable_id' => 3103,
        'created_at' => now()->subMinutes(3),
        'updated_at' => now()->subMinutes(3),
    ]);

    MediaDownloadRef::query()->forceCreate([
        'gid' => 'owner-c-excluded',
        'user_id' => $ownerC->id,
        'media_id' => 3104,
        'media_type' => VodStream::class,
        'downloadable_id' => 3104,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mockClient = new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'gid' => 'owner-b-newest',
                    'status' => 'active',
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'gid' => 'owner-a-mid',
                    'status' => 'active',
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'id' => 3,
                'result' => [
                    'gid' => 'owner-b-oldest',
                    'status' => 'active',
                ],
            ],
        ]),
    ]);

    app()->bind(JsonRpcConnector::class, function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('downloads', ['owners' => "{$ownerB->id},invalid,{$ownerA->id},{$ownerB->id}"]));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');

    $downloads = $response->json('props.downloads.data');
    $ownerIds = collect($downloads)->pluck('user_id')->unique()->sort()->values()->all();
    $gids = collect($downloads)->pluck('gid')->all();
    $options = $response->json('props.ownerOptions');
    $optionIds = collect($options)->pluck('id')->sort()->values()->all();

    expect($ownerIds)->toBe([$ownerA->id, $ownerB->id]);
    expect($gids)->toBe(['owner-b-newest', 'owner-a-mid', 'owner-b-oldest']);
    expect($optionIds)->toBe([$ownerA->id, $ownerB->id, $ownerC->id]);
});

it('forwards safe return_to when retrying vod downloads and ignores unsafe values', function (): void {
    $owner = User::factory()->memberInternal()->create();

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-gid-vod-safe',
        'media_id' => 3201,
        'media_type' => VodStream::class,
        'downloadable_id' => 3201,
        'user_id' => $owner->id,
    ]);

    $mockClient = new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'gid' => $download->gid,
                    'status' => 'error',
                ],
            ],
        ]),
        RemoveDownloadResultRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => 'OK',
        ]),
    ]);

    app()->bind(JsonRpcConnector::class, function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    $safeResponse = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'retry',
        'return_to' => '/downloads?owners=1,2&page=2',
    ]);

    $safeResponse->assertRedirect(route('movies.download', ['model' => 3201, 'return_to' => '/downloads?owners=1,2&page=2']));
    $this->assertDatabaseMissing('media_download_refs', ['id' => $download->id]);

    $unsafeDownload = MediaDownloadRef::query()->create([
        'gid' => 'retry-gid-vod-unsafe',
        'media_id' => 3201,
        'media_type' => VodStream::class,
        'downloadable_id' => 3201,
        'user_id' => $owner->id,
    ]);

    $unsafeResponse = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $unsafeDownload->id]), [
        'action' => 'retry',
        'return_to' => '/movies/9999',
    ]);

    $unsafeResponse->assertRedirect(route('movies.download', ['model' => 3201]));
    $this->assertDatabaseMissing('media_download_refs', ['id' => $unsafeDownload->id]);
});

it('forwards safe return_to when retrying series downloads and ignores unsafe values', function (): void {
    $owner = User::factory()->memberInternal()->create();

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-gid-series-safe',
        'media_id' => 3301,
        'media_type' => Series::class,
        'downloadable_id' => 3301,
        'season' => 1,
        'episode' => 3,
        'user_id' => $owner->id,
    ]);

    $mockClient = new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'gid' => $download->gid,
                    'status' => 'error',
                ],
            ],
        ]),
        RemoveDownloadResultRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => 'OK',
        ]),
    ]);

    app()->bind(JsonRpcConnector::class, function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    $safeResponse = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'retry',
        'return_to' => '/downloads?owners=1,2&page=2',
    ]);

    $safeResponse->assertRedirect(route('series.download.single', [
        'model' => 3301,
        'season' => 1,
        'episode' => 3,
        'return_to' => '/downloads?owners=1,2&page=2',
    ]));
    $this->assertDatabaseMissing('media_download_refs', ['id' => $download->id]);

    $unsafeDownload = MediaDownloadRef::query()->create([
        'gid' => 'retry-gid-series-unsafe',
        'media_id' => 3301,
        'media_type' => Series::class,
        'downloadable_id' => 3301,
        'season' => 1,
        'episode' => 3,
        'user_id' => $owner->id,
    ]);

    $unsafeResponse = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $unsafeDownload->id]), [
        'action' => 'retry',
        'return_to' => '/movies/9999',
    ]);

    $unsafeResponse->assertRedirect(route('series.download.single', ['model' => 3301, 'season' => 1, 'episode' => 3]));
    $this->assertDatabaseMissing('media_download_refs', ['id' => $unsafeDownload->id]);
});

it('returns not found when internal members operate on non-owned downloads', function (string $method, string $routeName): void {
    $user = User::factory()->memberInternal()->create();
    $owner = User::factory()->memberInternal()->create();
    $download = MediaDownloadRef::query()->create([
        'gid' => 'internal-gid-1',
        'media_id' => 1001,
        'media_type' => VodStream::class,
        'downloadable_id' => 1001,
        'user_id' => $owner->id,
    ]);

    $response = $this->actingAs($user)->{$method}(route($routeName, ['model' => $download->id]));

    $response->assertNotFound();
})->with([
    ['patch', 'downloads.edit'],
    ['delete', 'downloads.destroy'],
]);

it('allows signed direct download resolution and redirects', function (): void {
    $token = 'direct-token-1';
    $remoteUrl = 'https://example.com/direct.mp4';

    Cache::put("direct:link:{$token}", $remoteUrl, now()->addMinutes(30));

    $signedUrl = URL::temporarySignedRoute('direct.resolve', now()->addMinutes(30), ['token' => $token]);

    $response = $this->get($signedUrl);

    $response->assertRedirect($remoteUrl);
});
