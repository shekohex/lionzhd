<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Requests\Api\ListMoviesRequest;
use App\Http\Resources\Api\MovieResource;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class MoviesController extends Controller
{
    public function index(ListMoviesRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $categoryId = $request->categoryId();
        $preferenceCategoryIds = $this->moviePreferenceCategoryIds($user);

        /** @var AnonymousResourceCollection $collection */
        $collection = MovieResource::collection(
            VodStream::query()
                ->when($categoryId === null && $preferenceCategoryIds['hidden'] !== [], static function (Builder $query) use ($preferenceCategoryIds): void {
                    self::whereCategoryNotInPreservingUncategorized($query, $preferenceCategoryIds['hidden']);
                })
                ->when($preferenceCategoryIds['ignored'] !== [], static function (Builder $query) use ($preferenceCategoryIds): void {
                    self::whereCategoryNotInPreservingUncategorized($query, $preferenceCategoryIds['ignored']);
                })
                ->when($categoryId !== null, static function (Builder $query) use ($categoryId): void {
                    if ($categoryId === Category::UNCATEGORIZED_VOD_PROVIDER_ID) {
                        $query->where(static function (Builder $innerQuery) use ($categoryId): void {
                            $innerQuery
                                ->whereNull('category_id')
                                ->orWhere('category_id', '')
                                ->orWhere('category_id', $categoryId);
                        });

                        return;
                    }

                    $query->where('category_id', $categoryId);
                })
                ->orderBy('stream_id')
                ->paginate(
                    perPage: $request->pageSize(),
                    pageName: 'page[number]',
                    page: $request->pageNumber(),
                )
        )->withQuery([
            'page' => [
                'size' => $request->pageSize(),
            ],
        ]);

        return $collection;
    }

    public function show(ListMoviesRequest $request, XtreamCodesConnector $client, VodStream $movie): MovieResource
    {
        if ($request->query('include') === 'vod-info') {
            $response = $client->send(new GetVodInfoRequest($movie->stream_id));

            if ($response->status() === 404) {
                abort(404);
            }

            $movie->setAttribute('api_vod_info', json_decode((string) json_encode($response->dtoOrFail(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        }

        return new MovieResource($movie);
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
    private function moviePreferenceCategoryIds(User $user): array
    {
        $preferences = UserCategoryPreference::query()
            ->where('user_id', $user->getKey())
            ->where('media_type', MediaType::Movie->value)
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
