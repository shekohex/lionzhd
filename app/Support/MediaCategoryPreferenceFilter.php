<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MediaType;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Database\Eloquent\Builder;

final class MediaCategoryPreferenceFilter
{
    public static function apply(Builder $query, User $user, MediaType $mediaType, ?string $categoryId): Builder
    {
        $preferenceCategoryIds = self::preferenceCategoryIds($user, $mediaType);

        return $query
            ->when($categoryId === null && $preferenceCategoryIds['hidden'] !== [], static function (Builder $query) use ($preferenceCategoryIds): void {
                self::whereCategoryNotInPreservingUncategorized($query, $preferenceCategoryIds['hidden']);
            })
            ->when($preferenceCategoryIds['ignored'] !== [], static function (Builder $query) use ($preferenceCategoryIds): void {
                self::whereCategoryNotInPreservingUncategorized($query, $preferenceCategoryIds['ignored']);
            })
            ->when($categoryId !== null, static function (Builder $query) use ($categoryId, $mediaType): void {
                $uncategorizedProviderId = $mediaType === MediaType::Movie
                    ? Category::UNCATEGORIZED_VOD_PROVIDER_ID
                    : Category::UNCATEGORIZED_SERIES_PROVIDER_ID;

                if ($categoryId === $uncategorizedProviderId) {
                    $query->where(static function (Builder $innerQuery) use ($categoryId): void {
                        $innerQuery
                            ->whereNull('category_id')
                            ->orWhere('category_id', '')
                            ->orWhere('category_id', $categoryId);
                    });

                    return;
                }

                $query->where('category_id', $categoryId);
            });
    }

    /**
     * @param  list<string>  $categoryIds
     */
    private static function whereCategoryNotInPreservingUncategorized(Builder $query, array $categoryIds): void
    {
        $query->where(static function (Builder $innerQuery) use ($categoryIds): void {
            $innerQuery
                ->whereNull('category_id')
                ->orWhere('category_id', '')
                ->orWhereNotIn('category_id', $categoryIds);
        });
    }

    /**
     * @return array{hidden: list<string>, ignored: list<string>}
     */
    private static function preferenceCategoryIds(User $user, MediaType $mediaType): array
    {
        $preferences = UserCategoryPreference::query()
            ->where('user_id', $user->getKey())
            ->where('media_type', $mediaType->value)
            ->get(['category_provider_id', 'is_hidden', 'is_ignored']);

        $resolveIds = static fn (string $column): array => $preferences
            ->where($column, true)
            ->pluck('category_provider_id')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        return [
            'hidden' => $resolveIds('is_hidden'),
            'ignored' => $resolveIds('is_ignored'),
        ];
    }
}
