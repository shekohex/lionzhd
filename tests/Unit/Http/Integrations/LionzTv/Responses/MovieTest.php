<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Integrations\LionzTv\Responses;

use App\Http\Integrations\LionzTv\Responses\Movie;

describe('Movie', function (): void {
    it('handles complete API response data', function (): void {
        $data = [
            'stream_id' => 98765,
            'name' => 'Test Movie',
            'added' => '2023-01-01 12:00:00',
            'category_id' => 'cat123',
            'container_extension' => 'mp4',
            'custom_sid' => 'custom123',
            'direct_source' => 'http://example.com/movie.mp4',
        ];

        $movie = Movie::fromJson($data);

        expect($movie->streamId)->toBe(98765);
        expect($movie->name)->toBe('Test Movie');
        expect($movie->added)->toBe('2023-01-01 12:00:00');
        expect($movie->categoryId)->toBe('cat123');
        expect($movie->containerExtension)->toBe('mp4');
        expect($movie->customSid)->toBe('custom123');
        expect($movie->directSource)->toBe('http://example.com/movie.mp4');
    });

    it('handles missing keys in API response', function (): void {
        $data = [
            'stream_id' => 98765,
            // other keys are missing
        ];

        // This should not throw an exception
        $movie = Movie::fromJson($data);

        expect($movie->streamId)->toBe(98765);
        expect($movie->name)->toBe('');
        expect($movie->added)->toBe('');
        expect($movie->categoryId)->toBe('');
        expect($movie->containerExtension)->toBe('');
        expect($movie->customSid)->toBe('');
        expect($movie->directSource)->toBe('');
    });

    it('handles empty data array', function (): void {
        $data = [];

        // This should not throw an exception
        $movie = Movie::fromJson($data);

        expect($movie->streamId)->toBe(0);
        expect($movie->name)->toBe('');
        expect($movie->added)->toBe('');
        expect($movie->categoryId)->toBe('');
        expect($movie->containerExtension)->toBe('');
        expect($movie->customSid)->toBe('');
        expect($movie->directSource)->toBe('');
    });
});