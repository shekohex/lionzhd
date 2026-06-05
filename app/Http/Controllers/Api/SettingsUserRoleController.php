<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Admin\UpdateUserSettings;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateSettingsUserRoleRequest;
use App\Http\Resources\Api\AdminUserResource;
use App\Models\User;

final class SettingsUserRoleController extends Controller
{
    public function update(UpdateSettingsUserRoleRequest $request, User $user, UpdateUserSettings $updateUserSettings): AdminUserResource
    {
        return new AdminUserResource($updateUserSettings->role($user, UserRole::from((string) $request->validated('role'))));
    }
}
