<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Data\SearchMediaData;
use App\Enums\MediaType;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    /**
     * Display search results page
     */
    public function show(SearchMediaData $search): Response
    {
        $normalizedQuery = $search->normalizedQuery();
        $resolvedMediaType = $search->resolvedMediaType();
        $resolvedSortBy = $search->resolvedSortBy();
        $resolvedFilters = $search->resolvedFilters();

        if (! $search->hasQuery()) {
            return Inertia::render('search', [
                'movies' => [],
                'series' => [],
                'filters' => $resolvedFilters,
            ]);
        }

        $movies = [];
        $series = [];

        $typeLimit = $resolvedMediaType instanceof MediaType ? $search->per_page : (int) ceil($search->per_page / 2);
        $moviePage = $resolvedMediaType?->isSeries() ? null : $search->page;
        $seriesPage = $resolvedMediaType?->isMovie() ? null : $search->page;

        $getMovies = new SearchMovies;
        $getSeries = new SearchSeries;

        if ($search->isMovie()) {
            $movies = $getMovies($normalizedQuery, $resolvedSortBy, $moviePage, $typeLimit);
        }

        if ($search->isSeries()) {
            $series = $getSeries($normalizedQuery, $resolvedSortBy, $seriesPage, $typeLimit);
        }

        if (! $resolvedMediaType instanceof MediaType) {
            $movies = $getMovies($normalizedQuery, $resolvedSortBy, $moviePage, $typeLimit);
            $series = $getSeries($normalizedQuery, $resolvedSortBy, $seriesPage, $typeLimit);
        }

        return Inertia::render('search', [
            'movies' => $movies,
            'series' => $series,
            'filters' => $resolvedFilters,
        ]);
    }
}
