<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoEpisodes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BackfillSeriesMonitorRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $presetCounts = config('auto_episodes.backfill_preset_counts', []);

        if (! is_array($presetCounts)) {
            $presetCounts = [];
        }

        return [
            'backfill_count' => [
                'required',
                'integer',
                Rule::in(array_values(array_map(static fn (mixed $count): int => (int) $count, $presetCounts))),
            ],
        ];
    }
}
