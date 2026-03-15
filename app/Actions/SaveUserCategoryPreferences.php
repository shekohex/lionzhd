<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Enums\MediaType;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Support\Facades\DB;

final class SaveUserCategoryPreferences
{
    use AsAction;

    public function __invoke(User $user, MediaType $mediaType, array $pinnedIds, array $visibleIds, array $hiddenIds): void
    {
        $snapshotIds = array_values(array_unique(array_merge($visibleIds, $hiddenIds)));
        $existingPreferences = UserCategoryPreference::query()
            ->where('user_id', $user->id)
            ->where('media_type', $mediaType->value)
            ->get()
            ->keyBy('category_provider_id');

        $sortOrderMap = $this->buildSortOrderMap($existingPreferences->all(), $pinnedIds, $visibleIds, $hiddenIds);
        $hiddenLookup = array_fill_keys($hiddenIds, true);
        $rows = [];
        $timestamp = now();

        foreach ($snapshotIds as $categoryProviderId) {
            $existingPreference = $existingPreferences->get($categoryProviderId);

            $rows[] = [
                'user_id' => $user->id,
                'media_type' => $mediaType->value,
                'category_provider_id' => $categoryProviderId,
                'pin_rank' => ($rank = array_search($categoryProviderId, $pinnedIds, true)) === false ? null : $rank + 1,
                'sort_order' => $sortOrderMap[$categoryProviderId],
                'is_hidden' => array_key_exists($categoryProviderId, $hiddenLookup),
                'created_at' => $existingPreference?->created_at ?? $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::transaction(function () use ($user, $mediaType, $snapshotIds, $rows): void {
            $query = UserCategoryPreference::query()
                ->where('user_id', $user->id)
                ->where('media_type', $mediaType->value);

            if ($snapshotIds === []) {
                $query->delete();

                return;
            }

            $query
                ->whereNotIn('category_provider_id', $snapshotIds)
                ->delete();

            UserCategoryPreference::query()->upsert(
                $rows,
                ['user_id', 'media_type', 'category_provider_id'],
                ['pin_rank', 'sort_order', 'is_hidden', 'updated_at'],
            );
        }, attempts: 5);
    }

    private function buildSortOrderMap(array $existingPreferences, array $pinnedIds, array $visibleIds, array $hiddenIds): array
    {
        $sortOrderMap = [];
        $nextSortOrder = 1;
        $nonPinnedIds = array_values(array_filter(
            array_merge($visibleIds, $hiddenIds),
            static fn (string $categoryProviderId): bool => ! in_array($categoryProviderId, $pinnedIds, true),
        ));

        foreach ($nonPinnedIds as $categoryProviderId) {
            $sortOrderMap[$categoryProviderId] = $nextSortOrder++;
        }

        $highestKnownSortOrder = max(array_merge(
            [$nextSortOrder - 1],
            array_map(
                static fn (UserCategoryPreference $preference): int => (int) $preference->sort_order,
                $existingPreferences,
            ),
        ));

        foreach ($pinnedIds as $categoryProviderId) {
            $existingPreference = $existingPreferences[$categoryProviderId] ?? null;

            if ($existingPreference instanceof UserCategoryPreference) {
                $sortOrderMap[$categoryProviderId] = (int) $existingPreference->sort_order;

                continue;
            }

            $highestKnownSortOrder++;
            $sortOrderMap[$categoryProviderId] = $highestKnownSortOrder;
        }

        return $sortOrderMap;
    }
}
