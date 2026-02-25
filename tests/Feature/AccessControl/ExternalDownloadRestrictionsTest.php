<?php

declare(strict_types=1);

use App\Models\MediaDownloadRef;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Models\Aria2Config;
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
