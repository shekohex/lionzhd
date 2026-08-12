<?php

declare(strict_types=1);

use App\Services\SearchIndexFingerprint;

it('fingerprints every document independent of source order and numeric representation', function (): void {
    $fingerprint = new SearchIndexFingerprint;

    $expected = $fingerprint->forDocuments([
        ['id' => 1, 'name' => 'First', 'rating' => 4],
        ['id' => 2, 'name' => 'Second', 'rating' => 4.5],
    ]);

    expect($fingerprint->forDocuments([
        ['rating' => 4.5, 'name' => 'Second', 'id' => 2.0],
        ['rating' => 4.0, 'name' => 'First', 'id' => 1.0],
    ]))->toBe($expected)
        ->and($fingerprint->forDocuments([
            ['id' => 1, 'name' => 'Stale', 'rating' => 4],
            ['id' => 2, 'name' => 'Second', 'rating' => 4.5],
        ]))->not->toBe($expected);
});
