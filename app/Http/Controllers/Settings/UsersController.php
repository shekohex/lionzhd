<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Enums\UserSubtype;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'subtype', 'is_super_admin'])
            ->orderByDesc('is_super_admin')
            ->orderByRaw('case when role = ? then 0 else 1 end', [UserRole::Admin->value])
            ->orderBy('name')
            ->get()
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'subtype' => $user->subtype->value,
                'is_super_admin' => $user->is_super_admin,
            ])
            ->values();

        return Inertia::render('settings/users', [
            'users' => $users,
            'can_manage_admin_roles' => $request->user()?->is_super_admin ?? false,
        ]);
    }

    public function update(Request $request, User $user, string $operation): RedirectResponse
    {
        return match ($operation) {
            'subtype' => $this->updateUserSubtype($request, $user),
            'role' => $this->updateUserRole($request, $user),
            'transfer-super-admin' => $this->transferSuperAdmin($user),
            default => throw ValidationException::withMessages([
                'operation' => 'Unsupported user operation.',
            ]),
        };
    }

    private function updateUserSubtype(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'subtype' => ['required', Rule::in([UserSubtype::Internal->value, UserSubtype::External->value])],
        ]);

        if ($user->role !== UserRole::Member) {
            throw ValidationException::withMessages([
                'subtype' => 'Subtype can only be updated for members.',
            ]);
        }

        $user->subtype = UserSubtype::from($validated['subtype']);
        $user->save();

        return back()->with('success', 'User subtype updated successfully.');
    }

    private function updateUserRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('super-admin');

        $validated = $request->validate([
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Member->value])],
        ]);

        $role = UserRole::from($validated['role']);

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

        return back()->with('success', 'User role updated successfully.');
    }

    private function transferSuperAdmin(User $user): RedirectResponse
    {
        Gate::authorize('super-admin');

        if ($user->role !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'user' => 'Super-admin can only be transferred to an admin user.',
            ]);
        }

        DB::transaction(static function () use ($user): void {
            User::query()->where('is_super_admin', true)->update(['is_super_admin' => false]);

            $user->is_super_admin = true;
            $user->save();

            $superAdminCount = User::query()->where('is_super_admin', true)->count();

            if ($superAdminCount !== 1) {
                throw new RuntimeException('Super-admin transfer failed to preserve a single super-admin.');
            }
        });

        return back()->with('success', 'Super-admin transferred successfully.');
    }
}
