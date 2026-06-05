<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class SettingsResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->payload()['id'];
    }

    public function toType(Request $request): string
    {
        return (string) $this->payload()['type'];
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return $this->payload()['attributes'];
    }

    /** @return array{id: string, type: string, attributes: array<string, mixed>} */
    private function payload(): array
    {
        /** @var array{id: string, type: string, attributes: array<string, mixed>} $payload */
        $payload = $this->resource;

        return $payload;
    }
}
