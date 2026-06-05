<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListMoviesRequest;
use App\Http\Resources\Api\MovieResource;
use App\Models\VodStream;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class MoviesController extends Controller
{
    public function index(ListMoviesRequest $request): AnonymousResourceCollection
    {
        /** @var AnonymousResourceCollection $collection */
        $collection = MovieResource::collection(
            VodStream::query()
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

    public function show(VodStream $movie): MovieResource
    {
        return new MovieResource($movie);
    }
}
