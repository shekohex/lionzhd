<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    /**
     * @return array{data: array{type: string, id: string, attributes: array{name: string, email: string, role: string, subtype: string, is_super_admin: bool}}}
     */
    public function show(Request $request): array
    {
        $user = $request->user();

        return [
            'data' => [
                'type' => 'users',
                'id' => (string) $user->getKey(),
                'attributes' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'subtype' => $user->subtype->value,
                    'is_super_admin' => $user->is_super_admin,
                ],
            ],
        ];
    }
}
