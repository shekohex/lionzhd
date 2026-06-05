<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Requests\Api\ListSeriesRequest;
use App\Http\Requests\Api\ShowSeriesRequest;
use App\Http\Resources\Api\SeriesResource;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class SeriesController extends Controller
{
    public function index(ListSeriesRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $categoryId = $request->categoryId();
        $preferenceCategoryIds = $this->seriesPreferenceCategoryIds($user);

        /** @var AnonymousResourceCollection $collection */
        $collection = SeriesResource::collection(
            Series::query()
                ->when($categoryId === null && $preferenceCategoryIds['hidden'] !== [], static function (Builder $query) use ($preferenceCategoryIds): void {
                    self::whereCategoryNotInPreservingUncategorized($query, $preferenceCategoryIds['hidden']);
                })
                ->when($preferenceCategoryIds['ignored'] !== [], static function (Builder $query) use ($preferenceCategoryIds): void {
                    self::whereCategoryNotInPreservingUncategorized($query, $preferenceCategoryIds['ignored']);
                })
                ->when($categoryId !== null, static function (Builder $query) use ($categoryId): void {
                    if ($categoryId === Category::UNCATEGORIZED_SERIES_PROVIDER_ID) {
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
                ->orderBy('series_id')
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

    public function show(ShowSeriesRequest $request, XtreamCodesConnector $client, Series $series): SeriesResource
    {
        if ($request->includesEpisodes()) {
            $response = $client->send(new GetSeriesInfoRequest($series->series_id));

            if ($response->status() === 404) {
                abort(404);
            }

            $dto = $response->dtoOrFail();
            $series->setAttribute('api_series_seasons', $dto->seasons);
            $series->setAttribute('api_series_episodes', json_decode((string) json_encode($dto->seasonsWithEpisodes, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        }

        return new SeriesResource($series);
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
    private function seriesPreferenceCategoryIds(User $user): array
    {
        $preferences = UserCategoryPreference::query()
            ->where('user_id', $user->getKey())
            ->where('media_type', MediaType::Series->value)
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
