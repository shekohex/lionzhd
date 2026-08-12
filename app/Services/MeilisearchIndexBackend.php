<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SearchIndexBackend;
use App\Data\SearchIndexState;
use Illuminate\Support\Str;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;
use Meilisearch\Exceptions\ApiException;
use RuntimeException;
use Throwable;

final readonly class MeilisearchIndexBackend implements SearchIndexBackend
{
    public function __construct(
        private Client $client,
        private SearchIndexFingerprint $fingerprint,
    ) {}

    public function inspect(string $indexName): SearchIndexState
    {
        try {
            $index = $this->client->getIndex($indexName);
        } catch (ApiException $exception) {
            if ($exception->httpStatus !== 404) {
                throw $exception;
            }

            return new SearchIndexState(false, 0, null);
        }

        $stats = $index->stats();

        return new SearchIndexState(
            exists: true,
            documents: (int) ($stats['numberOfDocuments'] ?? 0),
            fingerprint: $this->fingerprint->forDocuments($this->documents($indexName)),
            settings: $index->getSettings(),
        );
    }

    public function replaceAtomically(
        string $indexName,
        string $primaryKey,
        array $settings,
        iterable $documentChunks,
        int $expectedDocuments,
    ): void {
        $temporaryIndexName = sprintf('%s__rebuild_%s', $indexName, Str::lower(Str::random(12)));
        $swapped = false;

        try {
            $this->await($this->client->createIndex($temporaryIndexName, ['primaryKey' => $primaryKey]));

            $temporaryIndex = $this->client->index($temporaryIndexName);

            if ($settings !== []) {
                $this->await($temporaryIndex->updateSettings($settings));
            }

            foreach ($documentChunks as $documents) {
                if ($documents === []) {
                    continue;
                }

                $this->await($temporaryIndex->addDocuments($documents, $primaryKey));
            }

            $actualDocuments = (int) ($temporaryIndex->stats()['numberOfDocuments'] ?? 0);

            if ($actualDocuments !== $expectedDocuments) {
                throw new RuntimeException(sprintf(
                    'Search index [%s] rebuild expected %d documents, got %d.',
                    $indexName,
                    $expectedDocuments,
                    $actualDocuments,
                ));
            }

            if (! $this->indexExists($indexName)) {
                $this->await($this->client->createIndex($indexName, ['primaryKey' => $primaryKey]));
            }

            $this->await($this->client->swapIndexes([[$indexName, $temporaryIndexName]]));
            $swapped = true;
            $this->deleteTemporaryIndex($temporaryIndexName);
        } catch (Throwable $exception) {
            if (! $swapped) {
                $this->deleteTemporaryIndex($temporaryIndexName);
            }

            throw $exception;
        }
    }

    private function indexExists(string $indexName): bool
    {
        try {
            $this->client->getIndex($indexName);

            return true;
        } catch (ApiException $exception) {
            if ($exception->httpStatus === 404) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function documents(string $indexName): iterable
    {
        $offset = 0;
        $limit = max(1, (int) config('scout.reconcile.chunk', 1000));

        do {
            $query = (new DocumentsQuery)
                ->setOffset($offset)
                ->setLimit($limit);
            $documents = $this->client->index($indexName)->getDocuments($query)->getResults();

            yield from $documents;
            $offset += count($documents);
        } while (count($documents) === $limit);
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function await(array $task): void
    {
        $completedTask = $this->client->waitForTask(
            $task['taskUid'],
            (int) config('scout.reconcile.task_timeout_ms', 600_000),
        );

        if (($completedTask['status'] ?? null) !== 'succeeded') {
            throw new RuntimeException((string) ($completedTask['error']['message'] ?? 'Meilisearch task failed.'));
        }
    }

    private function deleteTemporaryIndex(string $temporaryIndexName): void
    {
        try {
            if ($this->indexExists($temporaryIndexName)) {
                $this->await($this->client->deleteIndex($temporaryIndexName));
            }
        } catch (Throwable) {
            // Preserve the original rebuild failure. A later rebuild uses a unique temporary index.
        }
    }
}
