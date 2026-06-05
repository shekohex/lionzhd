<?php

declare(strict_types=1);

use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\ForceRemoveRequest;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Integrations\Aria2\Requests\PauseRequest;
use App\Http\Integrations\Aria2\Requests\RemoveDownloadResultRequest;
use App\Http\Integrations\Aria2\Requests\TellStatusRequest;
use App\Http\Integrations\Aria2\Requests\UnPauseRequest;
use App\Models\Aria2Config;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('lists downloads with live aria2 status and admin owner filters', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->memberInternal()->create();
    $otherOwner = User::factory()->memberInternal()->create();
    $movie = apiDownloadsCreateMovie(['stream_id' => 10, 'name' => 'Download Movie']);
    $download = MediaDownloadRef::fromVodStream('gid-1', $movie, $owner);
    $download->save();
    MediaDownloadRef::fromVodStream('gid-2', $movie, $otherOwner)->save();
    apiDownloadsBindAria2(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            ['jsonrpc' => '2.0', 'id' => 'gid-1', 'result' => apiDownloadsStatus('gid-1', 'active')],
        ]),
    ]));

    $token = $admin->createToken('external-api', ['read'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson("/api/v1/downloads?owners={$owner->id}", ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.*.id', [(string) $download->id])
        ->assertJsonPath('data.0.type', 'downloads')
        ->assertJsonPath('data.0.attributes.gid', 'gid-1')
        ->assertJsonPath('data.0.attributes.downloadStatus.status', 'active');

    expect(collect($response->json('meta.owner_options'))->pluck('id')->all())->toContain($owner->id);
});

it('updates downloads with pause action and enforces download operation gates', function (): void {
    $user = User::factory()->memberInternal()->create();
    $movie = apiDownloadsCreateMovie(['stream_id' => 20, 'name' => 'Pause Movie']);
    $download = MediaDownloadRef::fromVodStream('gid-pause', $movie, $user);
    $download->save();
    apiDownloadsBindAria2(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            ['jsonrpc' => '2.0', 'id' => 'gid-pause', 'result' => apiDownloadsStatus('gid-pause', 'active')],
        ]),
        PauseRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-pause', 'result' => 'OK']),
    ]));

    Sanctum::actingAs(User::factory()->memberExternal()->create(), ['download-operations']);

    $this->patchJson("/api/v1/downloads/{$download->id}", ['action' => 'pause'], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.detail', 'External accounts cannot perform download operations. Use Direct Download instead.');

    Sanctum::actingAs($user, ['download-operations']);

    $this->patchJson("/api/v1/downloads/{$download->id}", ['action' => 'pause'], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'downloads')
        ->assertJsonPath('data.attributes.desired_paused', true);
});

it('cancels downloads through delete and validates update actions', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['download-operations'])->plainTextToken;
    $movie = apiDownloadsCreateMovie(['stream_id' => 30, 'name' => 'Cancel Movie']);
    $download = MediaDownloadRef::fromVodStream('gid-cancel', $movie, $user);
    $download->save();
    apiDownloadsBindAria2(new MockClient([
        TellStatusRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-cancel', 'result' => apiDownloadsStatus('gid-cancel', 'active')]),
        ForceRemoveRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-cancel', 'result' => 'OK']),
    ]));

    $this->withToken($token)
        ->patchJson("/api/v1/downloads/{$download->id}", ['action' => 'unsupported'], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.source.parameter', 'action');

    $response = $this->withToken($token)
        ->deleteJson("/api/v1/downloads/{$download->id}", [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json');

    expect($response->json('data.attributes.canceled_at'))->toBeString();

    expect($download->fresh()?->canceled_at)->not->toBeNull();
});

it('cancels downloads through delete without removing partial files by default', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['download-operations'])->plainTextToken;
    $movie = apiDownloadsCreateMovie(['stream_id' => 31, 'name' => 'Default Cancel Movie']);
    $download = MediaDownloadRef::fromVodStream('gid-cancel-keep-partial', $movie, $user);
    $download->save();

    $root = apiDownloadsCreateTempDir('api-delete-keep-root');
    config()->set('services.aria2.download_root', $root);

    $file = $root.'/partial.mp4';
    File::put($file, 'partial');

    apiDownloadsBindAria2(new MockClient([
        TellStatusRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-cancel-keep-partial', 'result' => apiDownloadsStatusWithFiles('gid-cancel-keep-partial', $root, [$file])]),
        ForceRemoveRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-cancel-keep-partial', 'result' => 'OK']),
    ]));

    $this->withToken($token)
        ->deleteJson("/api/v1/downloads/{$download->id}", [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.cancel_delete_partial', false);

    $download->refresh();

    expect($download->canceled_at)->not->toBeNull();
    expect($download->cancel_delete_partial)->toBeFalse();
    expect($download->download_files)->toBe([$file]);
    expect(File::exists($file))->toBeTrue();

    File::deleteDirectory($root);
});

it('cancels downloads through delete and removes partial files when requested', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['download-operations'])->plainTextToken;
    $movie = apiDownloadsCreateMovie(['stream_id' => 32, 'name' => 'Delete Partial Cancel Movie']);
    $download = MediaDownloadRef::fromVodStream('gid-cancel-delete-partial', $movie, $user);
    $download->save();

    $root = apiDownloadsCreateTempDir('api-delete-partial-root');
    config()->set('services.aria2.download_root', $root);

    $file = $root.'/partial.mp4';
    $controlFile = $file.'.aria2';
    File::put($file, 'partial');
    File::put($controlFile, 'control');

    apiDownloadsBindAria2(new MockClient([
        TellStatusRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-cancel-delete-partial', 'result' => apiDownloadsStatusWithFiles('gid-cancel-delete-partial', $root, [$file])]),
        ForceRemoveRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-cancel-delete-partial', 'result' => 'OK']),
    ]));

    $this->withToken($token)
        ->deleteJson("/api/v1/downloads/{$download->id}?delete_partial=true", [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.cancel_delete_partial', true);

    $download->refresh();

    expect($download->canceled_at)->not->toBeNull();
    expect($download->cancel_delete_partial)->toBeTrue();
    expect($download->download_files)->toBe([$file]);
    expect(File::exists($file))->toBeFalse();
    expect(File::exists($controlFile))->toBeFalse();

    File::deleteDirectory($root);
});

it('supports resume and remove download patch actions', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['download-operations'])->plainTextToken;
    $movie = apiDownloadsCreateMovie(['stream_id' => 40, 'name' => 'Resume Remove Movie']);
    $paused = MediaDownloadRef::fromVodStream('gid-resume', $movie, $user);
    $paused->forceFill(['desired_paused' => true])->save();
    $complete = MediaDownloadRef::fromVodStream('gid-remove', $movie, $user);
    $complete->save();
    apiDownloadsBindAria2(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            ['jsonrpc' => '2.0', 'id' => 'gid-resume', 'result' => apiDownloadsStatus('gid-resume', 'paused')],
            ['jsonrpc' => '2.0', 'id' => 'gid-remove', 'result' => apiDownloadsStatus('gid-remove', 'complete')],
        ]),
        UnPauseRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-resume', 'result' => 'OK']),
        RemoveDownloadResultRequest::class => MockResponse::make(['jsonrpc' => '2.0', 'id' => 'gid-remove', 'result' => 'OK']),
    ]));

    $this->withToken($token)
        ->patchJson("/api/v1/downloads/{$paused->id}", ['action' => 'resume'], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.desired_paused', false);

    $this->withToken($token)
        ->patchJson("/api/v1/downloads/{$complete->id}", ['action' => 'remove'], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $complete->id);

    expect($complete->fresh())->toBeNull();
});

it('returns structured json api errors when aria2 is unavailable for patch actions', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['download-operations'])->plainTextToken;
    $movie = apiDownloadsCreateMovie(['stream_id' => 50, 'name' => 'Unavailable Movie']);
    $download = MediaDownloadRef::fromVodStream('gid-unavailable', $movie, $user);
    $download->save();
    apiDownloadsBindAria2(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make(['error' => ['code' => 1, 'message' => 'backend down']], 500),
    ]));

    $this->withToken($token)
        ->patchJson("/api/v1/downloads/{$download->id}", ['action' => 'pause'], ['Accept' => 'application/vnd.api+json'])
        ->assertStatus(503)
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '503')
        ->assertJsonPath('errors.0.detail', 'The aria2 backend is unavailable: backend down');
});

it('returns conflict when download action is not valid for the current aria2 state', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['download-operations'])->plainTextToken;
    $movie = apiDownloadsCreateMovie(['stream_id' => 60, 'name' => 'Conflict Movie']);
    $download = MediaDownloadRef::fromVodStream('gid-conflict', $movie, $user);
    $download->save();
    apiDownloadsBindAria2(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            ['jsonrpc' => '2.0', 'id' => 'gid-conflict', 'result' => apiDownloadsStatus('gid-conflict', 'paused')],
        ]),
    ]));

    $this->withToken($token)
        ->patchJson("/api/v1/downloads/{$download->id}", ['action' => 'pause'], ['Accept' => 'application/vnd.api+json'])
        ->assertConflict()
        ->assertJsonPath('errors.0.status', '409')
        ->assertJsonPath('errors.0.detail', 'You cannot pause a download in paused status.');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function apiDownloadsCreateMovie(array $attributes = []): VodStream
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

function apiDownloadsBindAria2(MockClient $mockClient): MockClient
{
    app()->bind(JsonRpcConnector::class, static function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

function apiDownloadsCreateTempDir(string $prefix): string
{
    $path = sys_get_temp_dir().'/lionzhd-'.$prefix.'-'.bin2hex(random_bytes(8));

    File::makeDirectory($path, 0755, true);

    return $path;
}

/**
 * @param  list<string>  $files
 * @return array<string, mixed>
 */
function apiDownloadsStatusWithFiles(string $gid, string $dir, array $files): array
{
    return [
        'gid' => $gid,
        'dir' => $dir,
        'files' => array_map(static fn (string $file): array => ['path' => $file], $files),
    ];
}

/**
 * @return array<string, mixed>
 */
function apiDownloadsStatus(string $gid, string $status): array
{
    return [
        'gid' => $gid,
        'status' => $status,
        'totalLength' => '100',
        'completedLength' => '50',
        'downloadSpeed' => '5',
        'errorCode' => '0',
        'errorMessage' => null,
        'dir' => '/downloads',
        'files' => [],
    ];
}
