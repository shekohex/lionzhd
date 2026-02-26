<?php

declare(strict_types=1);

namespace Tests\Feature\Downloads;

use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Aria2Config;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('hydrates download progress by gid and leaves error entries without status', function (): void {
    $admin = User::factory()->admin()->create();

    createHydrationVodStream(streamId: 5101, num: 5101, name: 'Hydration Movie A');
    createHydrationVodStream(streamId: 5102, num: 5102, name: 'Hydration Movie B');

    $downloadA = MediaDownloadRef::query()->create([
        'gid' => 'gid-progress-a',
        'media_id' => 5101,
        'media_type' => VodStream::class,
        'downloadable_id' => 5101,
        'user_id' => $admin->id,
    ]);

    $downloadB = MediaDownloadRef::query()->create([
        'gid' => 'gid-progress-b',
        'media_id' => 5102,
        'media_type' => VodStream::class,
        'downloadable_id' => 5102,
        'user_id' => $admin->id,
    ]);

    $mockClient = new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => $downloadB->gid,
                'error' => [
                    'code' => 1,
                    'message' => 'temporarily unavailable',
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'id' => $downloadA->gid,
                'result' => [
                    'gid' => $downloadA->gid,
                    'status' => 'active',
                    'totalLength' => '1000',
                    'completedLength' => '250',
                    'downloadSpeed' => '32',
                    'errorCode' => '0',
                    'errorMessage' => '',
                    'dir' => '/tmp/downloads',
                    'files' => [],
                ],
            ],
        ]),
    ]);

    app()->bind(JsonRpcConnector::class, static function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    $response = $this->actingAs($admin)
        ->withHeaders(downloadsInertiaHeaders())
        ->get(route('downloads'));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');

    $downloads = collect($response->json('props.downloads.data'))->keyBy('gid');

    expect($downloads->get($downloadA->gid)['downloadStatus'])->toMatchArray([
        'gid' => $downloadA->gid,
        'status' => 'active',
        'totalLength' => 1000,
        'completedLength' => 250,
    ]);

    expect($downloads->get($downloadB->gid)['downloadStatus'] ?? null)->toBeNull();
});

function createHydrationVodStream(int $streamId, int $num, string $name): VodStream
{
    $timestamp = now();

    DB::table('vod_streams')->insert([
        'stream_id' => $streamId,
        'num' => $num,
        'name' => $name,
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

function downloadsInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
