<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SaveUserCategoryPreferences;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateCategoryPreferencesRequest;
use App\Http\Resources\Api\CategoryPreferencesResource;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CategoryPreferencesController extends Controller
{
    public function update(UpdateCategoryPreferencesRequest $request, #[CurrentUser] User $user, string $mediaType): CategoryPreferencesResource
    {
        $type = $this->mediaType($mediaType);

        SaveUserCategoryPreferences::run(
            user: $user,
            mediaType: $type,
            pinnedIds: $request->normalizedList('pinned_ids'),
            visibleIds: $request->normalizedList('visible_ids'),
            hiddenIds: $request->normalizedList('hidden_ids'),
            ignoredIds: $request->normalizedList('ignored_ids'),
        );

        return new CategoryPreferencesResource(['user' => $user, 'mediaType' => $type]);
    }

    public function destroy(#[CurrentUser] User $user, string $mediaType): CategoryPreferencesResource
    {
        $type = $this->mediaType($mediaType);

        UserCategoryPreference::query()
            ->where('user_id', $user->id)
            ->where('media_type', $type->value)
            ->delete();

        return new CategoryPreferencesResource(['user' => $user, 'mediaType' => $type]);
    }

    private function mediaType(string $mediaType): MediaType
    {
        $type = MediaType::tryFrom($mediaType);

        if (! $type instanceof MediaType) {
            throw new HttpResponseException(response()->json([
                'errors' => [[
                    'status' => '404',
                    'title' => 'Not Found',
                    'detail' => 'Not Found',
                ]],
            ], 404)->header('Content-Type', 'application/vnd.api+json'));
        }

        return $type;
    }
}
