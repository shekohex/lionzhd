<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class ShowSeriesRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include' => ['sometimes', 'string', Rule::in(['episodes'])],
        ];
    }

    public function includesEpisodes(): bool
    {
        return $this->query('include') === 'episodes';
    }
}
