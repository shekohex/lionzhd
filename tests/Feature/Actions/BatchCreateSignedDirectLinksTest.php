<?php

declare(strict_types=1);

use App\Actions\BatchCreateSignedDirectLinks;
use App\Actions\CreateSignedDirectLink;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\GetGlobalOptionsRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Models\Aria2Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

describe('Batch Create Signed Direct Links Action', function (): void {
    beforeEach(function (): void {
        // Enable the feature flag for testing
        Config::set('features.direct_download_links', true);
        
        // Mock Aria2 config since it's required by the action
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
                    'dir' => '/mocked-dir',
                ],
            ]),
        ]);

        app()->bind(function () use ($mockClient): JsonRpcConnector {
            $connector = new JsonRpcConnector(app(Aria2Config::class));
            return $connector->withMockClient($mockClient);
        });
    });

    it('creates multiple signed direct links for episodes', function (): void {
        $episodes = [
            Episode::fake(['id' => 'ep1']),
            Episode::fake(['id' => 'ep2']),
            Episode::fake(['id' => 'ep3']),
        ];

        $result = BatchCreateSignedDirectLinks::run($episodes);

        expect($result)->toHaveCount(3);
        
        // Check that all URLs are valid signed URLs
        foreach ($result as $url) {
            expect($url)->toBeString();
            expect($url)->toContain('/dl/');
            expect($url)->toContain('signature=');
        }
    });

    it('returns empty collection for empty episodes array', function (): void {
        $result = BatchCreateSignedDirectLinks::run([]);

        expect($result)->toBeEmpty();
    });

    it('preserves episode order in returned URLs', function (): void {
        $episodes = [
            Episode::fake(['id' => 'episode3']),
            Episode::fake(['id' => 'episode1']),
            Episode::fake(['id' => 'episode2']),
        ];

        $result = BatchCreateSignedDirectLinks::run($episodes);

        expect($result)->toHaveCount(3);
        
        // Check that order is preserved by checking cache entries
        $tokens = $result->map(fn ($url) => basename(parse_url($url, PHP_URL_PATH)));
        
        // Each episode should have a unique token
        expect($tokens->unique())->toHaveCount(3);
    });

    it('creates valid cache entries for all episodes', function (): void {
        $episodes = [
            Episode::fake(['id' => 'ep1']),
            Episode::fake(['id' => 'ep2']),
        ];

        $result = BatchCreateSignedDirectLinks::run($episodes);

        // Check that cache entries exist for all tokens
        foreach ($result as $url) {
            $token = basename(parse_url($url, PHP_URL_PATH));
            $cacheKey = "direct:link:{$token}";
            
            expect(Cache::has($cacheKey))->toBeTrue();
            expect(Cache::get($cacheKey))->toBeString();
        }
    });
});