<?php

declare(strict_types=1);

namespace Tests\Feature\Downloads;

use App\Actions\CreateDownloadOut;
use App\Actions\Downloads\ComputeRetryBackoff;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\AddUriRequest;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Integrations\Aria2\Requests\PauseRequest;
use App\Http\Integrations\Aria2\Requests\RemoveDownloadResultRequest;
use App\Http\Integrations\Aria2\Requests\TellStatusRequest;
use App\Http\Integrations\Aria2\Requests\UnPauseRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\MonitorDownloads;
use App\Jobs\RetryDownload as RetryDownloadJob;
use App\Models\Aria2Config;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('schedules retry for transient monitor failures using deterministic backoff', function (): void {
    Carbon::setTestNow('2026-02-26 12:00:00');
    Queue::fake();

    $owner = User::factory()->memberInternal()->create();
    createRetryPolicyVodStream(streamId: 7101, name: 'Retry Movie 7101');

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-monitor-transient-gid',
        'media_id' => 7101,
        'media_type' => VodStream::class,
        'downloadable_id' => 7101,
        'user_id' => $owner->id,
        'retry_attempt' => 0,
    ]);

    bindRetryPolicyAria2Mock(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => $download->gid,
                'result' => [
                    'gid' => $download->gid,
                    'status' => 'error',
                    'errorCode' => '2',
                    'errorMessage' => 'connection timeout',
                    'dir' => '/tmp/retry-policy',
                    'files' => [
                        ['path' => '/tmp/retry-policy/retry-7101.mp4'],
                    ],
                    'totalLength' => '1000',
                    'completedLength' => '100',
                    'downloadSpeed' => '0',
                ],
            ],
        ]),
    ]));

    app(MonitorDownloads::class)->handle(app(JsonRpcConnector::class));

    $download->refresh();
    $expectedBackoff = ComputeRetryBackoff::run(1);

    expect($download->retry_attempt)->toBe(1);
    expect($download->retry_next_at)->not->toBeNull();
    expect((int) now()->diffInSeconds($download->retry_next_at))->toBe($expectedBackoff);

    Queue::assertPushed(RetryDownloadJob::class, function (RetryDownloadJob $job) use ($download): bool {
        return $job->downloadRefId === $download->id
            && $job->delay instanceof \DateTimeInterface
            && Carbon::instance($job->delay)->equalTo($download->retry_next_at);
    });
});

it('does not schedule auto retry when attempts are capped at five', function (): void {
    Carbon::setTestNow('2026-02-26 12:00:00');
    Queue::fake();

    $owner = User::factory()->memberInternal()->create();
    createRetryPolicyVodStream(streamId: 7102, name: 'Retry Movie 7102');

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-monitor-cap-gid',
        'media_id' => 7102,
        'media_type' => VodStream::class,
        'downloadable_id' => 7102,
        'user_id' => $owner->id,
        'retry_attempt' => 5,
    ]);

    bindRetryPolicyAria2Mock(new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => $download->gid,
                'result' => [
                    'gid' => $download->gid,
                    'status' => 'error',
                    'errorCode' => '6',
                    'errorMessage' => 'network problem',
                    'dir' => '/tmp/retry-policy',
                    'files' => [],
                ],
            ],
        ]),
    ]));

    app(MonitorDownloads::class)->handle(app(JsonRpcConnector::class));

    $download->refresh();

    expect($download->retry_attempt)->toBe(5);
    expect($download->retry_next_at)->toBeNull();
    Queue::assertNotPushed(RetryDownloadJob::class);
});

it('blocks manual retry while cooldown is active', function (): void {
    $owner = User::factory()->memberInternal()->create();

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-cooldown-gid',
        'media_id' => 7103,
        'media_type' => VodStream::class,
        'downloadable_id' => 7103,
        'user_id' => $owner->id,
        'retry_next_at' => now()->addMinutes(3),
    ]);

    $response = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'retry',
    ]);

    $response->assertSessionHasErrors(['action' => 'Retry is temporarily unavailable while this download is cooling down.']);
});

it('enforces sticky pause by pausing active or waiting downloads and never unpauses', function (): void {
    Queue::fake();

    $owner = User::factory()->memberInternal()->create();
    createRetryPolicyVodStream(streamId: 7104, name: 'Retry Movie 7104');

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-sticky-pause-gid',
        'media_id' => 7104,
        'media_type' => VodStream::class,
        'downloadable_id' => 7104,
        'user_id' => $owner->id,
        'desired_paused' => true,
    ]);

    $aria2Mock = new MockClient([
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => $download->gid,
                'result' => [
                    'gid' => $download->gid,
                    'status' => 'active',
                    'errorCode' => '0',
                    'errorMessage' => '',
                    'dir' => '/tmp/retry-policy',
                    'files' => [],
                ],
            ],
        ]),
        PauseRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
    ]);

    bindRetryPolicyAria2Mock($aria2Mock);

    app(MonitorDownloads::class)->handle(app(JsonRpcConnector::class));

    $aria2Mock->assertSent(PauseRequest::class);
    $aria2Mock->assertNotSent(UnPauseRequest::class);
});

it('deletes partial and control files for restart-from-zero when snapshot is missing', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createRetryPolicyVodStream(streamId: 7105, name: 'Retry Movie 7105');

    $root = createRetryPolicyTempDir('restart-zero-root');
    config()->set('services.aria2.download_root', $root);

    $file = $root.'/retry-7105.mp4';
    $controlFile = $file.'.aria2';
    File::put($file, 'partial-data');
    File::put($controlFile, 'control-data');

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-restart-zero-gid',
        'media_id' => 7105,
        'media_type' => VodStream::class,
        'downloadable_id' => 7105,
        'user_id' => $owner->id,
        'download_files' => null,
    ]);

    bindRetryPolicyAria2Mock(new MockClient([
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
        RemoveDownloadResultRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
        AddUriRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => 'retry-restart-zero-add',
            'result' => 'retry-restart-zero-new-gid',
        ]),
    ]));

    bindRetryPolicyXtreamMock(new MockClient([
        GetVodInfoRequest::class => MockResponse::make(vodInfoPayload(7105, 'Retry Movie 7105')),
    ]));

    $response = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'retry',
        'restart_from_zero' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Download retried successfully.');

    expect(File::exists($file))->toBeFalse();
    expect(File::exists($controlFile))->toBeFalse();
});

it('sends resume-capable addUri options with deterministic out path on retry', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createRetryPolicyVodStream(streamId: 7106, name: 'Retry Movie 7106');

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-options-gid',
        'media_id' => 7106,
        'media_type' => VodStream::class,
        'downloadable_id' => 7106,
        'user_id' => $owner->id,
        'last_error_code' => 6,
        'last_error_message' => 'network',
    ]);

    $vodPayload = vodInfoPayload(7106, 'Retry Movie 7106');
    $expectedOut = CreateDownloadOut::run(VodInformation::fromJson(7106, $vodPayload));

    $aria2Mock = new MockClient([
        RemoveDownloadResultRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
        AddUriRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => 'retry-options-add',
            'result' => 'retry-options-new-gid',
        ]),
    ]);

    bindRetryPolicyAria2Mock($aria2Mock);
    bindRetryPolicyXtreamMock(new MockClient([
        GetVodInfoRequest::class => MockResponse::make($vodPayload),
    ]));

    $response = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'retry',
    ]);

    $response->assertRedirect();

    $aria2Mock->assertSent(function ($request): bool {
        return $request instanceof AddUriRequest;
    });

    $aria2Mock->assertSent(function ($request) use ($expectedOut): bool {
        if (! $request instanceof AddUriRequest) {
            return false;
        }

        $options = $request->getOptions();

        return ($options['continue'] ?? null) === true
            && ($options['auto-file-renaming'] ?? null) === false
            && ($options['out'] ?? null) === $expectedOut;
    });
});

it('retries failed rows manually and exposes retry metadata fields in downloads payload', function (): void {
    $owner = User::factory()->memberInternal()->create();
    createRetryPolicyVodStream(streamId: 7107, name: 'Retry Movie 7107');

    $download = MediaDownloadRef::query()->create([
        'gid' => 'retry-manual-failed-gid',
        'media_id' => 7107,
        'media_type' => VodStream::class,
        'downloadable_id' => 7107,
        'user_id' => $owner->id,
        'last_error_code' => 29,
        'last_error_message' => 'provider overload',
        'retry_attempt' => 5,
        'retry_next_at' => now()->subMinute(),
    ]);

    $aria2Mock = new MockClient([
        RemoveDownloadResultRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $download->gid,
            'result' => 'OK',
        ]),
        AddUriRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => 'retry-manual-failed-add',
            'result' => 'retry-manual-failed-new-gid',
        ]),
        JsonRpcBatchRequest::class => MockResponse::make([
            [
                'jsonrpc' => '2.0',
                'id' => 'retry-manual-failed-new-gid',
                'result' => [
                    'gid' => 'retry-manual-failed-new-gid',
                    'status' => 'active',
                    'totalLength' => '1000',
                    'completedLength' => '200',
                    'downloadSpeed' => '15',
                    'errorCode' => '0',
                    'errorMessage' => '',
                    'dir' => '/tmp/retry-policy',
                    'files' => [],
                ],
            ],
        ]),
    ]);

    bindRetryPolicyAria2Mock($aria2Mock);
    bindRetryPolicyXtreamMock(new MockClient([
        GetVodInfoRequest::class => MockResponse::make(vodInfoPayload(7107, 'Retry Movie 7107')),
    ]));

    $patchResponse = $this->actingAs($owner)->patch(route('downloads.edit', ['model' => $download->id]), [
        'action' => 'retry',
    ]);

    $patchResponse->assertRedirect();
    $aria2Mock->assertSent(AddUriRequest::class);

    $download->refresh();

    expect($download->gid)->toBe('retry-manual-failed-new-gid');
    expect($download->retry_next_at)->toBeNull();
    expect($download->retry_attempt)->toBe(0);
    expect($download->last_error_code)->toBeNull();
    expect($download->last_error_message)->toBeNull();

    $indexResponse = $this->actingAs($owner)
        ->withHeaders(downloadRetryPolicyInertiaHeaders())
        ->get(route('downloads'));

    $indexResponse->assertOk();
    $indexResponse->assertHeader('X-Inertia', 'true');

    $payload = collect($indexResponse->json('props.downloads.data'))->firstWhere('id', $download->id);

    expect($payload)->toBeArray();
    expect($payload)->toHaveKeys(['last_error_code', 'last_error_message', 'retry_attempt', 'retry_next_at']);
});

function bindRetryPolicyAria2Mock(MockClient $mockClient): void
{
    app()->bind(JsonRpcConnector::class, static function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });
}

function bindRetryPolicyXtreamMock(MockClient $mockClient): void
{
    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });
}

function createRetryPolicyVodStream(int $streamId, string $name): VodStream
{
    $timestamp = now();

    DB::table('vod_streams')->insert([
        'stream_id' => $streamId,
        'num' => $streamId,
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

function createRetryPolicyTempDir(string $prefix): string
{
    $directory = sys_get_temp_dir().'/lionzhd-'.$prefix.'-'.uniqid('', true);
    File::ensureDirectoryExists($directory);

    return $directory;
}

/**
 * @return array<string, mixed>
 */
function vodInfoPayload(int $vodId, string $name): array
{
    return [
        'info' => [
            'movie_image' => '',
            'tmdb_id' => '',
            'backdrop' => '',
            'youtube_trailer' => '',
            'genre' => '',
            'plot' => '',
            'cast' => '',
            'rating' => '0',
            'director' => '',
            'releasedate' => '2026-01-01',
            'backdrop_path' => [],
            'duration_secs' => 0,
            'duration' => '0:00:00',
            'video' => [],
            'audio' => [],
            'bitrate' => 0,
        ],
        'movie_data' => [
            'stream_id' => $vodId,
            'name' => $name,
            'added' => '2026-01-01 00:00:00',
            'category_id' => '',
            'container_extension' => 'mp4',
            'custom_sid' => '',
            'direct_source' => '',
        ],
    ];
}

function downloadRetryPolicyInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
