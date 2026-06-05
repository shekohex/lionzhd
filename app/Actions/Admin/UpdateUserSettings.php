<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserSubtype;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateUserSettings
{
    public function subtype(User $user, UserSubtype $subtype): User
    {
        if ($user->role !== UserRole::Member) {
            throw ValidationException::withMessages([
                'subtype' => 'Subtype can only be updated for members.',
            ]);
        }

        $user->subtype = $subtype;
        $user->save();

        return $user->refresh();
    }

    /** @throws AuthorizationException */
    public function role(User $user, UserRole $role): User
    {
        Gate::authorize('super-admin');

        if ($user->is_super_admin && $role === UserRole::Member) {
            throw new AuthorizationException('Super-admin accounts cannot be demoted.');
        }

        if ($user->role === UserRole::Admin && $role === UserRole::Member) {
            $adminCount = User::query()->where('role', UserRole::Admin)->count();

            if ($adminCount <= 1) {
                throw new AuthorizationException('At least one admin account must remain.');
            }
        }

        $user->role = $role;

        if ($role === UserRole::Member) {
            $user->is_super_admin = false;
        }

        $user->save();

        return $user->refresh();
    }
}
