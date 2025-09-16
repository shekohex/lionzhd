<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Integrations\LionzTv\Responses;

use App\Http\Integrations\LionzTv\Responses\VodInformation;

describe('VodInformation', function (): void {
    it('handles complete API response data', function (): void {
        $vodId = 12345;
        $data = [
            'info' => [
                'movie_image' => 'https://example.com/image.jpg',
                'tmdb_id' => 'tmdb123',
                'backdrop' => 'https://example.com/backdrop.jpg',
                'youtube_trailer' => 'https://youtube.com/watch?v=abc',
                'genre' => 'Action',
                'plot' => 'A great movie plot',
                'cast' => 'John Doe, Jane Smith',
                'rating' => '8.5',
                'director' => 'Famous Director',
                'releasedate' => '2023-01-15',
                'backdrop_path' => ['path1.jpg', 'path2.jpg'],
                'duration_secs' => 7200,
                'duration' => '2:00:00',
                'video' => [],
                'audio' => [],
                'bitrate' => 5000000,
            ],
            'movie_data' => [
                'stream_id' => 98765,
                'name' => 'Test Movie',
                'added' => '2023-01-01 12:00:00',
                'category_id' => 'cat123',
                'container_extension' => 'mp4',
                'custom_sid' => 'custom123',
                'direct_source' => 'http://example.com/movie.mp4',
            ],
        ];

        $vod = VodInformation::fromJson($vodId, $data);

        expect($vod->vodId)->toBe(12345);
        expect($vod->movieImage)->toBe('https://example.com/image.jpg');
        expect($vod->tmdbId)->toBe('tmdb123');
        expect($vod->genre)->toBe('Action');
        expect($vod->rating)->toBe('8.5');
    });

    it('handles missing movie_image key in API response', function (): void {
        $vodId = 12345;
        $data = [
            'info' => [
                // movie_image is missing - this was causing the original error
                'tmdb_id' => 'tmdb123',
                'backdrop' => 'https://example.com/backdrop.jpg',
                'youtube_trailer' => 'https://youtube.com/watch?v=abc',
                'genre' => 'Action',
                'plot' => 'A great movie plot',
                'cast' => 'John Doe, Jane Smith',
                'rating' => '8.5',
                'director' => 'Famous Director',
                'releasedate' => '2023-01-15',
                'backdrop_path' => ['path1.jpg', 'path2.jpg'],
                'duration_secs' => 7200,
                'duration' => '2:00:00',
                'video' => [],
                'audio' => [],
                'bitrate' => 5000000,
            ],
            'movie_data' => [
                'stream_id' => 98765,
                'name' => 'Test Movie',
                'added' => '2023-01-01 12:00:00',
                'category_id' => 'cat123',
                'container_extension' => 'mp4',
                'custom_sid' => 'custom123',
                'direct_source' => 'http://example.com/movie.mp4',
            ],
        ];

        // This should not throw an exception anymore
        $vod = VodInformation::fromJson($vodId, $data);

        expect($vod->vodId)->toBe(12345);
        expect($vod->movieImage)->toBe(''); // Should default to empty string
        expect($vod->tmdbId)->toBe('tmdb123');
        expect($vod->genre)->toBe('Action');
    });

    it('handles completely missing info key in API response', function (): void {
        $vodId = 12345;
        $data = [
            // info key is completely missing
            'movie_data' => [
                'stream_id' => 98765,
                'name' => 'Test Movie',
                'added' => '2023-01-01 12:00:00',
                'category_id' => 'cat123',
                'container_extension' => 'mp4',
                'custom_sid' => 'custom123',
                'direct_source' => 'http://example.com/movie.mp4',
            ],
        ];

        // This should not throw an exception
        $vod = VodInformation::fromJson($vodId, $data);

        expect($vod->vodId)->toBe(12345);
        expect($vod->movieImage)->toBe('');
        expect($vod->tmdbId)->toBe('');
        expect($vod->genre)->toBe('');
        expect($vod->rating)->toBe('0.0');
        expect($vod->durationSecs)->toBe(0);
        expect($vod->backdropPath)->toBe([]);
    });

    it('handles missing movie_data key in API response', function (): void {
        $vodId = 12345;
        $data = [
            'info' => [
                'movie_image' => 'https://example.com/image.jpg',
                'tmdb_id' => 'tmdb123',
                'backdrop' => 'https://example.com/backdrop.jpg',
                'youtube_trailer' => 'https://youtube.com/watch?v=abc',
                'genre' => 'Action',
                'plot' => 'A great movie plot',
                'cast' => 'John Doe, Jane Smith',
                'rating' => '8.5',
                'director' => 'Famous Director',
                'releasedate' => '2023-01-15',
                'backdrop_path' => ['path1.jpg', 'path2.jpg'],
                'duration_secs' => 7200,
                'duration' => '2:00:00',
                'video' => [],
                'audio' => [],
                'bitrate' => 5000000,
            ],
            // movie_data is missing
        ];

        // This should not throw an exception
        $vod = VodInformation::fromJson($vodId, $data);

        expect($vod->vodId)->toBe(12345);
        expect($vod->movieImage)->toBe('https://example.com/image.jpg');
        expect($vod->movie->streamId)->toBe(0); // Should use default value
        expect($vod->movie->name)->toBe(''); // Should use default value
    });

    it('handles empty data array', function (): void {
        $vodId = 12345;
        $data = [];

        // This should not throw an exception
        $vod = VodInformation::fromJson($vodId, $data);

        expect($vod->vodId)->toBe(12345);
        expect($vod->movieImage)->toBe('');
        expect($vod->tmdbId)->toBe('');
        expect($vod->genre)->toBe('');
        expect($vod->rating)->toBe('0.0');
        expect($vod->durationSecs)->toBe(0);
        expect($vod->bitrate)->toBe(0);
        expect($vod->backdropPath)->toBe([]);
    });
});