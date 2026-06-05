<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class ShowMovieRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include' => ['sometimes', 'string', Rule::in(['vod-info'])],
        ];
    }

    public function includesVodInfo(): bool
    {
        return $this->query('include') === 'vod-info';
    }
}
