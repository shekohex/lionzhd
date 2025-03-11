<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Enums\SearchSortby;
use App\Http\Requests\SearchMediaRequest;
use App\Services\MediaSearchService;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly MediaSearchService $searchService) {}

    /**
     * Display search results page
     */
    public function full(SearchMediaRequest $request): Response
    {
        $query = $request->string('q')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $mediaType = $request->enum('media_type', MediaType::class);
        $sortBy = $request->enum('sort_by', SearchSortby::class);

        // Only perform search if query is not empty
        if ($query === '') {
            return Inertia::render('search', [
                'movies' => [],
                'series' => [],
                'filters' => [
                    'q' => $query,
                    'per_page' => $perPage,
                    'media_type' => $mediaType,
                    'sort_by' => $sortBy,
                ],
            ]);
        }

        // Initialize results
        $movies = [];
        $series = [];

        // Calculate the limit for each type based on media_type filter
        $typeLimit = $mediaType ? $perPage : (int) ceil($perPage / 2);

        // For movies, we use the provided movie page or default to 1
        $moviePage = $mediaType && $mediaType->isSeries() ? null : $request->integer('page', 1);

        // For series, we use the provided series page or default to 1
        $seriesPage = $mediaType && $mediaType->isMovie() ? null : $request->integer('page', 1);

        $getMovies = (fn () =>
            // Only set the page when we're specifically querying movies or both
            $this->searchService->searchMovies(
                $query,
                $typeLimit,
                $sortBy,
                $moviePage
            ));

        $getSeries = (fn () =>
            // Only set the page when we're specifically querying series or both
            $this->searchService->searchSeries(
                $query,
                $typeLimit,
                $sortBy,
                $seriesPage
            ));

        // Search for movies if appropriate
        if ($mediaType && $mediaType->isMovie()) {
            $movies = $getMovies();
        }

        // Search for series if appropriate
        if ($mediaType && $mediaType->isSeries()) {
            $series = $getSeries();
        }

        // If media_type filter is not set, search for both movies and series
        if (! $mediaType) {
            $movies = $getMovies();
            $series = $getSeries();
        }

        return Inertia::render('search', [
            'movies' => $movies,
            'series' => $series,
            'filters' => [
                'q' => $query,
                'per_page' => $perPage,
                'media_type' => $mediaType,
                'sort_by' => $sortBy,
                'page' => $request->integer('page', 1),
            ],
        ]);
    }

    /**
     * Display lightweight search results page
     */
    public function lightweight(SearchMediaRequest $request): Response
    {
        $query = $request->string('q')->trim()->toString();
        if ($query === '') {
            return Inertia::render('search', [
                'movies' => [],
                'series' => [],
                'filters' => [
                    'q' => $query,
                    'per_page' => 5,
                ],
            ]);
        }

        $movies = $this->searchService->searchMovies($query, 5, SearchSortby::Latest);
        $series = $this->searchService->searchSeries($query, 5, SearchSortby::Latest);
        $movies = $this->searchService->lightweightMovieResults($movies);
        $series = $this->searchService->lightweightSeriesResults($series);

        return Inertia::render('search', [
            'movies' => $movies,
            'series' => $series,
            'filters' => [
                'q' => $query,
                'per_page' => 5,
            ],
        ]);
    }
}
