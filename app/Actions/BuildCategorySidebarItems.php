<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Data\CategorySidebarItemData;
use App\Enums\MediaType;
use App\Models\Category;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Database\Eloquent\Builder;

final class BuildCategorySidebarItems
{
    use AsAction;

    public function __invoke(MediaType $mediaType, ?string $selectedCategoryId = null): array
    {
        [$categoryScopeColumn, $modelClass, $uncategorizedProviderId] = $this->resolveContext($mediaType);
        $selectedCategoryId = $this->normalizeCategoryId($selectedCategoryId);

        $categories = Category::query()
            ->where($categoryScopeColumn, true)
            ->get(['provider_id', 'name']);

        $countsByCategoryId = $modelClass::query()
            ->selectRaw('category_id, COUNT(*) as aggregate_count')
            ->whereNotNull('category_id')
            ->where('category_id', '!=', '')
            ->groupBy('category_id')
            ->pluck('aggregate_count', 'category_id');

        $uncategorizedCount = $modelClass::query()
            ->where(static function (Builder $query) use ($uncategorizedProviderId): void {
                $query
                    ->whereNull('category_id')
                    ->orWhere('category_id', '')
                    ->orWhere('category_id', $uncategorizedProviderId);
            })
            ->count();

        return $categories
            ->map(function (Category $category) use ($countsByCategoryId, $uncategorizedCount, $uncategorizedProviderId, $selectedCategoryId): CategorySidebarItemData {
                $isUncategorized = $category->provider_id === $uncategorizedProviderId;
                $count = $isUncategorized
                    ? $uncategorizedCount
                    : (int) ($countsByCategoryId[$category->provider_id] ?? 0);

                return new CategorySidebarItemData(
                    id: $category->provider_id,
                    name: $category->name,
                    disabled: $count === 0 && $category->provider_id !== $selectedCategoryId,
                    isUncategorized: $isUncategorized,
                );
            })
            ->sort(function (CategorySidebarItemData $left, CategorySidebarItemData $right): int {
                if ($left->isUncategorized !== $right->isUncategorized) {
                    return $left->isUncategorized ? 1 : -1;
                }

                return strcasecmp($left->name, $right->name);
            })
            ->values()
            ->all();
    }

    private function normalizeCategoryId(?string $selectedCategoryId): ?string
    {
        $normalized = trim((string) $selectedCategoryId);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveContext(MediaType $mediaType): array
    {
        if ($mediaType->isMovie()) {
            return ['in_vod', VodStream::class, Category::UNCATEGORIZED_VOD_PROVIDER_ID];
        }

        return ['in_series', Series::class, Category::UNCATEGORIZED_SERIES_PROVIDER_ID];
    }
}
