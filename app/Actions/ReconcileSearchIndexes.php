<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\SearchIndexBackend;
use App\Data\SearchIndexState;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class ReconcileSearchIndexes
{
    public function __construct(private SearchIndexBackend $backend) {}

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
        $sampleModel = $modelClass::query()
            ->orderByDesc('updated_at')
            ->orderByDesc($primaryKey)
            ->first();
        $sampleDocument = $sampleModel === null ? null : $this->searchDocument($sampleModel);
        $state = $this->backend->inspect($indexName, $sampleModel?->getScoutKey());
        $settings = config('scout.meilisearch.index-settings.'.$modelClass, []);

        if (! $force && $this->isHealthy($state, $expectedDocuments, $sampleDocument, $settings)) {
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
     * @param  array<string, mixed>|null  $sampleDocument
     * @param  array<string, mixed>  $expectedSettings
     */
    private function isHealthy(
        SearchIndexState $state,
        int $expectedDocuments,
        ?array $sampleDocument,
        array $expectedSettings,
    ): bool {
        if (! $state->exists || $state->documents !== $expectedDocuments) {
            return false;
        }

        foreach ($expectedSettings as $setting => $value) {
            if (($state->settings[$setting] ?? null) !== $value) {
                return false;
            }
        }

        if ($sampleDocument === null) {
            return true;
        }

        if (array_diff(array_keys($sampleDocument), $state->fields) !== []) {
            return false;
        }

        if ($state->sample === null) {
            return false;
        }

        foreach ($sampleDocument as $field => $value) {
            if (! $this->valuesMatch($state->sample[$field] ?? null, $value)) {
                return false;
            }
        }

        return true;
    }

    private function valuesMatch(mixed $indexedValue, mixed $databaseValue): bool
    {
        if (is_numeric($indexedValue) && is_numeric($databaseValue)) {
            return (float) $indexedValue === (float) $databaseValue;
        }

        return $indexedValue === $databaseValue;
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
