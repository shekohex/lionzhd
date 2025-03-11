<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Series;
use App\Models\VodStream;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeController extends Controller
{
    /**
     * Display the welcome page with top movies and series.
     */
    public function index(): Response
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

        return Inertia::render('welcome', $result);
    }
}
