<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class ListMoviesRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'array'],
            'page.number' => ['sometimes', 'integer', 'min:1'],
            'page.size' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function pageSize(): int
    {
        return (int) $this->input('page.size', 15);
    }
}
