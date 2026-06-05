<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Enums\MediaType;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class CategoryPreferencesResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->mediaType()->value;
    }

    public function toType(Request $request): string
    {
        return 'category-preferences';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $preferences = UserCategoryPreference::query()
            ->where('user_id', $this->user()->id)
            ->where('media_type', $this->mediaType()->value)
            ->orderBy('sort_order')
            ->get();

        return [
            'media_type' => $this->mediaType()->value,
            'pinned_ids' => $preferences
                ->whereNotNull('pin_rank')
                ->sortBy('pin_rank')
                ->pluck('category_provider_id')
                ->values()
                ->all(),
            'hidden_ids' => $preferences->where('is_hidden', true)->pluck('category_provider_id')->values()->all(),
            'ignored_ids' => $preferences->where('is_ignored', true)->pluck('category_provider_id')->values()->all(),
            'visible_ids' => $preferences
                ->filter(static fn (UserCategoryPreference $preference): bool => ! $preference->is_hidden && ! $preference->is_ignored)
                ->pluck('category_provider_id')
                ->values()
                ->all(),
        ];
    }

    private function user(): User
    {
        /** @var array{user: User, mediaType: MediaType} $resource */
        $resource = $this->resource;

        return $resource['user'];
    }

    private function mediaType(): MediaType
    {
        /** @var array{user: User, mediaType: MediaType} $resource */
        $resource = $this->resource;

        return $resource['mediaType'];
    }
}
