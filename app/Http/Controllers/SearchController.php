<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Data\SearchMediaData;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    /**
     * Display search results page
     */
    public function full(SearchMediaData $search): Response
    {

        // Only perform search if query is not empty
        if (! $search->hasQuery()) {
            return Inertia::render('search', [
                'movies' => [],
                'series' => [],
                'filters' => $search,
            ]);
        }

        // Initialize results
        $movies = [];
        $series = [];

        // Calculate the limit for each type based on media_type filter
        $typeLimit = $search->media_type ? $search->per_page : (int) ceil($search->per_page / 2);

        // For movies, we use the provided movie page or default to 1
        $moviePage = $search->isSeries() ? null : $search->page;

        // For series, we use the provided series page or default to 1
        $seriesPage = $search->isMovie() ? null : $search->page;

        $getMovies = new SearchMovies;

        $getSeries = new SearchSeries;

        // Search for movies if appropriate
        if ($search->isMovie()) {
            $movies = $getMovies($search->q, $search->sort_by, $moviePage, $typeLimit);
        }

        // Search for series if appropriate
        if ($search->isSeries()) {
            $series = $getSeries($search->q, $search->sort_by, $seriesPage, $typeLimit);
        }

        // If media_type filter is not set, search for both movies and series
        if (! $search->media_type) {
            $movies = $getMovies($search->q, $search->sort_by, $moviePage, $typeLimit);
            $series = $getSeries($search->q, $search->sort_by, $seriesPage, $typeLimit);
        }

        return Inertia::render('search', [
            'movies' => $movies,
            'series' => $series,
            'filters' => $search,
        ]);
    }
}
