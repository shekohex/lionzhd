<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AddToWatchlist;
use App\Actions\RemoveFromWatchlist;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MovieResource;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;

final class MovieWatchlistController extends Controller
{
    public function store(#[CurrentUser] User $user, VodStream $movie): MovieResource
    {
        if (! $user->inMyWatchlist($movie->stream_id, VodStream::class)) {
            AddToWatchlist::run($user, $movie->stream_id, VodStream::class);
        }

        return new MovieResource($movie->refresh());
    }

    public function destroy(#[CurrentUser] User $user, VodStream $movie): MovieResource
    {
        if ($user->inMyWatchlist($movie->stream_id, VodStream::class)) {
            RemoveFromWatchlist::run($user, $movie->stream_id, VodStream::class);
        }

        return new MovieResource($movie->refresh());
    }
}
