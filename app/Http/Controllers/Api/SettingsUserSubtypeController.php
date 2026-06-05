<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Admin\UpdateUserSettings;
use App\Enums\UserSubtype;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateSettingsUserSubtypeRequest;
use App\Http\Resources\Api\AdminUserResource;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class SettingsUserSubtypeController extends Controller
{
    public function update(UpdateSettingsUserSubtypeRequest $request, User $user, UpdateUserSettings $updateUserSettings): AdminUserResource
    {
        Gate::authorize('admin');

        return new AdminUserResource($updateUserSettings->subtype($user, UserSubtype::from((string) $request->validated('subtype'))));
    }
}
