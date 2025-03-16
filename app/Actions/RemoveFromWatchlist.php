<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\Watchlist;

final class RemoveFromWatchlist
{
    use AsAction;

    /**
     * Remove an item from the watchlist.
     *
     * @param  class-string<Series|VodStream>  $watchableType
     * @return bool True if the item was removed from the watchlist, false otherwise
     */
    public function __invoke(
        User $user,
        int $watchableId,
        string $watchableType
    ): bool {
        $deleted = Watchlist::query()
            ->where('watchable_id', $watchableId)
            ->where('watchable_type', $watchableType)
            ->firstOrFail()
            ->delete();

        return (bool) $deleted;
    }
}
