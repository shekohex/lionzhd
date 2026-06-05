<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class ListWatchlistRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'string', Rule::in(['movies', 'series'])],
        ];
    }

    public function filter(): ?string
    {
        $filter = $this->query('filter');

        return is_string($filter) ? $filter : null;
    }
}
