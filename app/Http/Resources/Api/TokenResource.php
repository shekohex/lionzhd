<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Laravel\Sanctum\PersonalAccessToken;

final class TokenResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->token()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'tokens';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $token = $this->token();
        $modelAttributes = $token->getAttributes();
        $plainTextToken = $modelAttributes['plain_text_token'] ?? null;

        return array_filter([
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'created_at' => $token->created_at,
            'updated_at' => $token->updated_at,
            'plain_text_token' => is_string($plainTextToken) ? $plainTextToken : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function token(): PersonalAccessToken
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource;

        return $token;
    }
}
