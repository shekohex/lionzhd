<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Series;
use App\Models\VodStream;
use App\Models\Watchlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class WatchlistResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->watchlist()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'watchlist-items';
    }

    /**
     * @return array{media_type: string, media_id: int, name: string, cover: string|null, created_at: mixed, updated_at: mixed}
     */
    public function toAttributes(Request $request): array
    {
        $watchlist = $this->watchlist();
        $watchable = $watchlist->watchable;

        return [
            'media_type' => $watchlist->watchable_type === VodStream::class ? 'movie' : 'series',
            'media_id' => (int) $watchlist->watchable_id,
            'name' => (string) $watchable->getAttribute('name'),
            'cover' => $watchable instanceof VodStream ? $watchable->stream_icon : ($watchable instanceof Series ? $watchable->cover : null),
            'created_at' => $watchlist->created_at,
            'updated_at' => $watchlist->updated_at,
        ];
    }

    /**
     * @return array<string, array{data: array{type: string, id: string}}>
     */
    protected function resolveResourceRelationshipIdentifiers(JsonApiRequest $request): array
    {
        $watchlist = $this->watchlist();

        return [
            'watchable' => [
                'data' => [
                    'type' => $watchlist->watchable_type === VodStream::class ? 'movies' : 'series',
                    'id' => (string) $watchlist->watchable_id,
                ],
            ],
        ];
    }

    private function watchlist(): Watchlist
    {
        /** @var Watchlist $watchlist */
        $watchlist = $this->resource;

        return $watchlist;
    }
}
