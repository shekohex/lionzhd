<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\UserSubtype;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SettingsUsersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('admin');

        /** @var AnonymousResourceCollection $collection */
        $collection = AdminUserResource::collection(User::query()->select(['id', 'name', 'email', 'role', 'subtype', 'is_super_admin', 'created_at', 'updated_at'])->orderByDesc('is_super_admin')->orderBy('name')->paginate(
            perPage: (int) $request->integer('page.size', 15),
            pageName: 'page[number]',
            page: (int) $request->integer('page.number', 1),
        ));

        return $collection;
    }

    public function update(Request $request, User $user, string $operation): AdminUserResource
    {
        if ($operation === 'role') {
            Gate::authorize('super-admin');
            $validated = $request->validate(['role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Member->value])]]);
            $user->role = UserRole::from((string) $validated['role']);

            if ($user->role === UserRole::Member) {
                $user->is_super_admin = false;
            }
        } else {
            Gate::authorize('admin');
            $validated = $request->validate(['subtype' => ['required', Rule::in([UserSubtype::Internal->value, UserSubtype::External->value])]]);
            $user->subtype = UserSubtype::from((string) $validated['subtype']);
        }

        $user->save();

        return new AdminUserResource($user->refresh());
    }
}
