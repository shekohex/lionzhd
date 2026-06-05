<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class ListMoviesRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'array'],
            'page.number' => ['sometimes', 'integer', 'min:1'],
            'page.size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'category' => [
                'sometimes',
                'string',
                Rule::exists('categories', 'provider_id')->where('in_vod', true),
            ],
        ];
    }

    public function categoryId(): ?string
    {
        $category = $this->query('category');

        if (! is_string($category)) {
            return null;
        }

        $category = mb_trim($category);

        return $category === '' ? null : $category;
    }

    public function pageSize(): int
    {
        return (int) $this->input('page.size', 15);
    }

    public function pageNumber(): int
    {
        return (int) $this->input('page.number', 1);
    }
}
