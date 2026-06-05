<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class PauseSchedulesRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['paused' => ['required', 'boolean']];
    }
}
