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
use App\Models\Series;
use App\Models\User;
use App\Support\MediaCategoryPreferenceFilter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class SeriesController extends Controller
{
    public function index(ListSeriesRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        /** @var AnonymousResourceCollection $collection */
        $collection = SeriesResource::collection(
            MediaCategoryPreferenceFilter::apply(Series::query(), $user, MediaType::Series, $request->categoryId())
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
}
