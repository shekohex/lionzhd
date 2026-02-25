<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Enums\CategorySyncRunStatus;
use App\Http\Integrations\LionzTv\Requests\GetSeriesCategoriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodCategoriesRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\CategorySyncRun;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final readonly class SyncCategories
{
    use AsAction;

    public function __construct(private XtreamCodesConnector $connector) {}

    public function __invoke(bool $forceEmptyVod = false, bool $forceEmptySeries = false, ?int $requestedByUserId = null): CategorySyncRun
    {
        $run = CategorySyncRun::query()->create([
            'requested_by_user_id' => $requestedByUserId,
            'status' => CategorySyncRunStatus::Running,
            'started_at' => now(),
            'summary' => $this->emptySummary(),
            'top_issues' => [],
        ]);

        try {
            $issues = [];
            $summary = $this->emptySummary();

            $this->ensureSystemCategories();

            $vod = $this->fetchAndNormalizeVodCategories($forceEmptyVod, $issues);
            $series = $this->fetchAndNormalizeSeriesCategories($forceEmptySeries, $issues);

            if ($vod['can_apply']) {
                $this->applySourceCategories($vod['categories'], 'vod', $summary);
            }

            if ($series['can_apply']) {
                $this->applySourceCategories($series['categories'], 'series', $summary);
            }

            if ($vod['can_apply'] && $series['can_apply']) {
                $this->cleanupMissingCategories(array_values(array_unique([
                    ...array_keys($vod['categories']),
                    ...array_keys($series['categories']),
                ])), $summary);
            }

            if ($vod['can_apply']) {
                $summary['moved_to_uncategorized_vod'] += $this->moveToUncategorized(
                    VodStream::class,
                    'stream_id',
                    Category::UNCATEGORIZED_VOD_PROVIDER_ID,
                    array_keys($vod['categories']),
                );

                $summary['remapped_from_uncategorized_vod'] += $this->remapFromUncategorized(
                    VodStream::class,
                    'stream_id',
                    Category::UNCATEGORIZED_VOD_PROVIDER_ID,
                    array_keys($vod['categories']),
                );
            }

            if ($series['can_apply']) {
                $summary['moved_to_uncategorized_series'] += $this->moveToUncategorized(
                    Series::class,
                    'series_id',
                    Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
                    array_keys($series['categories']),
                );

                $summary['remapped_from_uncategorized_series'] += $this->remapFromUncategorized(
                    Series::class,
                    'series_id',
                    Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
                    array_keys($series['categories']),
                );
            }

            $run->forceFill([
                'status' => $this->resolveStatus($issues, $vod['succeeded'], $series['succeeded']),
                'finished_at' => now(),
                'summary' => $summary,
                'top_issues' => $issues,
            ])->save();

            return $run->fresh() ?? $run;
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => CategorySyncRunStatus::Failed,
                'finished_at' => now(),
                'top_issues' => [$exception->getMessage()],
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return array{created:int,updated:int,removed:int,moved_to_uncategorized_vod:int,moved_to_uncategorized_series:int,remapped_from_uncategorized_vod:int,remapped_from_uncategorized_series:int}
     */
    private function emptySummary(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'moved_to_uncategorized_vod' => 0,
            'moved_to_uncategorized_series' => 0,
            'remapped_from_uncategorized_vod' => 0,
            'remapped_from_uncategorized_series' => 0,
        ];
    }

    private function ensureSystemCategories(): void
    {
        Category::query()->updateOrCreate(
            ['provider_id' => Category::UNCATEGORIZED_VOD_PROVIDER_ID],
            [
                'name' => 'Uncategorized',
                'in_vod' => true,
                'in_series' => false,
                'is_system' => true,
            ],
        );

        Category::query()->updateOrCreate(
            ['provider_id' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID],
            [
                'name' => 'Uncategorized',
                'in_vod' => false,
                'in_series' => true,
                'is_system' => true,
            ],
        );
    }

    /**
     * @param list<string> $issues
     * @return array{succeeded:bool,can_apply:bool,categories:array<string,string>}
     */
    private function fetchAndNormalizeVodCategories(bool $forceEmptyVod, array &$issues): array
    {
        try {
            /** @var array<int, array<string, mixed>> $payload */
            $payload = $this->connector->send(new GetVodCategoriesRequest)->dtoOrFail();

            return $this->normalizeSourceCategories($payload, 'VOD', $forceEmptyVod, $issues);
        } catch (Throwable $exception) {
            $this->addIssue($issues, sprintf('VOD categories fetch failed: %s', $exception->getMessage()));

            return [
                'succeeded' => false,
                'can_apply' => false,
                'categories' => [],
            ];
        }
    }

    /**
     * @param list<string> $issues
     * @return array{succeeded:bool,can_apply:bool,categories:array<string,string>}
     */
    private function fetchAndNormalizeSeriesCategories(bool $forceEmptySeries, array &$issues): array
    {
        try {
            /** @var array<int, array<string, mixed>> $payload */
            $payload = $this->connector->send(new GetSeriesCategoriesRequest)->dtoOrFail();

            return $this->normalizeSourceCategories($payload, 'Series', $forceEmptySeries, $issues);
        } catch (Throwable $exception) {
            $this->addIssue($issues, sprintf('Series categories fetch failed: %s', $exception->getMessage()));

            return [
                'succeeded' => false,
                'can_apply' => false,
                'categories' => [],
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     * @param list<string> $issues
     * @return array{succeeded:bool,can_apply:bool,categories:array<string,string>}
     */
    private function normalizeSourceCategories(array $payload, string $sourceName, bool $forceEmpty, array &$issues): array
    {
        $categories = [];

        foreach ($payload as $index => $row) {
            $providerId = trim($this->stringify($row['category_id'] ?? null));

            if ($providerId === '') {
                $this->addIssue($issues, sprintf('%s row #%d skipped: missing category_id', $sourceName, $index + 1));

                continue;
            }

            $name = $this->stringify($row['category_name'] ?? $row['name'] ?? '');

            if (trim($name) === '') {
                $this->addIssue($issues, sprintf('%s category %s has empty name', $sourceName, $providerId));
            }

            $categories[$providerId] = $name;
        }

        if ($categories === [] && ! $forceEmpty) {
            $this->addIssue($issues, sprintf('%s categories returned empty list; explicit confirmation is required to apply destructive changes', $sourceName));
        }

        return [
            'succeeded' => true,
            'can_apply' => $categories !== [] || $forceEmpty,
            'categories' => $categories,
        ];
    }

    /**
     * @param array<string,string> $categories
     * @param array{created:int,updated:int,removed:int,moved_to_uncategorized_vod:int,moved_to_uncategorized_series:int,remapped_from_uncategorized_vod:int,remapped_from_uncategorized_series:int} $summary
     */
    private function applySourceCategories(array $categories, string $source, array &$summary): void
    {
        foreach ($categories as $providerId => $name) {
            $category = Category::query()->firstOrNew([
                'provider_id' => $providerId,
            ]);

            $exists = $category->exists;

            if (! $exists) {
                $category->in_vod = false;
                $category->in_series = false;
                $category->is_system = false;
            }

            $category->name = $name;

            if ($source === 'vod') {
                $category->in_vod = true;
            }

            if ($source === 'series') {
                $category->in_series = true;
            }

            if (! $category->isDirty()) {
                continue;
            }

            $category->save();

            if ($exists) {
                $summary['updated']++;
            } else {
                $summary['created']++;
            }
        }

        $this->clearMissingSourceFlag(array_keys($categories), $source, $summary);
    }

    /**
     * @param list<string> $providerIds
     * @param array{created:int,updated:int,removed:int,moved_to_uncategorized_vod:int,moved_to_uncategorized_series:int,remapped_from_uncategorized_vod:int,remapped_from_uncategorized_series:int} $summary
     */
    private function clearMissingSourceFlag(array $providerIds, string $source, array &$summary): void
    {
        $column = $source === 'vod' ? 'in_vod' : 'in_series';
        $query = Category::query()
            ->where('is_system', false)
            ->where($column, true);

        if ($providerIds !== []) {
            $query->whereNotIn('provider_id', $providerIds);
        }

        $summary['updated'] += $query->update([
            $column => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param list<string> $unionProviderIds
     * @param array{created:int,updated:int,removed:int,moved_to_uncategorized_vod:int,moved_to_uncategorized_series:int,remapped_from_uncategorized_vod:int,remapped_from_uncategorized_series:int} $summary
     */
    private function cleanupMissingCategories(array $unionProviderIds, array &$summary): void
    {
        $query = Category::query()->where('is_system', false);

        if ($unionProviderIds !== []) {
            $query->whereNotIn('provider_id', $unionProviderIds);
        }

        $summary['removed'] += $query->delete();
    }

    /**
     * @param class-string<Series|VodStream> $modelClass
     * @param list<string> $validProviderIds
     */
    private function moveToUncategorized(string $modelClass, string $idColumn, string $uncategorizedProviderId, array $validProviderIds): int
    {
        $moved = 0;

        $modelClass::query()
            ->select([$idColumn, 'category_id', 'previous_category_id'])
            ->where(function (Builder $query) use ($uncategorizedProviderId, $validProviderIds): void {
                $query
                    ->whereNull('category_id')
                    ->orWhere('category_id', '');

                if ($validProviderIds === []) {
                    $query->orWhere(function (Builder $inner) use ($uncategorizedProviderId): void {
                        $inner->whereNotNull('category_id')
                            ->where('category_id', '!=', '')
                            ->where('category_id', '!=', $uncategorizedProviderId);
                    });

                    return;
                }

                $query->orWhere(function (Builder $inner) use ($uncategorizedProviderId, $validProviderIds): void {
                    $inner->whereNotNull('category_id')
                        ->where('category_id', '!=', '')
                        ->where('category_id', '!=', $uncategorizedProviderId)
                        ->whereNotIn('category_id', $validProviderIds);
                });
            })
            ->orderBy($idColumn)
            ->chunkById(500, function ($rows) use ($modelClass, $idColumn, $uncategorizedProviderId, &$moved): void {
                foreach ($rows as $row) {
                    $previousCategoryId = $row->category_id;

                    if (! is_string($previousCategoryId) || $previousCategoryId === '') {
                        $previousCategoryId = $row->previous_category_id;
                    }

                    $moved += $modelClass::query()
                        ->where($idColumn, $row->{$idColumn})
                        ->update([
                            'category_id' => $uncategorizedProviderId,
                            'previous_category_id' => $previousCategoryId,
                        ]);
                }
            }, $idColumn, $idColumn);

        return $moved;
    }

    /**
     * @param class-string<Series|VodStream> $modelClass
     * @param list<string> $validProviderIds
     */
    private function remapFromUncategorized(string $modelClass, string $idColumn, string $uncategorizedProviderId, array $validProviderIds): int
    {
        if ($validProviderIds === []) {
            return 0;
        }

        $remapped = 0;

        $modelClass::query()
            ->select([$idColumn, 'previous_category_id'])
            ->where('category_id', $uncategorizedProviderId)
            ->whereNotNull('previous_category_id')
            ->whereIn('previous_category_id', $validProviderIds)
            ->orderBy($idColumn)
            ->chunkById(500, function ($rows) use ($modelClass, $idColumn, &$remapped): void {
                foreach ($rows as $row) {
                    $remapped += $modelClass::query()
                        ->where($idColumn, $row->{$idColumn})
                        ->update([
                            'category_id' => $row->previous_category_id,
                            'previous_category_id' => null,
                        ]);
                }
            }, $idColumn, $idColumn);

        return $remapped;
    }

    /**
     * @param list<string> $issues
     */
    private function resolveStatus(array $issues, bool $vodSucceeded, bool $seriesSucceeded): CategorySyncRunStatus
    {
        if (! $vodSucceeded && ! $seriesSucceeded) {
            return CategorySyncRunStatus::Failed;
        }

        if ($issues !== []) {
            return CategorySyncRunStatus::SuccessWithWarnings;
        }

        return CategorySyncRunStatus::Success;
    }

    /**
     * @param list<string> $issues
     */
    private function addIssue(array &$issues, string $issue): void
    {
        if (count($issues) < 20) {
            $issues[] = $issue;
        }
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
