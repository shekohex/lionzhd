<?php

declare(strict_types=1);

use App\Actions\ReconcileSearchIndexes;
use App\Contracts\SearchIndexBackend;
use App\Data\SearchIndexState;
use App\Models\Series;
use App\Models\VodStream;
use App\Services\SearchIndexFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('scout.driver', 'meilisearch');
    config()->set('scout.reconcile.enabled', true);
    config()->set('scout.reconcile.chunk', 2);
});

it('rebuilds missing and empty indexes then becomes idempotent', function (): void {
    seedSearchReconciliationMedia();

    $backend = new ReconciliationFakeBackend([
        (new Series)->indexableAs() => new SearchIndexState(false, 0, null),
        (new VodStream)->indexableAs() => new SearchIndexState(true, 0, null),
    ]);
    app()->instance(SearchIndexBackend::class, $backend);

    app(ReconcileSearchIndexes::class)();

    expect($backend->replacedIndexes)->toBe([
        (new Series)->indexableAs(),
        (new VodStream)->indexableAs(),
    ]);

    app(ReconcileSearchIndexes::class)();

    expect($backend->replacedIndexes)->toHaveCount(2);
});

it('rebuilds indexes with stale non-latest documents or mismatched counts', function (): void {
    seedSearchReconciliationMedia();

    $series = new Series;
    $movie = new VodStream;
    $backend = new ReconciliationFakeBackend([
        $series->indexableAs() => new SearchIndexState(
            true,
            Series::query()->count(),
            'stale-non-latest-document',
        ),
        $movie->indexableAs() => new SearchIndexState(
            true,
            VodStream::query()->count() - 1,
            searchReconciliationFingerprint(VodStream::class),
        ),
    ]);
    app()->instance(SearchIndexBackend::class, $backend);

    app(ReconcileSearchIndexes::class)();

    expect($backend->replacedIndexes)->toBe([
        $series->indexableAs(),
        $movie->indexableAs(),
    ]);
});

it('does not rebuild healthy indexes', function (): void {
    seedSearchReconciliationMedia();

    $states = [];

    foreach ([Series::class, VodStream::class] as $modelClass) {
        $model = new $modelClass;
        $states[$model->indexableAs()] = new SearchIndexState(
            true,
            $modelClass::query()->count(),
            searchReconciliationFingerprint($modelClass),
            config('scout.meilisearch.index-settings.'.$modelClass, []),
        );
    }

    $backend = new ReconciliationFakeBackend($states);
    app()->instance(SearchIndexBackend::class, $backend);

    app(ReconcileSearchIndexes::class)();

    expect($backend->replacedIndexes)->toBe([]);
});

function seedSearchReconciliationMedia(): void
{
    DB::table('series')->insert([
        [
            'series_id' => 50_001,
            'num' => 1,
            'name' => 'Older Reconciled Series',
            'last_modified' => now()->subMinute(),
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinute(),
        ],
        [
            'series_id' => 50_002,
            'num' => 2,
            'name' => 'Latest Reconciled Series',
            'last_modified' => now(),
            'created_at' => now()->subMinute(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('vod_streams')->insert([
        [
            'stream_id' => 60_001,
            'num' => 1,
            'name' => 'Older Reconciled Movie',
            'stream_type' => 'movie',
            'rating_5based' => 4.0,
            'added' => now()->subMinute()->toDateTimeString(),
            'is_adult' => false,
            'container_extension' => 'mp4',
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinute(),
        ],
        [
            'stream_id' => 60_002,
            'num' => 2,
            'name' => 'Latest Reconciled Movie',
            'stream_type' => 'movie',
            'rating_5based' => 4.5,
            'added' => now()->toDateTimeString(),
            'is_adult' => false,
            'container_extension' => 'mp4',
            'created_at' => now()->subMinute(),
            'updated_at' => now(),
        ],
    ]);
}

/**
 * @return array<string, mixed>
 */
function searchReconciliationDocument(Series|VodStream $model): array
{
    return [
        ...$model->toSearchableArray(),
        $model->getScoutKeyName() => $model->getScoutKey(),
    ];
}

/**
 * @param  class-string<Series|VodStream>  $modelClass
 */
function searchReconciliationFingerprint(string $modelClass): string
{
    $documents = $modelClass::query()
        ->orderBy((new $modelClass)->getKeyName())
        ->get()
        ->map(fn (Series|VodStream $model): array => searchReconciliationDocument($model));

    return app(SearchIndexFingerprint::class)->forDocuments($documents);
}

final class ReconciliationFakeBackend implements SearchIndexBackend
{
    /** @var list<string> */
    public array $replacedIndexes = [];

    /**
     * @param  array<string, SearchIndexState>  $states
     */
    public function __construct(public array $states) {}

    public function inspect(string $indexName): SearchIndexState
    {
        return $this->states[$indexName] ?? new SearchIndexState(false, 0, null);
    }

    public function replaceAtomically(
        string $indexName,
        string $primaryKey,
        array $settings,
        iterable $documentChunks,
        int $expectedDocuments,
    ): void {
        $documents = [];

        foreach ($documentChunks as $chunk) {
            array_push($documents, ...$chunk);
        }

        $this->replacedIndexes[] = $indexName;
        $this->states[$indexName] = new SearchIndexState(
            true,
            count($documents),
            app(SearchIndexFingerprint::class)->forDocuments($documents),
            $settings,
        );

        expect($documents)->toHaveCount($expectedDocuments);
    }
}
