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

        $this->merge([
            'delete_partial' => $this->boolean('delete_partial'),
        ]);
    }
}
