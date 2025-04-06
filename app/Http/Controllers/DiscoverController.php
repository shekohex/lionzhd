<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\FeaturedMediaData;
use App\Data\SeriesData;
use App\Data\VodStreamData;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

final class DiscoverController extends Controller
{
    /**
     * Display the discover page with top movies and series.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        // Get top 15 movies and series with watchlist status
        $movies = VodStream::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
            ->orderBy('added', 'desc')
            ->limit(15)
            ->get();

        $series = Series::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
            ->orderBy('last_modified', 'desc')
            ->limit(15)
            ->get();

        return Inertia::render('discover', new FeaturedMediaData(
            VodStreamData::collect($movies, DataCollection::class),
            SeriesData::collect($series, DataCollection::class),
        ));
    }
}
