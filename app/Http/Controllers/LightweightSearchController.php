<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchMovies;
use App\Actions\SearchSeries;
use App\Data\LightweightSearchData;
use App\Data\LightweightSearchFiltersData;
use App\Data\SearchMediaData;
use App\Data\SeriesData;
use App\Data\VodStreamData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;
use Request;

final class LightweightSearchController extends Controller
{
    public function show(SearchMediaData $search): Response|RedirectResponse
    {
        $component = Request::header(Header::PARTIAL_COMPONENT);
        if ($search->q === '' || $component === null) {
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

        return Inertia::render($component, $result);
    }
}
