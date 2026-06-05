<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Enums\MediaType;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\Api\SearchResultResource;

trait BuildsSearchResults
{
    protected function searchResults(SearchRequest $request, bool $lightweight = false): SearchResultResource
    {
        $mediaType = $request->mediaType();
        $movies = [];
        $series = [];

        if ($mediaType !== MediaType::Series) {
            $movies = SearchMovies::run($request->queryText(), $request->sortBy(), $request->pageNumber(), $request->pageSize(), $lightweight);
        }

        if ($mediaType !== MediaType::Movie) {
            $series = SearchSeries::run($request->queryText(), $request->sortBy(), $request->pageNumber(), $request->pageSize(), $lightweight);
        }

        return new SearchResultResource([
            'movies' => $movies,
            'series' => $series,
        ]);
    }
}
