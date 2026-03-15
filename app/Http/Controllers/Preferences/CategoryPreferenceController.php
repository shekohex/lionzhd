<?php

declare(strict_types=1);

namespace App\Http\Controllers\Preferences;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Preferences\UpdateCategoryPreferencesRequest;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CategoryPreferenceController extends Controller
{
    public function update(UpdateCategoryPreferencesRequest $request, #[CurrentUser] User $user, MediaType $mediaType): RedirectResponse
    {
        $validated = $request->validated();
        $pinnedIds = array_values($validated['pinned_ids']);
        $visibleIds = array_values($validated['visible_ids']);
        $hiddenIds = array_values($validated['hidden_ids']);

        UserCategoryPreference::query()
            ->where('user_id', $user->id)
            ->where('media_type', $mediaType->value)
            ->delete();

        $rows = [];
        $sortOrder = 1;
        $pinRanks = array_flip($pinnedIds);

        foreach (array_merge($visibleIds, $hiddenIds) as $categoryProviderId) {
            $rows[] = [
                'user_id' => $user->id,
                'media_type' => $mediaType->value,
                'category_provider_id' => $categoryProviderId,
                'pin_rank' => array_key_exists($categoryProviderId, $pinRanks) ? $pinRanks[$categoryProviderId] + 1 : null,
                'sort_order' => $sortOrder++,
                'is_hidden' => in_array($categoryProviderId, $hiddenIds, true),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            UserCategoryPreference::query()->insert($rows);
        }

        return back();
    }

    public function destroy(Request $request, #[CurrentUser] User $user, MediaType $mediaType): RedirectResponse
    {
        UserCategoryPreference::query()
            ->where('user_id', $user->id)
            ->where('media_type', $mediaType->value)
            ->delete();

        return back();
    }
}
