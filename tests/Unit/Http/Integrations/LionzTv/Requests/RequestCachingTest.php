<?php

declare(strict_types=1);

use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\XtreamCodesConfig;
use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Cache::flush();
});

it('does not cache media list requests', function (): void {
    expect(in_array(Cacheable::class, class_implements(GetSeriesRequest::class), true))->toBeFalse();
    expect(in_array(Cacheable::class, class_implements(GetVodStreamsRequest::class), true))->toBeFalse();
});

it('uses shorter ttl for volatile remote dto caches', function (): void {
    expect((new GetSeriesInfoRequest(1))->cacheExpiryInSeconds())->toBe(10 * 60);
    expect((new GetVodInfoRequest(1))->cacheExpiryInSeconds())->toBe(12 * 60 * 60);
});

it('isolates series info cache by xtream scope', function (): void {
    $connectorA = makeConnector('http://host-a.local', 80, 'user-a', seriesInfoPayload('Host A Series'));
    $connectorB = makeConnector('http://host-b.local', 80, 'user-b', seriesInfoPayload('Host B Series'));

    $seriesAFirst = $connectorA->send(new GetSeriesInfoRequest(77))->dtoOrFail();
    $seriesB = $connectorB->send(new GetSeriesInfoRequest(77))->dtoOrFail();
    $seriesASecond = $connectorA->send(new GetSeriesInfoRequest(77))->dtoOrFail();

    expect($seriesAFirst->name)->toBe('Host A Series');
    expect($seriesB->name)->toBe('Host B Series');
    expect($seriesASecond->name)->toBe('Host A Series');
});

function makeConnector(string $host, int $port, string $username, array $seriesInfoResponse): XtreamCodesConnector
{
    $config = new XtreamCodesConfig([
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'password' => 'secret',
    ]);

    $connector = new XtreamCodesConnector($config);

    return $connector->withMockClient(new MockClient([
        GetSeriesInfoRequest::class => MockResponse::make($seriesInfoResponse, 200),
    ]));
}

function seriesInfoPayload(string $name): array
{
    return [
        'info' => [
            'name' => $name,
            'cover' => 'https://example.com/cover.jpg',
            'plot' => 'plot',
            'cast' => 'cast',
            'director' => 'director',
            'genre' => 'genre',
            'releaseDate' => '2024-01-01',
            'last_modified' => '2024-01-01 00:00:00',
            'rating' => '8.0',
            'rating_5based' => 4.0,
            'backdrop_path' => ['https://example.com/backdrop.jpg'],
            'youtube_trailer' => 'https://youtube.com/watch?v=test',
            'episode_run_time' => '00:45:00',
            'category_id' => '1',
        ],
        'seasons' => ['1'],
        'episodes' => [
            '1' => [[
                'id' => 'ep1',
                'episode_num' => 1,
                'title' => 'Episode 1',
                'container_extension' => 'mkv',
                'info' => [
                    'duration_secs' => 120,
                    'duration' => '00:02:00',
                    'bitrate' => 1000,
                ],
                'custom_sid' => 'sid',
                'added' => '2024-01-01 00:00:00',
                'season' => 1,
                'direct_source' => '',
            ]],
        ],
    ];
}
