<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Collection;

final class JsonApiResourceDocument
{
    /**
     * @param  class-string<JsonApiResource>  $resourceClass
     * @param  iterable<int, mixed>|LengthAwarePaginator  $items
     * @return array<string, mixed>
     */
    public static function collection(Request $request, string $resourceClass, iterable|LengthAwarePaginator $items): array
    {
        $collection = $items instanceof LengthAwarePaginator
            ? Collection::make($items->items())
            : Collection::make($items);

        $document = [
            'data' => $collection
                ->map(static function (mixed $item) use ($request, $resourceClass): array {
                    /** @var JsonApiResource $resource */
                    $resource = new $resourceClass($item);

                    return [
                        'id' => $resource->toId($request),
                        'type' => $resource->toType($request),
                        'attributes' => $resource->toAttributes($request),
                    ];
                })
                ->values()
                ->all(),
        ];

        if ($items instanceof LengthAwarePaginator) {
            $document['links'] = [
                'first' => $items->url(1),
                'last' => $items->url($items->lastPage()),
                'prev' => $items->previousPageUrl(),
                'next' => $items->nextPageUrl(),
            ];
            $document['meta'] = [
                'current_page' => $items->currentPage(),
                'from' => $items->firstItem(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'to' => $items->lastItem(),
                'total' => $items->total(),
            ];
        }

        return $document;
    }
}
