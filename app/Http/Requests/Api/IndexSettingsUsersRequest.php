<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class IndexSettingsUsersRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page.size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page.number' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
