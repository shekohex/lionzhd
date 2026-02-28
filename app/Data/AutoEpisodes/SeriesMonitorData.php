<?php

declare(strict_types=1);

namespace App\Data\AutoEpisodes;

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Enums\AutoEpisodes\SeriesMonitorRunStatus;
use App\Models\AutoEpisodes\SeriesMonitor as SeriesMonitorModel;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SeriesMonitorData extends Data
{
    public function __construct(
        public int $id,
        public int $series_id,
        public ?string $series_name,
        public ?string $series_cover,
        public bool $enabled,
        public string $timezone,
        public MonitorScheduleType $schedule_type,
        public ?string $schedule_daily_time,
        #[LiteralTypeScriptType('number[]')]
        public array $schedule_weekly_days,
        public ?string $schedule_weekly_time,
        #[LiteralTypeScriptType('number[]')]
        public array $monitored_seasons,
        public int $per_run_cap,
        public ?CarbonImmutable $next_run_at,
        public ?CarbonImmutable $last_attempt_at,
        public ?SeriesMonitorRunStatus $last_attempt_status,
        public ?CarbonImmutable $last_successful_check_at,
        public ?CarbonImmutable $run_now_available_at,
    ) {}

    public static function fromModel(SeriesMonitorModel $monitor): self
    {
        $scheduleType = $monitor->schedule_type;

        if (! $scheduleType instanceof MonitorScheduleType) {
            $scheduleType = MonitorScheduleType::Hourly;
        }

        $lastAttemptStatus = $monitor->last_attempt_status;

        if (! $lastAttemptStatus instanceof SeriesMonitorRunStatus) {
            $lastAttemptStatus = null;
        }

        return new self(
            id: $monitor->id,
            series_id: $monitor->series_id,
            series_name: $monitor->series?->name,
            series_cover: $monitor->series?->cover,
            enabled: $monitor->enabled,
            timezone: $monitor->timezone,
            schedule_type: $scheduleType,
            schedule_daily_time: $monitor->schedule_daily_time,
            schedule_weekly_days: self::normalizeIntList($monitor->schedule_weekly_days),
            schedule_weekly_time: $monitor->schedule_weekly_time,
            monitored_seasons: self::normalizeIntList($monitor->monitored_seasons),
            per_run_cap: $monitor->per_run_cap,
            next_run_at: $monitor->next_run_at,
            last_attempt_at: $monitor->last_attempt_at,
            last_attempt_status: $lastAttemptStatus,
            last_successful_check_at: $monitor->last_successful_check_at,
            run_now_available_at: $monitor->run_now_available_at,
        );
    }

    private static function normalizeIntList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = array_values(array_unique(array_map(static fn (mixed $item): int => (int) $item, $value)));
        sort($normalized);

        return $normalized;
    }
}
