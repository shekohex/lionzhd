<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\CreateDownloadOut;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\GetGlobalOptionsRequest;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Models\Aria2Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

describe('Create Download Output file', function (): void {
    $mockedDir = '/mocked-dir';
    beforeEach(function () use ($mockedDir): void {

        app()->bind(Aria2Config::class, fn () => new Aria2Config(
            [
                'host' => 'mocked-host',
                'port' => 1013,
                'secret' => 'mocked-secret',
                'use_ssl' => false,
            ]
        ));

        $mockClient = new MockClient([
            GetGlobalOptionsRequest::class => MockResponse::make([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'dir' => $mockedDir,
                ],
            ]),
        ]);

        app()->bind(function () use ($mockClient): JsonRpcConnector {
            $connector = new JsonRpcConnector(app(Aria2Config::class));

            return $connector->withMockClient($mockClient);
        });

    });

    it('creates the correct out path for vod', function (): void {
        $vod = VodInformation::fake();
        $result = CreateDownloadOut::run($vod);
        expect($result)->toBe(implode(DIRECTORY_SEPARATOR, [
            'movies',
            $vod->movie->name,
            $vod->movie->name.'.'.$vod->movie->containerExtension,
        ]));

    });

    it('creates the correct out path for series', function (): void {
        $series = SeriesInformation::fake();
        $episode = $series->seasonsWithEpisodes[1][1];
        $result = CreateDownloadOut::run($series, $episode);
        expect($result)->toBe(implode(DIRECTORY_SEPARATOR, [
            'shows',
            $series->name,
            'Season '.$episode->season,
            $episode->title.'.'.$episode->containerExtension,
        ]));

    });
});
