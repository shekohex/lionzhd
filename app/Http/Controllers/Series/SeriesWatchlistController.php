<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\AddToWatchlist;
use App\Actions\RemoveFromWatchlist;
use App\Http\Controllers\Controller;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class SeriesWatchlistController extends Controller
{
    /**
     * Add an item to the user's watchlist.
     */
    public function store(#[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $added = AddToWatchlist::run($user, $model->series_id, Series::class);
        if (! $added) {
            return back()->withErrors('Failed to add series to watchlist.');
        }

        return back();
    }

    /**
     * Remove an item from the user's watchlist.
     */
    public function destroy(#[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $removed = RemoveFromWatchlist::run($user, $model->series_id, Series::class);
        if (! $removed) {
            return back()->withErrors('Failed to remove series from watchlist.');
        }

        return back();
    }
}
