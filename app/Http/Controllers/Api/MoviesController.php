<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Requests\Api\ListMoviesRequest;
use App\Http\Requests\Api\ShowMovieRequest;
use App\Http\Resources\Api\MovieResource;
use App\Models\User;
use App\Models\VodStream;
use App\Support\MediaCategoryPreferenceFilter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class MoviesController extends Controller
{
    public function index(ListMoviesRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        /** @var AnonymousResourceCollection $collection */
        $collection = MovieResource::collection(
            MediaCategoryPreferenceFilter::apply(VodStream::query(), $user, MediaType::Movie, $request->categoryId())
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

    public function show(ShowMovieRequest $request, XtreamCodesConnector $client, VodStream $movie): MovieResource
    {
        if ($request->includesVodInfo()) {
            $response = $client->send(new GetVodInfoRequest($movie->stream_id));

            if ($response->status() === 404) {
                abort(404);
            }

            $movie->setAttribute('api_vod_info', json_decode((string) json_encode($response->dtoOrFail(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        }

        return new MovieResource($movie);
    }
}
