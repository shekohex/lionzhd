<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Data\FeaturedMediaData;
use App\Data\LightweightSearchData;
use App\Data\LightweightSearchFiltersData;
use App\Data\SearchMediaData;
use App\Data\SeriesData;
use App\Data\VodStreamData;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

final class WelcomeController extends Controller
{
    /**
     * Display the welcome page with top movies and series.
     */
    public function index(): Response
    {
        $movies = VodStream::query()->orderByDesc('added')
            ->limit(3)
            ->get();

        $series = Series::query()->orderByDesc('last_modified')
            ->limit(3)
            ->get();

        $result = new FeaturedMediaData(
            VodStreamData::collect($movies, DataCollection::class),
            SeriesData::collect($series, DataCollection::class),
        );

        return Inertia::render('welcome', ['featured' => $result]);
    }

    public function search(SearchMediaData $search): Response|RedirectResponse
    {
        if ($search->q === '') {
            return to_route('home');
        }

        $movies = SearchMovies::run($search->q, $search->sort_by, lightweight: true, perPage: $search->per_page);
        $series = SearchSeries::run($search->q, $search->sort_by, lightweight: true, perPage: $search->per_page);

        $filters = LightweightSearchFiltersData::from($search);

        $result = new LightweightSearchData(
            VodStreamData::collect($movies),
            SeriesData::collect($series),
            $filters,
        );

        return Inertia::render('welcome', $result);
    }
}
