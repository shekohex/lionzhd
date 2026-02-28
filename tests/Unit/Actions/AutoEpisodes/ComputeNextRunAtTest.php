<?php

declare(strict_types=1);

use App\Actions\AutoEpisodes\ComputeNextRunAt;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

afterEach(function (): void {
    Date::setTestNow();
});

it('rounds hourly schedules to the next local hour boundary', function (): void {
    Date::setTestNow('2026-03-01 10:00:00 UTC');

    $nextRunAt = ComputeNextRunAt::run(
        nowUtc(),
        'UTC',
        MonitorScheduleType::Hourly,
    );

    expect($nextRunAt)->toBeInstanceOf(CarbonImmutable::class);
    expect($nextRunAt->timezoneName)->toBe('UTC');
    expect($nextRunAt->toDateTimeString())->toBe('2026-03-01 11:00:00');
});

it('computes daily schedules before and after the target local time', function (): void {
    Date::setTestNow('2026-01-15 06:30:00 UTC');

    $beforeTime = ComputeNextRunAt::run(
        nowUtc(),
        'Europe/Berlin',
        MonitorScheduleType::Daily,
        '09:00',
    );

    Date::setTestNow('2026-01-15 09:30:00 UTC');

    $afterTime = ComputeNextRunAt::run(
        nowUtc(),
        'Europe/Berlin',
        MonitorScheduleType::Daily,
        '09:00',
    );

    expect($beforeTime->toDateTimeString())->toBe('2026-01-15 08:00:00');
    expect($afterTime->toDateTimeString())->toBe('2026-01-16 08:00:00');
});

it('selects the nearest future weekly day candidate across multiple days', function (): void {
    Date::setTestNow('2026-01-05 15:00:00 UTC');

    $nextRunAt = ComputeNextRunAt::run(
        nowUtc(),
        'America/New_York',
        MonitorScheduleType::Weekly,
        weeklyDays0Sun: [1, 3, 5],
        weeklyTime: '09:00',
    );

    expect($nextRunAt->timezoneName)->toBe('UTC');
    expect($nextRunAt->toDateTimeString())->toBe('2026-01-07 14:00:00');
});

function nowUtc(): CarbonImmutable
{
    return Date::now('UTC')->toImmutable();
}
