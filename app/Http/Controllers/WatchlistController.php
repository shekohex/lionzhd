<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CheckWatchlistRequest;
use App\Http\Requests\ListWatchlistRequest;
use App\Http\Requests\StoreWatchlistRequest;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\Watchlist;
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
            $watchable = $item->watchable;
            $type = $item->watchable_type === VodStream::class ? 'movie' : 'series';

            return [
                'id' => $item->id,
                'type' => $type,
                'watchableId' => $watchable->num,
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
    public function store(StoreWatchlistRequest $request): RedirectResponse
    {
        $type = $request->string('type')->lower()->toString();
        $id = $request->integer('id');

        /** @var User $user */
        $user = Auth::user();

        // Determine the model type based on the type parameter
        $modelType = $type === 'movie' ? VodStream::class : Series::class;

        // Check if the item exists
        $model = $modelType::find($id);
        if (! $model) {
            return to_route('watchlist')->withErrors("{$type} is not found in the database");
        }

        // Check if the item is already in the watchlist
        $exists = $user->watchlists()
            ->where('watchable_type', $modelType)
            ->where('watchable_id', $id)
            ->exists();

        if ($exists) {
            return to_route('watchlist')->withErrors("{$type} already in your watchlist");
        }

        // Add to watchlist
        $item = $user->watchlists()->create([
            'watchable_type' => $modelType,
            'watchable_id' => $id,
        ]);

        return to_route('watchlist')
            ->with('watchableType', $type)
            ->with('watchableId', $id)
            ->with('itemId', $item->id);
    }

    /**
     * Remove an item from the user's watchlist.
     */
    public function destroy(int $id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var ?Watchlist $watchlistItem */
        $watchlistItem = $user->watchlists()->find($id);

        if (! $watchlistItem) {
            return to_route('watchlist')->withErrors('watchlist item does not exist');
        }

        $deleted = $watchlistItem->delete();

        if (! $deleted) {
            return to_route('watchlist')->withErrors('Failed to remove the item from the watchlist');
        }

        return to_route('watchlist')->with('message', 'item added to your watchlist');
    }

    /**
     * Check whether the user has added the media item to their watchlist.
     */
    public function check(CheckWatchlistRequest $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $id = $request->integer('media_id');
        $type = $request->string('media_type')->lower()->toString();

        $modelType = $type === 'movie' ? VodStream::class : Series::class;
        $exists = $user->inMyWatchlist($id, $modelType);

        return Inertia::render('watchlist', [
            'exists' => $exists,
            'filter' => $type,
            'items' => [],
        ]);
    }
}
