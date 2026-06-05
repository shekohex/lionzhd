<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\MediaType;
use App\Enums\SearchSortby;
use Illuminate\Validation\Rule;

final class SearchRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1'],
            'page' => ['sometimes', 'array'],
            'page.number' => ['sometimes', 'integer', 'min:1'],
            'page.size' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'media_type' => ['sometimes', 'nullable', 'string', Rule::enum(MediaType::class)],
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::enum(SearchSortby::class)],
        ];
    }

    public function queryText(): string
    {
        return (string) $this->input('q');
    }

    public function pageSize(): int
    {
        return (int) $this->input('page.size', 10);
    }

    public function pageNumber(): int
    {
        return (int) $this->input('page.number', 1);
    }

    public function mediaType(): ?MediaType
    {
        $value = $this->input('media_type');

        return is_string($value) ? MediaType::tryFrom($value) : null;
    }

    public function sortBy(): ?SearchSortby
    {
        $value = $this->input('sort_by', SearchSortby::Latest->value);

        return is_string($value) ? SearchSortby::tryFrom($value) : null;
    }
}
