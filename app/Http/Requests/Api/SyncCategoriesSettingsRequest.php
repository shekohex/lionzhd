<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class SyncCategoriesSettingsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'force_empty_vod' => ['sometimes', 'boolean'],
            'force_empty_series' => ['sometimes', 'boolean'],
        ];
    }
}
