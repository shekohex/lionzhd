<?php

declare(strict_types=1);

use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\AddUriRequest;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Aria2Config;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('does not start a series download while the same episode lock is held', function (): void {
    $user = User::factory()->memberInternal()->create();
    DB::table('series')->insert([
        'series_id' => 6101,
        'num' => 6101,
        'name' => 'Locked Series',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $series = Series::query()->findOrFail(6101);

    bindSeriesDownloadInfo('101');
    $aria2Mock = bindAria2AddUri('series-lock-gid');

    $lock = Cache::lock('downloads:manual:user:'.$user->id.':App:Models:Series:'.$series->series_id.':101', 15);
    expect($lock->get())->toBeTrue();

    try {
        $response = test()->actingAs($user)
            ->get(route('series.download.single', ['model' => $series->series_id, 'season' => 1, 'episode' => 0]), ['referer' => '/series/6101']);
    } finally {
        $lock->release();
    }

    $response->assertRedirect('/series/6101');
    $response->assertSessionHasErrors([
        'download' => 'Download is already being prepared. Please try again.',
    ]);
    expect(MediaDownloadRef::query()->count())->toBe(0);
    $aria2Mock->assertNotSent(AddUriRequest::class);
});

it('does not start a vod download while the same lock is held', function (): void {
    $user = User::factory()->memberInternal()->create();
    DB::table('vod_streams')->insert([
        'stream_id' => 7101,
        'num' => 7101,
        'name' => 'Locked Movie',
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => null,
        'rating_5based' => 0,
        'category_id' => null,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'added' => now(),
        'is_adult' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $vod = VodStream::query()->findOrFail(7101);

    bindVodDownloadInfo($vod->stream_id, $vod->name);
    $aria2Mock = bindAria2AddUri('vod-lock-gid');

    $lock = Cache::lock('downloads:manual:user:'.$user->id.':App:Models:VodStream:'.$vod->stream_id.':'.$vod->stream_id, 15);
    expect($lock->get())->toBeTrue();

    try {
        $response = test()->actingAs($user)
            ->get(route('movies.download', ['model' => $vod->stream_id]), ['referer' => '/movies/7101']);
    } finally {
        $lock->release();
    }

    $response->assertRedirect('/movies/7101');
    $response->assertSessionHasErrors([
        'download' => 'Download is already being prepared. Please try again.',
    ]);
    expect(MediaDownloadRef::query()->count())->toBe(0);
    $aria2Mock->assertNotSent(AddUriRequest::class);
});

function bindSeriesDownloadInfo(string $episodeId): MockClient
{
    $mockClient = new MockClient([
        GetSeriesInfoRequest::class => MockResponse::make([
            'info' => [
                'name' => 'Locked Series',
                'cover' => 'https://example.test/cover.jpg',
                'plot' => 'plot',
                'cast' => 'cast',
                'director' => 'director',
                'genre' => 'genre',
                'releaseDate' => '2026-01-01',
                'last_modified' => '2026-01-01 00:00:00',
                'rating' => '8.0',
                'rating_5based' => 4.0,
                'backdrop_path' => ['https://example.test/backdrop.jpg'],
                'youtube_trailer' => 'https://youtube.com/watch?v=test',
                'episode_run_time' => '00:45:00',
                'category_id' => '1',
            ],
            'seasons' => ['1'],
            'episodes' => [
                '1' => [[
                    'id' => $episodeId,
                    'season' => 1,
                    'episode_num' => 1,
                    'title' => 'Episode 1',
                    'container_extension' => 'mkv',
                    'custom_sid' => 'sid-'.$episodeId,
                    'added' => '2026-01-01 00:00:00',
                    'direct_source' => '',
                    'info' => [
                        'duration_secs' => 2700,
                        'duration' => '00:45:00',
                        'bitrate' => 1000,
                        'video' => [],
                        'audio' => [],
                    ],
                ]],
            ],
        ], 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

function bindVodDownloadInfo(int $vodId, string $name): MockClient
{
    $mockClient = new MockClient([
        GetVodInfoRequest::class => MockResponse::make([
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
        ], 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

function bindAria2AddUri(string $gid): MockClient
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
