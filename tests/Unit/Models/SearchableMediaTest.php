<?php

declare(strict_types=1);

use App\Models\Series;
use App\Models\VodStream;
use Carbon\CarbonImmutable;

it('uses the database Scout driver during tests', function (): void {
    expect(config('scout.driver'))->toBe('database');
});

it('keeps movie search documents aligned with Meilisearch settings', function (): void {
    $movie = new VodStream([
        'name' => 'Searchable Movie',
        'category_id' => '42',
        'added' => '2026-01-02 03:04:05',
        'rating' => '8.5',
        'rating_5based' => 4.3,
    ]);
    $movie->created_at = CarbonImmutable::parse('2026-01-01 00:00:00');

    $document = $movie->toSearchableArray();
    $settings = config('scout.meilisearch.index-settings.'.VodStream::class);

    expect(array_keys($document))
        ->toContain(...$settings['filterableAttributes'])
        ->toContain(...$settings['sortableAttributes'])
        ->and($document['added'])->toBe(CarbonImmutable::parse('2026-01-02 03:04:05')->getTimestamp())
        ->and($document['rating'])->toBe(8.5)
        ->and($document['rating_5based'])->toBe(4.3)
        ->and($document['created_at'])->toBe(CarbonImmutable::parse('2026-01-01 00:00:00')->getTimestamp())
        ->and($document)->toHaveKey('updated_at');
});

it('keeps series search documents aligned with Meilisearch settings', function (): void {
    $series = new Series([
        'name' => 'Searchable Series',
        'genre' => 'Drama',
        'category_id' => '84',
        'releaseDate' => '2026-02-03',
        'last_modified' => '2026-02-04 05:06:07',
        'rating' => '9.1',
        'rating_5based' => 4.6,
    ]);
    $series->created_at = CarbonImmutable::parse('2026-02-01 00:00:00');

    $document = $series->toSearchableArray();
    $settings = config('scout.meilisearch.index-settings.'.Series::class);

    expect(array_keys($document))
        ->toContain(...$settings['filterableAttributes'])
        ->toContain(...$settings['sortableAttributes'])
        ->and($document['last_modified'])->toBe(CarbonImmutable::parse('2026-02-04 05:06:07')->getTimestamp())
        ->and($document['rating'])->toBe(9.1)
        ->and($document['rating_5based'])->toBe(4.6)
        ->and($document['created_at'])->toBe(CarbonImmutable::parse('2026-02-01 00:00:00')->getTimestamp())
        ->and($document)->toHaveKey('updated_at');
});
