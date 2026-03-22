<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaCategoryAssignment;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use Throwable;

/**
 * Sync media content from Xtream Codes API
 *
 * @method static void run()
 */
final readonly class SyncMedia
{
    use AsAction;

    public function __construct(private XtreamCodesConnector $connector) {}

    /**
     * Execute the action.
     */
    public function __invoke(): void
    {
        $this->removeAllFromSearchSafely();

        Telescope::withoutRecording(function (): void {
            Log::debug('Fetching series from Xtream Codes API');
            /** @var array<int, array<string, mixed>> $series */
            $series = $this->connector->send(new GetSeriesRequest)->dtoOrFail();

            $this->syncSeries($series);

            unset($series);
            gc_collect_cycles();

            Log::debug('Fetching VOD streams from Xtream Codes API');
            /** @var array<int, array<string, mixed>> $vodStreams */
            $vodStreams = $this->connector->send(new GetVodStreamsRequest)->dtoOrFail();

            $this->syncVodStreams($vodStreams);
        });

        $this->bustXtreamDtoCacheNamespace();
        $this->makeAllSearchableSafely();
        Log::info('All media contents have been refreshed');
    }

    private function bustXtreamDtoCacheNamespace(): void
    {
        $cache = Cache::store();
        $key = XtreamCodesConnector::DTO_CACHE_NAMESPACE_KEY;
        $version = $cache->increment($key);

        if ($version === false) {
            $cache->forever($key, ((int) $cache->get($key, 0)) + 1);
        }
    }

    private function removeAllFromSearchSafely(): void
    {
        try {
            Log::debug('Removing items from search index');
            VodStream::removeAllFromSearch();
            Series::removeAllFromSearch();
        } catch (Throwable $exception) {
            Log::warning('Skipping search index cleanup', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function makeAllSearchableSafely(): void
    {
        try {
            Log::debug('Marking series as searchable');
            Series::makeAllSearchable(3000);
            Log::debug('Marking VOD streams as searchable');
            VodStream::makeAllSearchable(3000);
        } catch (Throwable $exception) {
            Log::warning('Skipping search indexing', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $series
     */
    private function syncSeries(array $series): void
    {
        $this->pruneMissingRows(Series::class, 'series_id', 'series', $series);

        foreach (array_chunk($series, 1000) as $chunk) {
            $assignments = $this->buildAssignmentsForChunk($chunk, 'series', 'series_id');

            $saved = Series::query()->upsert(
                $this->sanitizeSeriesChunk($chunk),
                ['series_id'],
                [
                    'num', 'name', 'cover', 'plot', 'cast',
                    'director', 'genre', 'releaseDate', 'last_modified',
                    'rating', 'rating_5based', 'backdrop_path',
                    'youtube_trailer', 'episode_run_time', 'category_id',
                ]
            );

            $this->syncAssignments($assignments, 'series');

            if ($saved) {
                Log::debug('Saved series chunk');
            } else {
                Log::warning('Failed to save series chunk');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $vodStreams
     */
    private function syncVodStreams(array $vodStreams): void
    {
        foreach (array_chunk($vodStreams, 1000) as $chunk) {
            $assignments = $this->buildAssignmentsForChunk($chunk, 'vod', 'stream_id');

            $saved = VodStream::query()->upsert(
                $this->sanitizeVodChunk($chunk),
                ['stream_id'],
                [
                    'num',
                    'name',
                    'stream_type',
                    'stream_icon',
                    'rating',
                    'rating_5based',
                    'added',
                    'is_adult',
                    'category_id',
                    'container_extension',
                    'custom_sid',
                    'direct_source',
                ]
            );

            $this->syncAssignments($assignments, 'vod');

            if ($saved) {
                Log::debug('Saved VOD stream chunk');
            } else {
                Log::warning('Failed to save VOD stream chunk');
            }
        }

        $this->pruneMissingRows(VodStream::class, 'stream_id', 'vod', $vodStreams);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function pruneMissingRows(string $modelClass, string $key, string $mediaType, array $records): void
    {
        $activeIds = [];

        foreach ($records as $record) {
            $id = $record[$key] ?? null;

            if ($id === null) {
                continue;
            }

            $activeIds[(string) $id] = true;
        }

        $deleted = 0;

        $modelClass::query()
            ->select($key)
            ->orderBy($key)
            ->chunkById(1000, function ($rows) use ($activeIds, &$deleted, $key, $mediaType, $modelClass): void {
                $staleIds = $rows
                    ->pluck($key)
                    ->filter(fn (mixed $id): bool => ! isset($activeIds[(string) $id]))
                    ->values()
                    ->all();

                if ($staleIds === []) {
                    return;
                }

                MediaCategoryAssignment::query()
                    ->where('media_type', $mediaType)
                    ->whereIn('media_provider_id', array_map('strval', $staleIds))
                    ->delete();

                $deleted += $modelClass::query()->whereIn($key, $staleIds)->delete();
            }, $key, $key);

        Log::debug('Pruned stale media rows', [
            'model' => $modelClass,
            'deleted' => $deleted,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $chunk
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeSeriesChunk(array $chunk): array
    {
        return array_map(fn (array $row): array => Arr::only($row, [
            'series_id',
            'num',
            'name',
            'cover',
            'plot',
            'cast',
            'director',
            'genre',
            'releaseDate',
            'last_modified',
            'rating',
            'rating_5based',
            'backdrop_path',
            'youtube_trailer',
            'episode_run_time',
            'category_id',
        ]), $chunk);
    }

    /**
     * @param array<int, array<string, mixed>> $chunk
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeVodChunk(array $chunk): array
    {
        return array_map(fn (array $row): array => Arr::only($row, [
            'stream_id',
            'num',
            'name',
            'stream_type',
            'stream_icon',
            'rating',
            'rating_5based',
            'added',
            'is_adult',
            'category_id',
            'container_extension',
            'custom_sid',
            'direct_source',
        ]), $chunk);
    }

    /**
     * @param array<int, array<string, mixed>> $chunk
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildAssignmentsForChunk(array $chunk, string $mediaType, string $providerKey): array
    {
        $assignments = [];

        foreach ($chunk as $row) {
            $mediaProviderId = $this->stringify($row[$providerKey] ?? null);

            if ($mediaProviderId === '') {
                continue;
            }

            $resolvedCategoryIds = $this->resolveAssignedCategoryIds($row);

            $assignments[$mediaProviderId] = array_map(
                fn (array $assignment): array => [
                    'media_type' => $mediaType,
                    'media_provider_id' => $mediaProviderId,
                    'category_provider_id' => $assignment['category_provider_id'],
                    'source_order' => $assignment['source_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $resolvedCategoryIds,
            );
        }

        return $assignments;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $assignmentsByMedia
     */
    private function syncAssignments(array $assignmentsByMedia, string $mediaType): void
    {
        if ($assignmentsByMedia === []) {
            return;
        }

        $mediaProviderIds = array_keys($assignmentsByMedia);

        MediaCategoryAssignment::query()
            ->where('media_type', $mediaType)
            ->whereIn('media_provider_id', $mediaProviderIds)
            ->delete();

        $rows = [];

        foreach ($assignmentsByMedia as $assignmentRows) {
            foreach ($assignmentRows as $assignmentRow) {
                $rows[] = $assignmentRow;
            }
        }

        if ($rows !== []) {
            MediaCategoryAssignment::query()->insert($rows);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array{category_provider_id:string,source_order:int}>
     */
    private function resolveAssignedCategoryIds(array $row): array
    {
        $richCategoryIds = $this->normalizeCategoryIdList($row['category_ids'] ?? null);

        if ($richCategoryIds !== []) {
            return $richCategoryIds;
        }

        $fallbackCategoryId = trim($this->stringify($row['category_id'] ?? null));

        if ($fallbackCategoryId === '') {
            return [];
        }

        return [[
            'category_provider_id' => $fallbackCategoryId,
            'source_order' => 0,
        ]];
    }

    /**
     * @return list<array{category_provider_id:string,source_order:int}>
     */
    private function normalizeCategoryIdList(mixed $value): array
    {
        $rawCategoryIds = [];

        if (is_array($value)) {
            $rawCategoryIds = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $rawCategoryIds = $decoded;
            } elseif (trim($value) !== '') {
                $rawCategoryIds = explode(',', $value);
            }
        }

        if ($rawCategoryIds === []) {
            return [];
        }

        $assignments = [];
        $seen = [];

        foreach ($rawCategoryIds as $rawCategoryId) {
            $categoryProviderId = trim($this->stringify($rawCategoryId));

            if ($categoryProviderId === '' || isset($seen[$categoryProviderId])) {
                continue;
            }

            $seen[$categoryProviderId] = true;
            $assignments[] = [
                'category_provider_id' => $categoryProviderId,
                'source_order' => count($assignments),
            ];
        }

        return $assignments;
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
