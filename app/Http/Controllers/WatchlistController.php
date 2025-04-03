<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddToWatchlist;
use App\Http\Requests\ListWatchlistRequest;
use App\Http\Requests\StoreWatchlistRequest;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\Watchlist;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class WatchlistController extends Controller
{
    /**
     * Display the user's watchlist items.
     */
    public function index(ListWatchlistRequest $request): Response
    {
        $filter = $request->string('filter', 'all')->lower()->toString();
        /** @var User $user */
        $user = Auth::user();

        $query = $user->watchlists()->with('watchable');

        // Apply filter if specified
        if ($filter === 'movies') {
            $query->where('watchable_type', VodStream::class);
        } elseif ($filter === 'series') {
            $query->where('watchable_type', Series::class);
        }

        /** @var Collection<Watchlist> $watchlistItems */
        $watchlistItems = $query->latest()->get();

        // Transform the data for the frontend
        $items = $watchlistItems->map(function ($item): array {
            /** @var VodStream|Series */
            $watchable = $item->watchable;
            $type = $item->watchable_type === VodStream::class ? 'movie' : 'series';

            return [
                'id' => $item->id,
                'type' => $type,
                'watchableId' => $watchable->getKey(),
                'name' => $watchable->name,
                'cover' => $type === 'movie' ? $watchable->stream_icon : $watchable->cover,
                'addedAt' => $item->created_at->diffForHumans(),
            ];
        });

        return Inertia::render('watchlist', [
            'items' => $items,
            'filter' => $filter,
        ]);
    }

    /**
     * Add an item to the user's watchlist.
     */
    public function store(#[CurrentUser] User $user, StoreWatchlistRequest $request): RedirectResponse
    {
        $type = $request->string('type')->lower()->toString();
        $id = $request->integer('id');

        // Determine the model type based on the type parameter
        $modelType = $type === 'movie' ? VodStream::class : Series::class;

        $added = AddToWatchlist::run($user, $id, $modelType);

        if (! $added) {
            return to_route('watchlist')
                ->withErrors('Failed to add the item to the watchlist');
        }

        return to_route('watchlist');
    }

    /**
     * Remove an item from the user's watchlist.
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $removed = Watchlist::query()
                ->where('id', $id)
                ->firstOrFail()
                ->delete();

            return to_route('watchlist')
                ->with('message', 'Item removed from your watchlist');

        } catch (ModelNotFoundException) {
            return to_route('watchlist')
                ->withErrors('Watchlist item does not exist');
        }

    }
}
