<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\DetailPageCategoryChipData;
use App\Enums\MediaType;
use App\Models\Category;
use App\Models\MediaCategoryAssignment;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Support\Facades\DB;

final class ResolveDetailPageCategories
{
    /**
     * @return array<int, DetailPageCategoryChipData>
     */
    public function forMovie(VodStream $movie): array
    {
        return $this->resolve(
            mediaType: MediaType::Movie,
            mediaProviderId: (string) $movie->getKey(),
            routeName: 'movies',
            uncategorizedProviderId: Category::UNCATEGORIZED_VOD_PROVIDER_ID,
        );
    }

    /**
     * @return array<int, DetailPageCategoryChipData>
     */
    public function forSeries(Series $series): array
    {
        return $this->resolve(
            mediaType: MediaType::Series,
            mediaProviderId: (string) $series->getKey(),
            routeName: 'series',
            uncategorizedProviderId: Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
        );
    }

    /**
     * @return array<int, DetailPageCategoryChipData>
     */
    private function resolve(MediaType $mediaType, string $mediaProviderId, string $routeName, string $uncategorizedProviderId): array
    {
        $assignments = MediaCategoryAssignment::query()
            ->where('media_type', $mediaType->value)
            ->where('media_provider_id', $mediaProviderId)
            ->orderBy('source_order')
            ->orderBy('category_provider_id')
            ->get(['category_provider_id', 'source_order']);

        $assignmentCategoryIds = $assignments
            ->pluck('category_provider_id')
            ->filter(static fn (mixed $categoryProviderId): bool => is_string($categoryProviderId) && trim($categoryProviderId) !== '')
            ->map(static fn (string $categoryProviderId): string => trim($categoryProviderId))
            ->values();

        if ($assignmentCategoryIds->isEmpty() || $assignmentCategoryIds->every(static fn (string $categoryProviderId): bool => $categoryProviderId === $uncategorizedProviderId)) {
            $uncategorizedChip = $this->resolveUncategorizedChip($mediaType, $routeName, $uncategorizedProviderId);

            return $uncategorizedChip === null ? [] : [$uncategorizedChip];
        }

        $syncOrderColumn = $mediaType->isMovie() ? 'vod_sync_order' : 'series_sync_order';
        $availabilityColumn = $mediaType->isMovie() ? 'in_vod' : 'in_series';

        return DB::table('categories')
            ->join('media_category_assignments', 'media_category_assignments.category_provider_id', '=', 'categories.provider_id')
            ->where('media_category_assignments.media_type', $mediaType->value)
            ->where('media_category_assignments.media_provider_id', $mediaProviderId)
            ->where("categories.{$availabilityColumn}", true)
            ->select([
                'categories.provider_id',
                'categories.name',
                "categories.{$syncOrderColumn} as sync_order",
                'media_category_assignments.source_order',
            ])
            ->get()
            ->sort(static function (object $left, object $right): int {
                $leftHasSyncOrder = $left->sync_order !== null;
                $rightHasSyncOrder = $right->sync_order !== null;

                if ($leftHasSyncOrder !== $rightHasSyncOrder) {
                    return $leftHasSyncOrder ? -1 : 1;
                }

                if ($leftHasSyncOrder && $rightHasSyncOrder) {
                    $syncOrderComparison = $left->sync_order <=> $right->sync_order;

                    if ($syncOrderComparison !== 0) {
                        return $syncOrderComparison;
                    }

                    $sourceOrderComparison = $left->source_order <=> $right->source_order;

                    if ($sourceOrderComparison !== 0) {
                        return $sourceOrderComparison;
                    }
                }

                return strcmp((string) $left->provider_id, (string) $right->provider_id);
            })
            ->values()
            ->map(fn (object $category): DetailPageCategoryChipData => new DetailPageCategoryChipData(
                id: (string) $category->provider_id,
                name: (string) $category->name,
                href: route($routeName, ['category' => (string) $category->provider_id]),
            ))
            ->all();
    }

    private function resolveUncategorizedChip(MediaType $mediaType, string $routeName, string $uncategorizedProviderId): ?DetailPageCategoryChipData
    {
        $availabilityColumn = $mediaType->isMovie() ? 'in_vod' : 'in_series';

        $category = Category::query()
            ->where('provider_id', $uncategorizedProviderId)
            ->where($availabilityColumn, true)
            ->first(['provider_id', 'name']);

        if ($category === null) {
            return null;
        }

        return new DetailPageCategoryChipData(
            id: $category->provider_id,
            name: $category->name,
            href: route($routeName, ['category' => $category->provider_id]),
        );
    }
}
