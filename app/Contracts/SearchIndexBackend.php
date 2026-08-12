<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\SearchIndexState;

interface SearchIndexBackend
{
    public function inspect(string $indexName): SearchIndexState;

    /**
     * @param  array<string, mixed>  $settings
     * @param  iterable<int, array<int, array<string, mixed>>>  $documentChunks
     */
    public function replaceAtomically(
        string $indexName,
        string $primaryKey,
        array $settings,
        iterable $documentChunks,
        int $expectedDocuments,
    ): void;
}
