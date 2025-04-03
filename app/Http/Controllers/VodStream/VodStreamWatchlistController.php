<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\AddToWatchlist;
use App\Actions\RemoveFromWatchlist;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class VodStreamWatchlistController extends Controller
{
    /**
     * Add an item to the user's watchlist.
     */
    public function store(#[CurrentUser] User $user, VodStream $model): RedirectResponse
    {
        $added = AddToWatchlist::run($user, $model->stream_id, VodStream::class);
        if (! $added) {
            return back()->withErrors('Failed to add movie to watchlist.');
        }

        return back();
    }

    /**
     * Remove an item from the user's watchlist.
     */
    public function destroy(#[CurrentUser] User $user, VodStream $model): RedirectResponse
    {
        $removed = RemoveFromWatchlist::run($user, $model->stream_id, VodStream::class);
        if (! $removed) {
            return back()->withErrors('Failed to remove movie from watchlist.');
        }

        return back()->with('success', 'Movie removed from watchlist.');
    }
}
