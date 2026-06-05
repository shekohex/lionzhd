<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

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
}
