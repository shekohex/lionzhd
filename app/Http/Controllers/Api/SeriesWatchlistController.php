<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AddToWatchlist;
use App\Actions\RemoveFromWatchlist;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SeriesResource;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final class SeriesWatchlistController extends Controller
{
    public function store(#[CurrentUser] User $user, Series $series): SeriesResource
    {
        abort_unless(AddToWatchlist::run($user, $series->series_id, Series::class), 500, 'Failed to add series to watchlist.');

        return new SeriesResource($series);
    }

    public function destroy(#[CurrentUser] User $user, Series $series): SeriesResource
    {
        abort_unless(RemoveFromWatchlist::run($user, $series->series_id, Series::class), 500, 'Failed to remove series from watchlist.');

        return new SeriesResource($series);
    }
}
