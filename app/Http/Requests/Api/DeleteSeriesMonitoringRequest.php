<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class DeleteSeriesMonitoringRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'remove_from_watchlist' => ['sometimes', 'boolean'],
        ];
    }

    public function removeFromWatchlist(): bool
    {
        return $this->boolean('remove_from_watchlist');
    }
}
