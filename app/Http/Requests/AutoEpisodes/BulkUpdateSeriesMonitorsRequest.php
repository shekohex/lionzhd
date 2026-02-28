<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoEpisodes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkUpdateSeriesMonitorsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'series_ids' => ['required', 'array', 'list', 'min:1'],
            'series_ids.*' => ['integer', 'min:1', 'distinct'],
            'preset' => ['required', Rule::in(['hourly', 'daily', 'weekly'])],
        ];
    }
}
