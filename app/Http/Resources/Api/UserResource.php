<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class UserResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->user()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'users';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $user = $this->user();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'subtype' => $user->subtype->value,
            'is_super_admin' => $user->is_super_admin,
        ];
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->resource;

        return $user;
    }
}
