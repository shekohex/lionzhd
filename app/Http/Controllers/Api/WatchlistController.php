<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AddToWatchlist;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListWatchlistRequest;
use App\Http\Requests\Api\StoreWatchlistRequest;
use App\Http\Resources\Api\WatchlistResource;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\Watchlist;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class WatchlistController extends Controller
{
    public function index(ListWatchlistRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        /** @var AnonymousResourceCollection $collection */
        $collection = WatchlistResource::collection(
            $user->watchlists()
                ->with('watchable')
                ->when($request->filter() === 'movies', static function (Builder $query): void {
                    $query->where('watchable_type', VodStream::class);
                })
                ->when($request->filter() === 'series', static function (Builder $query): void {
                    $query->where('watchable_type', Series::class);
                })
                ->latest()
                ->orderByDesc('id')
                ->get()
        );

        return $collection;
    }

    public function store(StoreWatchlistRequest $request, #[CurrentUser] User $user): WatchlistResource
    {
        [$modelClass, $model] = $this->resolveWatchable($request->mediaType(), $request->mediaId());

        abort_unless(AddToWatchlist::run($user, $request->mediaId(), $modelClass), 500, 'Failed to add item to watchlist.');

        $watchlist = $user->watchlists()
            ->where('watchable_type', $modelClass)
            ->where('watchable_id', $model->getKey())
            ->latest()
            ->firstOrFail();
        $watchlist->setRelation('watchable', $model);

        return new WatchlistResource($watchlist);
    }

    public function destroy(#[CurrentUser] User $user, Watchlist $watchlist): WatchlistResource
    {
        abort_unless($watchlist->user_id === $user->id, 404);

        $watchlist->load('watchable');
        $resource = new WatchlistResource($watchlist);
        $watchlist->delete();

        return $resource;
    }

    /**
     * @return array{0: class-string<Series|VodStream>, 1: Series|VodStream}
     */
    private function resolveWatchable(string $mediaType, int $mediaId): array
    {
        if ($mediaType === 'movie') {
            $movie = VodStream::query()->whereKey($mediaId)->firstOrFail();

            return [VodStream::class, $movie];
        }

        $series = Series::query()->whereKey($mediaId)->firstOrFail();

        return [Series::class, $series];
    }
}
