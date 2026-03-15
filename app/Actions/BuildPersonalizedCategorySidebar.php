<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Data\CategorySidebarData;
use App\Data\CategorySidebarItemData;
use App\Enums\MediaType;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\VodStream;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelData\DataCollection;

final class BuildPersonalizedCategorySidebar
{
    use AsAction;

    private const string ALL_CATEGORIES_ID = 'all-categories';

    private const int PIN_LIMIT = 5;

    public function __invoke(User $user, MediaType $mediaType, ?string $selectedCategoryId = null): CategorySidebarData
    {
        [$categoryScopeColumn, $modelClass, $uncategorizedProviderId] = $this->resolveContext($mediaType);
        $selectedCategoryId = $this->normalizeCategoryId($selectedCategoryId);

        $categories = Category::query()
            ->where($categoryScopeColumn, true)
            ->get(['provider_id', 'name', 'is_system'])
            ->sort(static function (Category $left, Category $right) use ($uncategorizedProviderId): int {
                if ($left->provider_id === $uncategorizedProviderId || $right->provider_id === $uncategorizedProviderId) {
                    return $left->provider_id === $uncategorizedProviderId ? 1 : -1;
                }

                return strcasecmp($left->name, $right->name);
            })
            ->values();

        $preferences = UserCategoryPreference::query()
            ->where('user_id', $user->getKey())
            ->where('media_type', $mediaType->value)
            ->get()
            ->keyBy('category_provider_id');

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

        $nextSortOrder = (int) $preferences
            ->filter(static fn (UserCategoryPreference $preference): bool => $preference->pin_rank === null)
            ->max('sort_order');

        $nextSortOrder++;

        $items = $categories
            ->map(function (Category $category, int $canonicalIndex) use ($preferences, $countsByCategoryId, $uncategorizedCount, $uncategorizedProviderId, $selectedCategoryId, &$nextSortOrder): array {
                $isUncategorized = $category->provider_id === $uncategorizedProviderId;
                $count = $isUncategorized
                    ? $uncategorizedCount
                    : (int) ($countsByCategoryId[$category->provider_id] ?? 0);

                $preference = $isUncategorized ? null : $preferences->get($category->provider_id);
                $pinRank = $preference?->pin_rank;
                $sortOrder = $preference?->sort_order ?? $nextSortOrder++;
                $isHidden = (bool) ($preference?->is_hidden ?? false);
                $canNavigate = $count > 0 || $category->provider_id === $selectedCategoryId;
                $canEdit = ! $isUncategorized;

                return [
                    'item' => new CategorySidebarItemData(
                        id: $category->provider_id,
                        name: $category->name,
                        disabled: ! $canNavigate,
                        canNavigate: $canNavigate,
                        canEdit: $canEdit,
                        isPinned: $pinRank !== null,
                        isHidden: $isHidden,
                        pinRank: $pinRank,
                        sortOrder: $isUncategorized ? null : $sortOrder,
                        isUncategorized: $isUncategorized,
                    ),
                    'canonical_index' => $canonicalIndex,
                    'pin_rank' => $pinRank,
                    'sort_order' => $sortOrder,
                    'is_hidden' => $isHidden,
                ];
            })
            ->values();

        $visibleItems = $items
            ->reject(static fn (array $payload): bool => $payload['is_hidden'])
            ->sort(static function (array $left, array $right): int {
                /** @var CategorySidebarItemData $leftItem */
                $leftItem = $left['item'];
                /** @var CategorySidebarItemData $rightItem */
                $rightItem = $right['item'];

                if ($leftItem->isUncategorized !== $rightItem->isUncategorized) {
                    return $leftItem->isUncategorized ? 1 : -1;
                }

                $leftPinned = $left['pin_rank'] !== null;
                $rightPinned = $right['pin_rank'] !== null;

                if ($leftPinned !== $rightPinned) {
                    return $leftPinned ? -1 : 1;
                }

                if ($leftPinned && $rightPinned) {
                    $pinOrder = $left['pin_rank'] <=> $right['pin_rank'];

                    if ($pinOrder !== 0) {
                        return $pinOrder;
                    }
                }

                $sortOrder = $left['sort_order'] <=> $right['sort_order'];

                if ($sortOrder !== 0) {
                    return $sortOrder;
                }

                return $left['canonical_index'] <=> $right['canonical_index'];
            })
            ->map(static fn (array $payload): CategorySidebarItemData => $payload['item'])
            ->values()
            ->prepend($this->allCategoriesItem())
            ->values();

        $hiddenItems = $items
            ->filter(static fn (array $payload): bool => $payload['is_hidden'])
            ->sort(static function (array $left, array $right): int {
                $leftPinned = $left['pin_rank'] !== null;
                $rightPinned = $right['pin_rank'] !== null;

                if ($leftPinned !== $rightPinned) {
                    return $leftPinned ? -1 : 1;
                }

                if ($leftPinned && $rightPinned) {
                    $pinOrder = $left['pin_rank'] <=> $right['pin_rank'];

                    if ($pinOrder !== 0) {
                        return $pinOrder;
                    }
                }

                $sortOrder = $left['sort_order'] <=> $right['sort_order'];

                if ($sortOrder !== 0) {
                    return $sortOrder;
                }

                return $left['canonical_index'] <=> $right['canonical_index'];
            })
            ->map(static fn (array $payload): CategorySidebarItemData => $payload['item'])
            ->values();

        $selectedHiddenItem = $hiddenItems->first(static fn (CategorySidebarItemData $item): bool => $item->id === $selectedCategoryId);

        return new CategorySidebarData(
            visibleItems: CategorySidebarItemData::collect($visibleItems, DataCollection::class),
            hiddenItems: CategorySidebarItemData::collect($hiddenItems, DataCollection::class),
            selectedCategoryIsHidden: $selectedHiddenItem instanceof CategorySidebarItemData,
            selectedCategoryName: $selectedHiddenItem instanceof CategorySidebarItemData ? $selectedHiddenItem->name : null,
            pinLimit: self::PIN_LIMIT,
            canReset: $preferences->isNotEmpty(),
        );
    }

    private function allCategoriesItem(): CategorySidebarItemData
    {
        return new CategorySidebarItemData(
            id: self::ALL_CATEGORIES_ID,
            name: 'All categories',
            disabled: false,
            canNavigate: true,
            canEdit: false,
            isPinned: false,
            isHidden: false,
            pinRank: null,
            sortOrder: null,
            isUncategorized: false,
        );
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
