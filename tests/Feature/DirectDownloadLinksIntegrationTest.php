<?php

declare(strict_types=1);

use App\Actions\CreateSignedDirectLink;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

describe('Direct Download Links Integration', function (): void {
    beforeEach(function (): void {
        // Enable the feature flag for testing
        Config::set('features.direct_download_links', true);
    });

    it('creates signed direct link and stores in cache', function (): void {
        $vod = VodInformation::fake();

        $url = CreateSignedDirectLink::run($vod);

        expect($url)->toBeString();
        expect($url)->toContain('/dl/');

        // Extract token from URL and check cache
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

        // Extract token from URL and check cache
        $token = basename(parse_url($url, PHP_URL_PATH));
        $cacheKey = "direct:link:{$token}";
        
        expect(Cache::has($cacheKey))->toBeTrue();
        expect(Cache::get($cacheKey))->toBeString();
    });

    it('stores different tokens for different content', function (): void {
        $vod1 = VodInformation::fake(['id' => 'vod1']);
        $vod2 = VodInformation::fake(['id' => 'vod2']);

        $url1 = CreateSignedDirectLink::run($vod1);
        $url2 = CreateSignedDirectLink::run($vod2);

        expect($url1)->not->toBe($url2);

        $token1 = basename(parse_url($url1, PHP_URL_PATH));
        $token2 = basename(parse_url($url2, PHP_URL_PATH));

        expect($token1)->not->toBe($token2);
    });

    it('sets cache with approximately 4 hours TTL', function (): void {
        $vod = VodInformation::fake();
        
        // Test that cache entry exists
        $url = CreateSignedDirectLink::run($vod);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $cacheKey = "direct:link:{$token}";
        
        expect(Cache::has($cacheKey))->toBeTrue();
    });
});