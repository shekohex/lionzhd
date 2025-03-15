<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Enums\SearchSortby;
use App\Http\Requests\SearchMediaRequest;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeController extends Controller
{
    /**
     * Display the welcome page with top movies and series.
     */
    public function index(?array $result = null, ?array $filters = null): Response
    {
        $movies = VodStream::query()->orderByDesc('added')
            ->limit(3)
            ->get(['num', 'name', 'stream_icon', 'rating_5based', 'added']);

        $series = Series::query()->orderByDesc('last_modified')
            ->limit(3)
            ->get(['num', 'name', 'plot', 'cover', 'rating_5based', 'last_modified']);

        $result = [
            'movies' => $movies->toArray(),
            'series' => $series->toArray(),
        ];

        return Inertia::render('welcome', ['featured' => $result]);
    }

    public function search(SearchMediaRequest $request): Response|RedirectResponse
    {
        $query = $request->string('q', '')->trim()->toString();
        $perPage = $request->integer('per_page', 5);
        if ($query === '') {
            return to_route('home');
        }

        $movies = SearchMovies::run($query, SearchSortby::Popular, lightweight: true, perPage: $perPage);
        $series = SearchSeries::run($query, SearchSortby::Popular, lightweight: true, perPage: $perPage);

        $filters = [
            'q' => $query,
            'per_page' => $perPage,
        ];

        return Inertia::render('welcome', [
            'movies' => $movies,
            'series' => $series,
            'filters' => $filters,
        ]);
    }
}
