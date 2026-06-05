<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class ApiActionResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->resource['id'];
    }

    public function toType(Request $request): string
    {
        return (string) $this->resource['type'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $this->resource['attributes'];

        return $attributes;
    }
}
