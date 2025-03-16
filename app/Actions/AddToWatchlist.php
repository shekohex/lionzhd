<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\Watchlist;

final class AddToWatchlist
{
    use AsAction;

    /**
     * Add a watchable item to the user's watchlist.
     *
     * @param  class-string<Series|VodStream>  $watchableType
     * @return bool True if the item was added to the watchlist, false otherwise.
     */
    public function __invoke(
        User $user,
        int $watchableId,
        string $watchableType
    ): bool {
        $watchlist = $user->watchlists()
            ->create([
                'watchable_id' => $watchableId,
                'watchable_type' => $watchableType,
            ]);

        return $watchlist->exists();
    }
}
