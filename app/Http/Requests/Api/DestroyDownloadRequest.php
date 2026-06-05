<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class DestroyDownloadRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delete_partial' => ['sometimes', 'boolean'],
        ];
    }

    public function deletePartial(): bool
    {
        return (bool) $this->boolean('delete_partial');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('delete_partial')) {
            return;
        }

        $value = $this->input('delete_partial');

        if (is_bool($value)) {
            return;
        }

        if (! is_string($value) && ! is_int($value)) {
            return;
        }

        $normalized = match (mb_strtolower((string) $value)) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => null,
        };

        if ($normalized === null) {
            return;
        }

        $this->merge([
            'delete_partial' => $normalized,
        ]);
    }
}
