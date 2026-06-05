<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class BulkApplySchedulesRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'series_ids' => ['required', 'array', 'list', 'min:1'],
            'series_ids.*' => ['integer', 'min:1', 'distinct'],
            'preset' => ['required', Rule::in(['hourly', 'daily', 'weekly'])],
        ];
    }
}
