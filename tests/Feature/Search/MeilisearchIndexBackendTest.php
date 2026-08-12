<?php

declare(strict_types=1);

use App\Services\MeilisearchIndexBackend;
use App\Services\SearchIndexFingerprint;
use Illuminate\Support\Str;
use Meilisearch\Client;

$meilisearchTestHost = getenv('MEILISEARCH_TEST_HOST') ?: '';
$meilisearchTestKey = getenv('MEILISEARCH_TEST_KEY') ?: null;

it('atomically replaces a live index after validating the replacement', function () use ($meilisearchTestHost, $meilisearchTestKey): void {
    $client = new Client($meilisearchTestHost, $meilisearchTestKey);
    $indexName = 'test_'.Str::lower(Str::random(12));
    $fingerprint = new SearchIndexFingerprint;
    $backend = new MeilisearchIndexBackend($client, $fingerprint);

    try {
        $createTask = $client->createIndex($indexName, ['primaryKey' => 'id']);
        $client->waitForTask($createTask['taskUid']);
        $addTask = $client->index($indexName)->addDocuments([['id' => 1, 'name' => 'stale']], 'id');
        $client->waitForTask($addTask['taskUid']);

        $backend->replaceAtomically(
            indexName: $indexName,
            primaryKey: 'id',
            settings: ['sortableAttributes' => ['updated_at']],
            documentChunks: [[['id' => 2, 'name' => 'fresh', 'updated_at' => 100]]],
            expectedDocuments: 1,
        );

        expect($client->index($indexName)->getDocument(2)['name'])->toBe('fresh')
            ->and($client->index($indexName)->stats()['numberOfDocuments'])->toBe(1)
            ->and($client->index($indexName)->getSettings()['sortableAttributes'])->toBe(['updated_at'])
            ->and($backend->inspect($indexName)->fingerprint)->toBe($fingerprint->forDocuments([
                ['id' => 2, 'name' => 'fresh', 'updated_at' => 100],
            ]));
    } finally {
        deleteMeilisearchTestIndex($client, $indexName);
    }
})->skip($meilisearchTestHost === '', 'MEILISEARCH_TEST_HOST is not configured.');

it('keeps the live index untouched when replacement validation fails', function () use ($meilisearchTestHost, $meilisearchTestKey): void {
    $client = new Client($meilisearchTestHost, $meilisearchTestKey);
    $indexName = 'test_'.Str::lower(Str::random(12));

    try {
        $createTask = $client->createIndex($indexName, ['primaryKey' => 'id']);
        $client->waitForTask($createTask['taskUid']);
        $addTask = $client->index($indexName)->addDocuments([['id' => 1, 'name' => 'live']], 'id');
        $client->waitForTask($addTask['taskUid']);

        expect(fn () => (new MeilisearchIndexBackend($client, new SearchIndexFingerprint))->replaceAtomically(
            indexName: $indexName,
            primaryKey: 'id',
            settings: [],
            documentChunks: [[['id' => 2, 'name' => 'incomplete']]],
            expectedDocuments: 2,
        ))->toThrow(RuntimeException::class, 'expected 2 documents, got 1')
            ->and($client->index($indexName)->getDocument(1)['name'])->toBe('live')
            ->and($client->index($indexName)->stats()['numberOfDocuments'])->toBe(1);
    } finally {
        deleteMeilisearchTestIndex($client, $indexName);
    }
})->skip($meilisearchTestHost === '', 'MEILISEARCH_TEST_HOST is not configured.');

function deleteMeilisearchTestIndex(Client $client, string $indexName): void
{
    try {
        $task = $client->deleteIndex($indexName);
        $client->waitForTask($task['taskUid']);
    } catch (Throwable) {
        // Index may not have been created when setup fails.
    }
}
