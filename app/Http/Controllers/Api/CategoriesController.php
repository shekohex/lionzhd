<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListCategoriesRequest;
use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class CategoriesController extends Controller
{
    public function index(ListCategoriesRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $mediaType = $request->mediaType();

        /** @var AnonymousResourceCollection $collection */
        $collection = CategoryResource::collection(
            Category::query()
                ->when($mediaType === MediaType::Movie, static fn (Builder $query): Builder => $query->where('in_vod', true))
                ->when($mediaType === MediaType::Series, static fn (Builder $query): Builder => $query->where('in_series', true))
                ->when($mediaType !== null, function (Builder $query) use ($user, $mediaType): void {
                    $query->whereNotIn('provider_id', $this->hiddenOrIgnoredCategoryIds($user, $mediaType));
                })
                ->orderBy('name')
                ->get()
        );

        return $collection;
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    /**
     * @return list<string>
     */
    private function hiddenOrIgnoredCategoryIds(User $user, MediaType $mediaType): array
    {
        return UserCategoryPreference::query()
            ->where('user_id', $user->id)
            ->where('media_type', $mediaType)
            ->where(static function (Builder $query): void {
                $query->where('is_hidden', true)->orWhere('is_ignored', true);
            })
            ->pluck('category_provider_id')
            ->all();
    }
}
