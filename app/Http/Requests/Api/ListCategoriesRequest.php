<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\MediaType;
use Illuminate\Validation\Rule;

final class ListCategoriesRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'for' => ['sometimes', 'string', Rule::enum(MediaType::class)],
        ];
    }

    public function mediaType(): ?MediaType
    {
        $value = $this->input('for');

        return is_string($value) ? MediaType::tryFrom($value) : null;
    }
}
