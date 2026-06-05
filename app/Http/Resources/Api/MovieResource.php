<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\VodStream;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class MovieResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->movie()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'movies';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return $this->movie()->getData()->toArray();
    }

    private function movie(): VodStream
    {
        /** @var VodStream $movie */
        $movie = $this->resource;

        return $movie;
    }
}
