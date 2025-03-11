<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Series;
use App\Models\VodStream;
use Inertia\Inertia;
use Inertia\Response;

final class DiscoverController extends Controller
{
    /**
     * Display the discover page with top movies and series.
     */
    public function index(): Response
    {
        // Get top 15 movies and series
        $movies = VodStream::query()->orderBy('added', 'desc')
            ->limit(15)
            ->get();

        $series = Series::query()->orderBy('last_modified', 'desc')
            ->limit(15)
            ->get();

        return Inertia::render('discover', [
            'movies' => $movies,
            'series' => $series,
        ]);
    }
}
