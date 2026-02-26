<?php

declare(strict_types=1);

namespace Tests\Feature\Downloads;

use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\ForceRemoveRequest;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Integrations\Aria2\Requests\PauseRequest;
use App\Http\Integrations\Aria2\Requests\TellStatusRequest;
use App\Http\Integrations\Aria2\Requests\UnPauseRequest;
use App\Models\Aria2Config;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('cancels via delete route, keeps row, and persists terminal canceled state', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createVodStream(6101);
    $download = createVodDownload($owner, 6101, 'cancel-delete-route-gid');

    $root = createTempDir('cancel-delete-root');
    config()->set('services.aria2.download_root', $root);

    $file = $root.'/partial.mp4';
    File::put($file, 'partial');

    bindAria2Mock(new MockClient([
        TellStatusRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => [
                'gid' => $download->gid,
                'dir' => $root,
                'files' => [
                    ['path' => $file],
                ],
            ],
        ]),
        ForceRemoveRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
    ]));

    $response = $this->actingAs($owner)->delete(route('downloads.destroy', ['model' => $download->id]));

    $response->assertRedirect();

    $download->refresh();

    expect($download->canceled_at)->not->toBeNull();
    expect($download->cancel_delete_partial)->toBeFalse();
    expect($download->download_files)->toBe([$file]);
    expect(File::exists($file))->toBeTrue();
});

it('cancels via patch route with delete_partial and removes partial data safely', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createVodStream(6201);
    $download = createVodDownload($owner, 6201, 'cancel-patch-route-gid');

    $root = createTempDir('cancel-patch-root');
    config()->set('services.aria2.download_root', $root);

    $file = $root.'/partial.mp4';
    $controlFile = $file.'.aria2';
    File::put($file, 'partial');
    File::put($controlFile, 'control');

    bindAria2Mock(new MockClient([
        TellStatusRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => [
                'gid' => $download->gid,
                'dir' => $root,
                'files' => [
                    ['path' => $file],
                ],
            ],
        ]),
        ForceRemoveRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
    ]));

    $response = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'cancel',
        'delete_partial' => true,
    ]);

    $response->assertRedirect();

    $download->refresh();

    expect($download->canceled_at)->not->toBeNull();
    expect($download->cancel_delete_partial)->toBeTrue();
    expect(File::exists($file))->toBeFalse();
    expect(File::exists($controlFile))->toBeFalse();
});

it('persists desired_paused when pausing and clears it when resuming', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createVodStream(6301);
    $download = createVodDownload($owner, 6301, 'pause-resume-gid');

    bindAria2Mock(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => $download->gid,
                'result' => [
                    'gid' => $download->gid,
                    'status' => 'active',
                ],
            ],
        ]),
        PauseRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
    ]));

    $pauseResponse = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'pause',
    ]);

    $pauseResponse->assertRedirect();
    $download->refresh();
    expect($download->desired_paused)->toBeTrue();

    bindAria2Mock(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => $download->gid,
                'result' => [
                    'gid' => $download->gid,
                    'status' => 'paused',
                ],
            ],
        ]),
        UnPauseRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
    ]));

    $resumeResponse = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'resume',
    ]);

    $resumeResponse->assertRedirect();
    $download->refresh();
    expect($download->desired_paused)->toBeFalse();
});

it('returns an error and keeps canceled_at null when force remove fails', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createVodStream(6401);
    $download = createVodDownload($owner, 6401, 'cancel-failure-gid');

    $root = createTempDir('cancel-failure-root');
    config()->set('services.aria2.download_root', $root);

    $file = $root.'/partial.mp4';
    File::put($file, 'partial');

    bindAria2Mock(new MockClient([
        TellStatusRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => [
                'gid' => $download->gid,
                'dir' => $root,
                'files' => [
                    ['path' => $file],
                ],
            ],
        ]),
        ForceRemoveRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'error' => [
                'code' => 1,
                'message' => 'Unable to cancel download',
            ],
        ]),
    ]));

    $response = $this->actingAs($owner)->delete(route('downloads.destroy', ['model' => $download->id]));

    $response->assertSessionHasErrors(['action' => 'Unable to cancel download']);

    $download->refresh();
    expect($download->canceled_at)->toBeNull();
});

it('blocks delete partial cleanup when tellStatus dir is outside allowed root', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createVodStream(6501);
    $download = createVodDownload($owner, 6501, 'cancel-outside-root-gid');

    $allowedRoot = createTempDir('allowed-root');
    $outsideRoot = createTempDir('outside-root');
    config()->set('services.aria2.download_root', $allowedRoot);

    $outsideFile = $outsideRoot.'/outside.mp4';
    $outsideControlFile = $outsideFile.'.aria2';
    File::put($outsideFile, 'outside');
    File::put($outsideControlFile, 'outside-control');

    bindAria2Mock(new MockClient([
        TellStatusRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => [
                'gid' => $download->gid,
                'dir' => $outsideRoot,
                'files' => [
                    ['path' => $outsideFile],
                ],
            ],
        ]),
        ForceRemoveRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
    ]));

    $response = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'cancel',
        'delete_partial' => true,
    ]);

    $response->assertSessionHasErrors([
        'action' => 'Delete partial data was blocked because the download path is outside the allowed download root.',
    ]);

    $download->refresh();

    expect($download->canceled_at)->not->toBeNull();
    expect(File::exists($outsideFile))->toBeTrue();
    expect(File::exists($outsideControlFile))->toBeTrue();
});

function bindAria2Mock(MockClient $mockClient): void
{
    app()->bind(JsonRpcConnector::class, static function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });
}

function createVodDownload(User $owner, int $streamId, string $gid): MediaDownloadRef
{
    return MediaDownloadRef::query()->create([
        'gid' => $gid,
        'media_id' => $streamId,
        'media_type' => VodStream::class,
        'downloadable_id' => $streamId,
        'user_id' => $owner->id,
    ]);
}

function createVodStream(int $streamId): VodStream
{
    $timestamp = now();

    DB::table('vod_streams')->insert([
        'stream_id' => $streamId,
        'num' => $streamId,
        'name' => "Movie {$streamId}",
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => null,
        'rating_5based' => 0,
        'added' => $timestamp->toDateTimeString(),
        'is_adult' => false,
        'category_id' => null,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return VodStream::query()->findOrFail($streamId);
}

function createTempDir(string $prefix): string
{
    $directory = sys_get_temp_dir().'/lionzhd-'.$prefix.'-'.uniqid('', true);
    File::ensureDirectoryExists($directory);

    return $directory;
}
