<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\UserSubtype;
use Illuminate\Validation\Rule;

final class UpdateSettingsUserSubtypeRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['subtype' => ['required', Rule::in([UserSubtype::Internal->value, UserSubtype::External->value])]];
    }
}
