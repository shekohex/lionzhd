<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class BackfillSeriesMonitoringRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presetCounts = config('auto_episodes.backfill_preset_counts', []);

        if (! is_array($presetCounts)) {
            $presetCounts = [];
        }

        return [
            'backfill_count' => ['required', 'integer', Rule::in(array_values(array_map(static fn (mixed $count): int => (int) $count, $presetCounts)))],
        ];
    }
}
