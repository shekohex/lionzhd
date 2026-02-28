<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoEpisodes;

use App\Enums\AutoEpisodes\MonitorScheduleType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreSeriesMonitorRequest extends FormRequest
{
    public function rules(): array
    {
        $presetTimes = config('auto_episodes.preset_times', []);
        $maxPerRunCap = max(1, (int) config('auto_episodes.max_per_run_cap', 100));

        if (! is_array($presetTimes)) {
            $presetTimes = [];
        }

        return [
            'timezone' => ['required', 'timezone'],
            'schedule_type' => ['required', Rule::in(array_map(static fn (MonitorScheduleType $type): string => $type->value, MonitorScheduleType::cases()))],
            'schedule_daily_time' => ['nullable', 'string', Rule::in($presetTimes)],
            'schedule_weekly_days' => ['nullable', 'array', 'list'],
            'schedule_weekly_days.*' => ['integer', 'between:0,6'],
            'schedule_weekly_time' => ['nullable', 'string', Rule::in($presetTimes)],
            'monitored_seasons' => ['present', 'array', 'list'],
            'monitored_seasons.*' => ['integer', 'min:1'],
            'per_run_cap' => ['sometimes', 'integer', 'min:1', "max:{$maxPerRunCap}"],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $scheduleType = $this->input('schedule_type');

            if ($scheduleType === MonitorScheduleType::Daily->value && ! is_string($this->input('schedule_daily_time'))) {
                $validator->errors()->add('schedule_daily_time', 'Daily schedule requires one preset time.');
            }

            if ($scheduleType === MonitorScheduleType::Weekly->value) {
                $weeklyDays = $this->input('schedule_weekly_days');

                if (! is_array($weeklyDays) || $weeklyDays === []) {
                    $validator->errors()->add('schedule_weekly_days', 'Weekly schedule requires at least one day.');
                }

                if (! is_string($this->input('schedule_weekly_time'))) {
                    $validator->errors()->add('schedule_weekly_time', 'Weekly schedule requires one preset time.');
                }
            }
        });
    }
}
