<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Client\Aria2Client;
use App\Exceptions\Aria2\AuthenticationException;
use App\Exceptions\Aria2\DownloadException;
use App\Models\Aria2Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class Aria2ClientTest extends TestCase
{
    private Aria2Config $config;

    private Aria2Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new Aria2Config([
            'host' => 'localhost',
            'port' => 6800,
            'secret' => 'test-secret',
            'use_ssl' => false,
        ]);

        // Mock HTTP client to prevent real requests
        Http::fake();

        // Create the client
        $this->client = new Aria2Client($this->config);
    }

    public function it_adds_uri_successfully(): void
    {
        $expectedGid = '2089b05ecca3d829';
        $uri = 'https://example.com/file.zip';

        Http::fake([
            '*' => Http::response([
                'id' => '1',
                'jsonrpc' => '2.0',
                'result' => $expectedGid,
            ]),
        ]);

        $gid = $this->client->addUri($uri);

        $this->assertEquals($expectedGid, $gid);

        Http::assertSent(fn ($request) => $request['method'] === 'aria2.addUri' &&
                   $request['params'][0] === 'token:test-secret' &&
                   $request['params'][1][0] === $uri);
    }

    public function it_handles_authentication_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => '1',
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32098,
                    'message' => 'Invalid token',
                ],
            ]),
        ]);

        $this->expectException(AuthenticationException::class);

        $this->client->addUri('https://example.com/file.zip');
    }

    public function it_handles_download_error(): void
    {
        $gid = '2089b05ecca3d829';

        Http::fake([
            '*' => Http::response([
                'id' => '1',
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => 1,
                    'message' => 'Download not found',
                ],
            ]),
        ]);

        $this->expectException(DownloadException::class);

        $this->client->remove($gid);
    }

    public function it_gets_download_status(): void
    {
        $gid = '2089b05ecca3d829';
        $expectedStatus = [
            'gid' => $gid,
            'status' => 'active',
            'totalLength' => '1024',
            'completedLength' => '512',
            'downloadSpeed' => '2048',
        ];

        Http::fake([
            '*' => Http::response([
                'id' => '1',
                'jsonrpc' => '2.0',
                'result' => $expectedStatus,
            ]),
        ]);

        $status = $this->client->tellStatus($gid);

        $this->assertEquals($expectedStatus, $status);

        Http::assertSent(fn ($request) => $request['method'] === 'aria2.tellStatus' &&
                   $request['params'][0] === 'token:test-secret' &&
                   $request['params'][1] === $gid);
    }

    public function it_gets_global_stats(): void
    {
        $expectedStats = [
            'downloadSpeed' => '2048',
            'uploadSpeed' => '1024',
            'numActive' => '1',
            'numWaiting' => '0',
            'numStopped' => '5',
        ];

        Http::fake([
            '*' => Http::response([
                'id' => '1',
                'jsonrpc' => '2.0',
                'result' => $expectedStats,
            ]),
        ]);

        $stats = $this->client->getGlobalStat();

        $this->assertEquals($expectedStats, $stats);

        Http::assertSent(fn ($request) => $request['method'] === 'aria2.getGlobalStat' &&
                   $request['params'][0] === 'token:test-secret');
    }

    public function it_pauses_and_resumes_downloads(): void
    {
        $gid = '2089b05ecca3d829';

        Http::fake([
            '*' => Http::response([
                'id' => '1',
                'jsonrpc' => '2.0',
                'result' => 'OK',
            ]),
        ]);

        // Test pause
        $this->client->pause($gid);

        Http::assertSent(fn ($request) => $request['method'] === 'aria2.pause' &&
                   $request['params'][0] === 'token:test-secret' &&
                   $request['params'][1] === $gid);

        // Test unpause
        $this->client->unpause($gid);

        Http::assertSent(fn ($request) => $request['method'] === 'aria2.unpause' &&
                   $request['params'][0] === 'token:test-secret' &&
                   $request['params'][1] === $gid);
    }

    public function it_gets_version_info(): void
    {
        $expectedVersion = [
            'version' => '1.36.0',
            'enabledFeatures' => ['BitTorrent', 'Metalink'],
        ];

        Http::fake([
            '*' => Http::response([
                'id' => '1',
                'jsonrpc' => '2.0',
                'result' => $expectedVersion,
            ]),
        ]);

        $version = $this->client->getVersion();

        $this->assertEquals($expectedVersion, $version);

        Http::assertSent(fn ($request) => $request['method'] === 'aria2.getVersion' &&
                   $request['params'][0] === 'token:test-secret');
    }
}
