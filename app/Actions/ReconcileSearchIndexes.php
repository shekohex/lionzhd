<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\SearchIndexBackend;
use App\Data\SearchIndexState;
use App\Models\Series;
use App\Models\VodStream;
use App\Services\SearchIndexFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class ReconcileSearchIndexes
{
    public function __construct(
        private SearchIndexBackend $backend,
        private SearchIndexFingerprint $fingerprint,
    ) {}

    public function __invoke(bool $force = false): void
    {
        if (config('scout.driver') !== 'meilisearch' || ! config('scout.reconcile.enabled', true)) {
            return;
        }

        $lock = Cache::lock('search-index-reconciliation', (int) config('scout.reconcile.lock_seconds', 3600));

        if (! $lock->get()) {
            Log::info('Search index reconciliation already running');

            return;
        }

        try {
            $this->reconcileModel(new Series, $force);
            $this->reconcileModel(new VodStream, $force);
        } finally {
            $lock->release();
        }
    }

    private function reconcileModel(Series|VodStream $model, bool $force): void
    {
        $modelClass = $model::class;
        $primaryKey = $model->getScoutKeyName();
        $indexName = $model->indexableAs();
        $expectedDocuments = $modelClass::query()->count();
        $expectedFingerprint = $this->fingerprint->forDocuments($this->documents($modelClass, $primaryKey));
        $state = $this->backend->inspect($indexName);
        $settings = config('scout.meilisearch.index-settings.'.$modelClass, []);

        if (! $force && $this->isHealthy($state, $expectedDocuments, $expectedFingerprint, $settings)) {
            return;
        }

        Log::warning('Rebuilding inconsistent search index', [
            'index' => $indexName,
            'database_documents' => $expectedDocuments,
            'indexed_documents' => $state->documents,
            'index_exists' => $state->exists,
        ]);

        $this->backend->replaceAtomically(
            indexName: $indexName,
            primaryKey: $primaryKey,
            settings: $settings,
            documentChunks: $this->documentChunks($modelClass, $primaryKey),
            expectedDocuments: $expectedDocuments,
        );
    }

    /**
     * @param  array<string, mixed>  $expectedSettings
     */
    private function isHealthy(
        SearchIndexState $state,
        int $expectedDocuments,
        string $expectedFingerprint,
        array $expectedSettings,
    ): bool {
        if (
            ! $state->exists
            || $state->documents !== $expectedDocuments
            || $state->fingerprint !== $expectedFingerprint
        ) {
            return false;
        }

        foreach ($expectedSettings as $setting => $value) {
            if (($state->settings[$setting] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  class-string<Series|VodStream>  $modelClass
     * @return iterable<int, array<int, array<string, mixed>>>
     */
    private function documentChunks(string $modelClass, string $primaryKey): iterable
    {
        $chunkSize = (int) config('scout.reconcile.chunk', 1000);

        foreach ($modelClass::query()->lazyById($chunkSize, $primaryKey)->chunk($chunkSize) as $models) {
            yield $models
                ->map(fn (Series|VodStream $model): array => $this->searchDocument($model))
                ->values()
                ->all();
        }
    }

    /**
     * @param  class-string<Series|VodStream>  $modelClass
     * @return iterable<int, array<string, mixed>>
     */
    private function documents(string $modelClass, string $primaryKey): iterable
    {
        foreach ($this->documentChunks($modelClass, $primaryKey) as $documents) {
            yield from $documents;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function searchDocument(Series|VodStream $model): array
    {
        return [
            ...$model->toSearchableArray(),
            $model->getScoutKeyName() => $model->getScoutKey(),
        ];
    }
}
