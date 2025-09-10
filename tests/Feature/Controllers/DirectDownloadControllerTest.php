<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

describe('Direct Download Controller', function (): void {
    beforeEach(function (): void {
        // Enable the feature flag for testing
        Config::set('features.direct_download_links', true);
    });

    it('redirects to remote URL for valid signed token', function (): void {
        // Create a valid signed URL
        $url = URL::temporarySignedRoute(
            'direct.resolve',
            now()->addHours(4),
            ['token' => 'test-token']
        );

        // Mock the cache
        Cache::put('direct:link:test-token', 'https://example.com/remote-url.mp4', now()->addHours(4));

        $response = $this->get($url);

        $response->assertRedirect('https://example.com/remote-url.mp4');
    });

    it('returns 403 for invalid signature', function (): void {
        $response = $this->get('/dl/test-token');

        $response->assertForbidden();
    });

    it('returns 403 for expired signature', function (): void {
        // Create an expired signed URL
        $url = URL::temporarySignedRoute(
            'direct.resolve',
            now()->subHour(),
            ['token' => 'test-token']
        );

        $response = $this->get($url);

        $response->assertForbidden();
    });

    it('returns 404 for missing cache entry', function (): void {
        // Create a valid signed URL but no cache entry
        $url = URL::temporarySignedRoute(
            'direct.resolve',
            now()->addHours(4),
            ['token' => 'missing-token']
        );

        $response = $this->get($url);

        $response->assertNotFound();
    });

    it('returns 404 when feature is disabled', function (): void {
        // Disable feature
        Config::set('features.direct_download_links', false);

        // Create a valid signed URL
        $url = URL::temporarySignedRoute(
            'direct.resolve',
            now()->addHours(4),
            ['token' => 'disabled-feature-token']
        );

        Cache::put('direct:link:disabled-feature-token', 'https://example.com/file.mp4', now()->addHours(4));

        $response = $this->get($url);

        $response->assertNotFound();
    });

    it('works without authentication', function (): void {
        // Create a valid signed URL
        $url = URL::temporarySignedRoute(
            'direct.resolve',
            now()->addHours(4),
            ['token' => 'public-token']
        );

        // Mock the cache
        Cache::put('direct:link:public-token', 'https://public.example.com/file.mp4', now()->addHours(4));

        // Should work even without auth
        $response = $this->get($url);

        $response->assertRedirect('https://public.example.com/file.mp4');
    });

    it('handles different URL formats', function (): void {
        $testUrls = [
            'https://cdn.example.com/video.mp4',
            'http://stream.example.com:8080/content/movie.mkv',
            'https://storage.example.com/path/to/series/episode.mp4',
        ];

        foreach ($testUrls as $remoteUrl) {
            $token = 'test-token-'.md5($remoteUrl);

            $url = URL::temporarySignedRoute(
                'direct.resolve',
                now()->addHours(4),
                ['token' => $token]
            );

            // Mock the cache
            Cache::put("direct:link:{$token}", $remoteUrl, now()->addHours(4));

            $response = $this->get($url);

            $response->assertRedirect($remoteUrl);
        }
    });
});
