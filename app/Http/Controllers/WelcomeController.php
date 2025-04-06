<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\FeaturedMediaData;
use App\Data\SeriesData;
use App\Data\VodStreamData;
use App\Models\Series;
use App\Models\VodStream;
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
}
