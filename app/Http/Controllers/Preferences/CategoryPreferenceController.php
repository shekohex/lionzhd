<?php

declare(strict_types=1);

namespace App\Http\Controllers\Preferences;

use App\Actions\SaveUserCategoryPreferences;
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
        SaveUserCategoryPreferences::run(
            user: $user,
            mediaType: $mediaType,
            pinnedIds: array_values($validated['pinned_ids']),
            visibleIds: array_values($validated['visible_ids']),
            hiddenIds: array_values($validated['hidden_ids']),
        );

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
