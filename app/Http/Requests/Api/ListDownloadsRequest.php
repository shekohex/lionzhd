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
            'owners' => ['sometimes', 'string'],
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
            ->map(static fn (string $id): string => mb_trim($id))
            ->filter(static fn (string $id): bool => $id !== '' && ctype_digit($id))
            ->map(static fn (string $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
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
