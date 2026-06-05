<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class StoreWatchlistRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_type' => ['required', 'string', Rule::in(['movie', 'series'])],
            'media_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function mediaType(): string
    {
        return (string) $this->input('media_type');
    }

    public function mediaId(): int
    {
        return (int) $this->input('media_id');
    }
}
