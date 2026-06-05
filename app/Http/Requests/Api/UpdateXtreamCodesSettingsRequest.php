<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class UpdateXtreamCodesSettingsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'host' => ['sometimes', 'required', 'string'],
            'port' => ['sometimes', 'required', 'integer', 'between:1,65535'],
            'username' => ['sometimes', 'required', 'string'],
            'password' => ['sometimes', 'required', 'string'],
        ];
    }
}
