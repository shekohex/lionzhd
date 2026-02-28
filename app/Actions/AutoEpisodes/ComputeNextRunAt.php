<?php

declare(strict_types=1);

namespace App\Actions\AutoEpisodes;

use App\Concerns\AsAction;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ComputeNextRunAt
{
    use AsAction;

    public function __invoke(
        CarbonImmutable $nowUtc,
        string $timezone,
        MonitorScheduleType $scheduleType,
        ?string $dailyTime = null,
        array $weeklyDays0Sun = [],
        ?string $weeklyTime = null,
    ): CarbonImmutable {
        $localNow = $nowUtc->setTimezone($timezone);

        $nextRunAt = match ($scheduleType) {
            MonitorScheduleType::Hourly => $localNow->startOfHour()->addHour(),
            MonitorScheduleType::Daily => $this->computeDailyNextRunAt($localNow, $this->resolveDailyTime($dailyTime)),
            MonitorScheduleType::Weekly => $this->computeWeeklyNextRunAt(
                $localNow,
                $this->resolveWeeklyDays($weeklyDays0Sun),
                $this->resolveWeeklyTime($weeklyTime),
            ),
        };

        return $nextRunAt->setTimezone('UTC');
    }

    private function computeDailyNextRunAt(CarbonImmutable $localNow, string $time): CarbonImmutable
    {
        [$hour, $minute] = $this->parseTime($time);

        $candidate = $localNow->setTime($hour, $minute, 0);

        if ($candidate->lessThanOrEqualTo($localNow)) {
            return $candidate->addDay();
        }

        return $candidate;
    }

    private function computeWeeklyNextRunAt(CarbonImmutable $localNow, array $days0Sun, string $time): CarbonImmutable
    {
        [$hour, $minute] = $this->parseTime($time);

        $nextRunAt = null;

        foreach ($days0Sun as $dayOfWeek) {
            $dayOffset = ($dayOfWeek - $localNow->dayOfWeek + 7) % 7;
            $candidate = $localNow->addDays($dayOffset)->setTime($hour, $minute, 0);

            if ($candidate->lessThanOrEqualTo($localNow)) {
                $candidate = $candidate->addWeek();
            }

            if ($nextRunAt === null || $candidate->lessThan($nextRunAt)) {
                $nextRunAt = $candidate;
            }
        }

        if (! $nextRunAt instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Weekly schedule requires at least one valid day (0=Sunday..6=Saturday).');
        }

        return $nextRunAt;
    }

    private function resolveDailyTime(?string $dailyTime): string
    {
        $resolved = $dailyTime ?? $this->defaultPresetTime();

        $this->assertPresetTime($resolved, 'daily');

        return $resolved;
    }

    private function resolveWeeklyTime(?string $weeklyTime): string
    {
        $resolved = $weeklyTime ?? $this->defaultPresetTime();

        $this->assertPresetTime($resolved, 'weekly');

        return $resolved;
    }

    private function resolveWeeklyDays(array $weeklyDays0Sun): array
    {
        if ($weeklyDays0Sun === []) {
            throw new InvalidArgumentException('Weekly schedule requires at least one day (0=Sunday..6=Saturday).');
        }

        $normalizedDays = [];

        foreach ($weeklyDays0Sun as $day) {
            if (! is_int($day) || $day < 0 || $day > 6) {
                throw new InvalidArgumentException('Weekly schedule day values must be integers between 0 and 6.');
            }

            $normalizedDays[$day] = true;
        }

        $days = array_keys($normalizedDays);
        sort($days);

        return $days;
    }

    private function defaultPresetTime(): string
    {
        $presetTimes = config('auto_episodes.preset_times', []);

        if (! is_array($presetTimes) || $presetTimes === []) {
            throw new InvalidArgumentException('Config auto_episodes.preset_times must contain at least one HH:MM value.');
        }

        $firstPreset = $presetTimes[0];

        if (! is_string($firstPreset)) {
            throw new InvalidArgumentException('Config auto_episodes.preset_times values must be HH:MM strings.');
        }

        return $firstPreset;
    }

    private function assertPresetTime(string $time, string $scheduleType): void
    {
        $this->parseTime($time);

        $presetTimes = config('auto_episodes.preset_times', []);

        if (! is_array($presetTimes) || ! in_array($time, $presetTimes, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s schedule time [%s]. Allowed values must come from config auto_episodes.preset_times.',
                $scheduleType,
                $time,
            ));
        }
    }

    private function parseTime(string $time): array
    {
        if (! preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $time)) {
            throw new InvalidArgumentException(sprintf('Invalid time format [%s]. Expected HH:MM (24h).', $time));
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        return [$hour, $minute];
    }
}
