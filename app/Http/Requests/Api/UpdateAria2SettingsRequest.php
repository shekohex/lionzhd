<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class UpdateAria2SettingsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'host' => ['sometimes', 'required', 'string'],
            'port' => ['sometimes', 'required', 'integer', 'between:1,65535'],
            'secret' => ['sometimes', 'required', 'string'],
            'use_ssl' => ['sometimes', 'boolean'],
        ];
    }
}
