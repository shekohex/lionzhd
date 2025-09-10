<?php

declare(strict_types=1);

use App\Actions\CreateSignedDirectLink;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\GetGlobalOptionsRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Models\Aria2Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

describe('Create Signed Direct Link Action', function (): void {
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

    it('creates signed direct link for VOD', function (): void {
        $vod = VodInformation::fake();

        $url = CreateSignedDirectLink::run($vod);

        expect($url)->toBeString();
        expect($url)->toContain('/dl/');
        expect($url)->toContain('signature=');
    });

    it('stores remote URL in cache', function (): void {
        $vod = VodInformation::fake();

        $url = CreateSignedDirectLink::run($vod);

        // Extract token from URL
        $token = basename(parse_url($url, PHP_URL_PATH));
        $cacheKey = "direct:link:{$token}";
        
        expect(Cache::has($cacheKey))->toBeTrue();
        expect(Cache::get($cacheKey))->toBeString();
    });

    it('creates signed direct link for episode', function (): void {
        $episode = Episode::fake();

        $url = CreateSignedDirectLink::run($episode);

        expect($url)->toBeString();
        expect($url)->toContain('/dl/');
        expect($url)->toContain('signature=');
    });

    it('creates different tokens for different content', function (): void {
        $vod1 = VodInformation::fake(['id' => 'vod1']);
        $vod2 = VodInformation::fake(['id' => 'vod2']);

        $url1 = CreateSignedDirectLink::run($vod1);
        $url2 = CreateSignedDirectLink::run($vod2);

        expect($url1)->not->toBe($url2);

        $token1 = basename(parse_url($url1, PHP_URL_PATH));
        $token2 = basename(parse_url($url2, PHP_URL_PATH));

        expect($token1)->not->toBe($token2);
    });

    it('handles feature flag correctly', function (): void {
        Config::set('features.direct_download_links', false);
        
        $vod = VodInformation::fake();

        // This should still work since the action doesn't check the feature flag
        // (the controllers do, but the action is just a utility)
        $url = CreateSignedDirectLink::run($vod);

        expect($url)->toBeString();
        expect($url)->toContain('/dl/');
    });
});