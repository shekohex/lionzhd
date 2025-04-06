<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\CreateDownloadDir;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\GetGlobalOptionsRequest;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Models\Aria2Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

describe('Create Download Directory', function (): void {
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

    it('creates the correct dir path for vod', function () use ($mockedDir): void {
        $vod = VodInformation::fake();
        $result = CreateDownloadDir::run($vod);
        expect($result)->toBe(implode(DIRECTORY_SEPARATOR, [
            $mockedDir,
            'movies',
            $vod->movie->name,
        ]).DIRECTORY_SEPARATOR);

    });
});
