<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoEpisodes;

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSeriesMonitorRequest extends FormRequest
{
    public function rules(): array
    {
        $presetTimes = config('auto_episodes.preset_times', []);
        $maxPerRunCap = max(1, (int) config('auto_episodes.max_per_run_cap', 100));

        if (! is_array($presetTimes)) {
            $presetTimes = [];
        }

        return [
            'timezone' => ['sometimes', 'timezone'],
            'schedule_type' => ['sometimes', Rule::in(array_map(static fn (MonitorScheduleType $type): string => $type->value, MonitorScheduleType::cases()))],
            'schedule_daily_time' => ['sometimes', 'nullable', 'string', Rule::in($presetTimes)],
            'schedule_weekly_days' => ['sometimes', 'array', 'list', 'min:1'],
            'schedule_weekly_days.*' => ['integer', 'between:0,6'],
            'schedule_weekly_time' => ['sometimes', 'nullable', 'string', Rule::in($presetTimes)],
            'monitored_seasons' => ['sometimes', 'array', 'list'],
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

            $monitor = $this->existingMonitor();
            $scheduleType = $this->resolvedScheduleType($monitor);

            if (! is_string($scheduleType)) {
                return;
            }

            if ($scheduleType === MonitorScheduleType::Daily->value) {
                $dailyTime = $this->exists('schedule_daily_time')
                    ? $this->input('schedule_daily_time')
                    : $monitor?->schedule_daily_time;

                if (! is_string($dailyTime)) {
                    $validator->errors()->add('schedule_daily_time', 'Daily schedule requires one preset time.');
                }
            }

            if ($scheduleType === MonitorScheduleType::Weekly->value) {
                $weeklyDays = $this->exists('schedule_weekly_days')
                    ? $this->input('schedule_weekly_days')
                    : $monitor?->schedule_weekly_days;

                if (! is_array($weeklyDays) || $weeklyDays === []) {
                    $validator->errors()->add('schedule_weekly_days', 'Weekly schedule requires at least one day.');
                }

                $weeklyTime = $this->exists('schedule_weekly_time')
                    ? $this->input('schedule_weekly_time')
                    : $monitor?->schedule_weekly_time;

                if (! is_string($weeklyTime)) {
                    $validator->errors()->add('schedule_weekly_time', 'Weekly schedule requires one preset time.');
                }
            }
        });
    }

    private function resolvedScheduleType(?SeriesMonitor $monitor): ?string
    {
        $scheduleType = $this->input('schedule_type');

        if (is_string($scheduleType)) {
            return $scheduleType;
        }

        if (! $monitor instanceof SeriesMonitor) {
            return null;
        }

        return $monitor->schedule_type instanceof MonitorScheduleType
            ? $monitor->schedule_type->value
            : (is_string($monitor->schedule_type) ? $monitor->schedule_type : null);
    }

    private function existingMonitor(): ?SeriesMonitor
    {
        $user = $this->user();
        $series = $this->route('model');

        if (! $user instanceof User || ! $series instanceof Series) {
            return null;
        }

        return SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $series->series_id)
            ->first();
    }
}
