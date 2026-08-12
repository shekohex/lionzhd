<?php

declare(strict_types=1);

use App\Actions\ReconcileSearchIndexes;
use App\Contracts\SearchIndexBackend;
use App\Data\SearchIndexState;
use App\Models\Series;
use App\Models\VodStream;
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
        (new Series)->indexableAs() => new SearchIndexState(false, 0, [], null),
        (new VodStream)->indexableAs() => new SearchIndexState(true, 0, [], null),
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

it('rebuilds indexes with stale documents or mismatched counts', function (): void {
    seedSearchReconciliationMedia();

    $series = Series::query()->latest('updated_at')->latest('series_id')->firstOrFail();
    $movie = VodStream::query()->latest('updated_at')->latest('stream_id')->firstOrFail();
    $backend = new ReconciliationFakeBackend([
        $series->indexableAs() => new SearchIndexState(
            true,
            Series::query()->count(),
            array_keys(searchReconciliationDocument($series)),
            [...searchReconciliationDocument($series), 'name' => 'Stale Series'],
        ),
        $movie->indexableAs() => new SearchIndexState(
            true,
            VodStream::query()->count() - 1,
            array_keys(searchReconciliationDocument($movie)),
            searchReconciliationDocument($movie),
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
        $model = $modelClass::query()->latest('updated_at')->latest((new $modelClass)->getKeyName())->firstOrFail();
        $document = searchReconciliationDocument($model);
        $states[$model->indexableAs()] = new SearchIndexState(
            true,
            $modelClass::query()->count(),
            array_keys($document),
            $document,
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
        'series_id' => 50_001,
        'num' => 1,
        'name' => 'Reconciled Series',
        'last_modified' => now(),
        'created_at' => now()->subMinute(),
        'updated_at' => now(),
    ]);

    DB::table('vod_streams')->insert([
        'stream_id' => 60_001,
        'num' => 1,
        'name' => 'Reconciled Movie',
        'stream_type' => 'movie',
        'rating_5based' => 4.0,
        'added' => now()->toDateTimeString(),
        'is_adult' => false,
        'container_extension' => 'mp4',
        'created_at' => now()->subMinute(),
        'updated_at' => now(),
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

final class ReconciliationFakeBackend implements SearchIndexBackend
{
    /** @var list<string> */
    public array $replacedIndexes = [];

    /**
     * @param  array<string, SearchIndexState>  $states
     */
    public function __construct(public array $states) {}

    public function inspect(string $indexName, int|string|null $sampleId = null): SearchIndexState
    {
        return $this->states[$indexName] ?? new SearchIndexState(false, 0, [], null);
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
        $sample = collect($documents)->sortByDesc('updated_at')->sortByDesc($primaryKey)->first();
        $this->states[$indexName] = new SearchIndexState(
            true,
            count($documents),
            array_keys($sample ?? []),
            $sample,
            $settings,
        );

        expect($documents)->toHaveCount($expectedDocuments);
    }
}
