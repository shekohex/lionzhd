<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\Api\SearchResultResource;

final class LightweightSearchController extends Controller
{
    public function store(SearchRequest $request): SearchResultResource
    {
        $mediaType = $request->mediaType();
        $movies = [];
        $series = [];

        if ($mediaType !== MediaType::Series) {
            $movies = SearchMovies::run($request->queryText(), $request->sortBy(), $request->pageNumber(), $request->pageSize(), lightweight: true);
        }

        if ($mediaType !== MediaType::Movie) {
            $series = SearchSeries::run($request->queryText(), $request->sortBy(), $request->pageNumber(), $request->pageSize(), lightweight: true);
        }

        return new SearchResultResource([
            'movies' => $movies,
            'series' => $series,
        ]);
    }
}
