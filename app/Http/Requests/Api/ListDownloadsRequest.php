<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class ListDownloadsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'owners' => ['sometimes', 'string', 'regex:/^[1-9]\d*(,[1-9]\d*)*$/'],
            'page' => ['sometimes', 'array'],
            'page.number' => ['sometimes', 'integer', 'min:1'],
            'page.size' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ownerIds(): array
    {
        $owners = $this->query('owners');

        if (! is_string($owners) || $owners === '') {
            return [];
        }

        $ownerIds = collect(explode(',', $owners))
            ->map(static fn (string $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_values($ownerIds);
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
