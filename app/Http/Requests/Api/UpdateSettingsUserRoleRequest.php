<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

final class UpdateSettingsUserRoleRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Member->value])]];
    }
}
