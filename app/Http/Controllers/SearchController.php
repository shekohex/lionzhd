<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Enums\MediaType;
use App\Enums\SearchSortby;
use App\Http\Requests\SearchMediaRequest;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    /**
     * Display search results page
     */
    public function full(SearchMediaRequest $request): Response
    {
        $query = $request->string('q')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $page = $request->integer('page', 1);
        $mediaType = $request->enum('media_type', MediaType::class);
        $sortBy = $request->enum('sort_by', SearchSortby::class);

        $filters = [
            'q' => $query,
            'per_page' => $perPage,
            'media_type' => $mediaType,
            'sort_by' => $sortBy,
            'page' => $page,
        ];

        // Only perform search if query is not empty
        if ($query === '') {
            return Inertia::render('search', [
                'movies' => [],
                'series' => [],
                'filters' => $filters,
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

        $getMovies = new SearchMovies;

        $getSeries = new SearchSeries;

        // Search for movies if appropriate
        if ($mediaType && $mediaType->isMovie()) {
            $movies = $getMovies($query, $sortBy, $moviePage, $typeLimit);
        }

        // Search for series if appropriate
        if ($mediaType && $mediaType->isSeries()) {
            $series = $getSeries($query, $sortBy, $seriesPage, $typeLimit);
        }

        // If media_type filter is not set, search for both movies and series
        if (! $mediaType) {
            $movies = $getMovies($query, $sortBy, $moviePage, $typeLimit);
            $series = $getSeries($query, $sortBy, $seriesPage, $typeLimit);
        }

        return Inertia::render('search', [
            'movies' => $movies,
            'series' => $series,
            'filters' => $filters,
        ]);
    }
}
