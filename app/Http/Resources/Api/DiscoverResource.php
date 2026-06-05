<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Support\JsonApiResourceDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class DiscoverResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return 'featured';
    }

    public function toType(Request $request): string
    {
        return 'discover';
    }

    /**
     * @return array{
     *     movies: array<string, mixed>,
     *     series: array<string, mixed>
     * }
     */
    public function toAttributes(Request $request): array
    {
        return [
            'movies' => JsonApiResourceDocument::collection($request, MovieResource::class, $this->resource['movies']),
            'series' => JsonApiResourceDocument::collection($request, SeriesResource::class, $this->resource['series']),
        ];
    }
}
