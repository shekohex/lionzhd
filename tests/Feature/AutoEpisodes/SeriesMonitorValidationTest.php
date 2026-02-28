<?php

declare(strict_types=1);

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns forbidden for external members before monitoring validation runs', function (): void {
    $user = User::factory()->memberExternal()->create();
    $series = seriesMonitorValidationCreateSeries();

    $response = $this->actingAs($user)->postJson(route('series.monitoring.store', ['model' => $series->series_id]), [
        'timezone' => 'Bad/Timezone',
        'schedule_type' => 'invalid',
    ]);

    $response->assertForbidden();
});

it('returns 422 for invalid store payloads for internal members', function (): void {
    $user = User::factory()->memberInternal()->create();
    $series = seriesMonitorValidationCreateSeries();
    seriesMonitorValidationCreateWatchlist($user, $series);

    $cases = [
        [array_replace(seriesMonitorValidationValidStorePayload(), ['timezone' => 'Not/AZone']), ['timezone']],
        [array_replace(seriesMonitorValidationValidStorePayload(), ['schedule_type' => 'monthly']), ['schedule_type']],
        [
            array_replace(seriesMonitorValidationValidStorePayload(), [
                'schedule_type' => MonitorScheduleType::Daily->value,
                'schedule_daily_time' => '00:13',
            ]),
            ['schedule_daily_time'],
        ],
        [
            array_replace(seriesMonitorValidationValidStorePayload(), [
                'schedule_type' => MonitorScheduleType::Weekly->value,
                'schedule_weekly_days' => [],
                'schedule_weekly_time' => seriesMonitorValidationPresetTime(),
            ]),
            ['schedule_weekly_days'],
        ],
        [
            array_replace(seriesMonitorValidationValidStorePayload(), [
                'schedule_type' => MonitorScheduleType::Weekly->value,
                'schedule_weekly_days' => [7],
                'schedule_weekly_time' => seriesMonitorValidationPresetTime(),
            ]),
            ['schedule_weekly_days.0'],
        ],
        [array_replace(seriesMonitorValidationValidStorePayload(), ['per_run_cap' => 0]), ['per_run_cap']],
    ];

    foreach ($cases as [$payload, $keys]) {
        $response = $this->actingAs($user)->postJson(route('series.monitoring.store', ['model' => $series->series_id]), $payload);
        $response->assertStatus(422)->assertJsonValidationErrors($keys);
    }
});

it('accepts valid store payloads and persists monitor schedule settings', function (): void {
    $user = User::factory()->memberInternal()->create();
    $series = seriesMonitorValidationCreateSeries();
    $watchlist = seriesMonitorValidationCreateWatchlist($user, $series);

    $response = $this->actingAs($user)->post(route('series.monitoring.store', ['model' => $series->series_id]), [
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Weekly->value,
        'schedule_weekly_days' => [4, 1, 1],
        'schedule_weekly_time' => seriesMonitorValidationPresetTime(),
        'monitored_seasons' => [2, 1, 1],
        'per_run_cap' => 7,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $monitor = SeriesMonitor::query()->where('user_id', $user->id)->where('series_id', $series->series_id)->firstOrFail();

    expect($monitor->watchlist_id)->toBe($watchlist->id)
        ->and($monitor->enabled)->toBeTrue()
        ->and($monitor->timezone)->toBe('UTC')
        ->and($monitor->schedule_type)->toBe(MonitorScheduleType::Weekly)
        ->and($monitor->schedule_weekly_days)->toBe([1, 4])
        ->and($monitor->schedule_weekly_time)->toBe(seriesMonitorValidationPresetTime())
        ->and($monitor->monitored_seasons)->toBe([1, 2])
        ->and($monitor->per_run_cap)->toBe(7)
        ->and($monitor->next_run_at)->not->toBeNull();
});

it('accepts empty monitored seasons as monitor all seasons on store', function (): void {
    $user = User::factory()->memberInternal()->create();
    $series = seriesMonitorValidationCreateSeries();
    seriesMonitorValidationCreateWatchlist($user, $series);

    $response = $this->actingAs($user)->postJson(route('series.monitoring.store', ['model' => $series->series_id]), [
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly->value,
        'monitored_seasons' => [],
    ]);

    $response->assertStatus(302);

    $monitor = SeriesMonitor::query()->where('user_id', $user->id)->where('series_id', $series->series_id)->firstOrFail();

    expect($monitor->monitored_seasons)->toBe([])
        ->and($monitor->schedule_type)->toBe(MonitorScheduleType::Hourly)
        ->and($monitor->next_run_at)->not->toBeNull();
});

it('returns 422 when update switches to daily without a valid preset time', function (): void {
    $user = User::factory()->memberInternal()->create();
    $series = seriesMonitorValidationCreateSeries();
    $watchlist = seriesMonitorValidationCreateWatchlist($user, $series);
    seriesMonitorValidationCreateMonitor($user, $series, $watchlist, [
        'schedule_type' => MonitorScheduleType::Hourly,
        'schedule_daily_time' => null,
        'schedule_weekly_days' => [],
        'schedule_weekly_time' => null,
    ]);

    $response = $this->actingAs($user)->patchJson(route('series.monitoring.update', ['model' => $series->series_id]), [
        'schedule_type' => MonitorScheduleType::Daily->value,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['schedule_daily_time']);
});

it('returns 422 when update includes weekly day outside 0 to 6', function (): void {
    $user = User::factory()->memberInternal()->create();
    $series = seriesMonitorValidationCreateSeries();
    $watchlist = seriesMonitorValidationCreateWatchlist($user, $series);
    seriesMonitorValidationCreateMonitor($user, $series, $watchlist);

    $response = $this->actingAs($user)->patchJson(route('series.monitoring.update', ['model' => $series->series_id]), [
        'schedule_type' => MonitorScheduleType::Weekly->value,
        'schedule_weekly_days' => [8],
        'schedule_weekly_time' => seriesMonitorValidationPresetTime(),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['schedule_weekly_days.0']);
});

it('returns 422 when update includes weekly time outside configured preset times', function (): void {
    $user = User::factory()->memberInternal()->create();
    $series = seriesMonitorValidationCreateSeries();
    $watchlist = seriesMonitorValidationCreateWatchlist($user, $series);
    seriesMonitorValidationCreateMonitor($user, $series, $watchlist);

    $response = $this->actingAs($user)->patchJson(route('series.monitoring.update', ['model' => $series->series_id]), [
        'schedule_type' => MonitorScheduleType::Weekly->value,
        'schedule_weekly_days' => [1, 5],
        'schedule_weekly_time' => '00:13',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['schedule_weekly_time']);
});

it('accepts partial update payloads and validates only provided fields', function (): void {
    $user = User::factory()->memberInternal()->create();
    $series = seriesMonitorValidationCreateSeries();
    $watchlist = seriesMonitorValidationCreateWatchlist($user, $series);
    $monitor = seriesMonitorValidationCreateMonitor($user, $series, $watchlist, [
        'schedule_type' => MonitorScheduleType::Daily,
        'schedule_daily_time' => seriesMonitorValidationPresetTime(),
        'schedule_weekly_days' => [],
        'schedule_weekly_time' => null,
        'monitored_seasons' => [1, 2],
        'per_run_cap' => 5,
    ]);

    $response = $this->actingAs($user)->patch(route('series.monitoring.update', ['model' => $series->series_id]), [
        'monitored_seasons' => [3, 1, 1],
        'per_run_cap' => 9,
    ]);

    $response->assertRedirect();

    $monitor->refresh();

    expect($monitor->schedule_type)->toBe(MonitorScheduleType::Daily)
        ->and($monitor->schedule_daily_time)->toBe(seriesMonitorValidationPresetTime())
        ->and($monitor->schedule_weekly_days)->toBe([])
        ->and($monitor->schedule_weekly_time)->toBeNull()
        ->and($monitor->monitored_seasons)->toBe([1, 3])
        ->and($monitor->per_run_cap)->toBe(9)
        ->and($monitor->next_run_at)->not->toBeNull();
});

it('returns 422 for invalid bulk preset payloads', function (): void {
    $user = User::factory()->memberInternal()->create();
    $seriesA = seriesMonitorValidationCreateSeries();
    $seriesB = seriesMonitorValidationCreateSeries();
    $watchlistA = seriesMonitorValidationCreateWatchlist($user, $seriesA);
    $watchlistB = seriesMonitorValidationCreateWatchlist($user, $seriesB);
    seriesMonitorValidationCreateMonitor($user, $seriesA, $watchlistA);
    seriesMonitorValidationCreateMonitor($user, $seriesB, $watchlistB);

    $response = $this->actingAs($user)->patchJson(route('schedules.bulk-apply'), [
        'series_ids' => [$seriesA->series_id, $seriesB->series_id],
        'preset' => 'invalid',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['preset']);
});

it('accepts valid bulk schedule preset updates', function (): void {
    $user = User::factory()->memberInternal()->create();
    $seriesA = seriesMonitorValidationCreateSeries();
    $seriesB = seriesMonitorValidationCreateSeries();
    $watchlistA = seriesMonitorValidationCreateWatchlist($user, $seriesA);
    $watchlistB = seriesMonitorValidationCreateWatchlist($user, $seriesB);
    seriesMonitorValidationCreateMonitor($user, $seriesA, $watchlistA, ['schedule_type' => MonitorScheduleType::Hourly]);
    seriesMonitorValidationCreateMonitor($user, $seriesB, $watchlistB, ['schedule_type' => MonitorScheduleType::Hourly]);

    $response = $this->actingAs($user)->patch(route('schedules.bulk-apply'), [
        'series_ids' => [$seriesA->series_id, $seriesB->series_id],
        'preset' => 'daily',
    ]);

    $response->assertRedirect();

    $monitors = SeriesMonitor::query()
        ->where('user_id', $user->id)
        ->whereIn('series_id', [$seriesA->series_id, $seriesB->series_id])
        ->orderBy('series_id')
        ->get();

    expect($monitors)->toHaveCount(2);

    foreach ($monitors as $monitor) {
        expect($monitor->schedule_type)->toBe(MonitorScheduleType::Daily)
            ->and($monitor->schedule_daily_time)->toBe(seriesMonitorValidationPresetTime())
            ->and($monitor->schedule_weekly_days)->toBe([])
            ->and($monitor->schedule_weekly_time)->toBeNull()
            ->and($monitor->next_run_at)->not->toBeNull();
    }
});

function seriesMonitorValidationPresetTime(): string
{
    $value = config('auto_episodes.preset_times.0');

    return is_string($value) ? $value : '06:00';
}

function seriesMonitorValidationValidStorePayload(array $overrides = []): array
{
    return array_replace([
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly->value,
        'monitored_seasons' => [1, 2],
        'per_run_cap' => 5,
    ], $overrides);
}

function seriesMonitorValidationCreateSeries(): Series
{
    static $seriesId = 70_000;

    $seriesId++;

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Validation Series %d', $seriesId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Series::query()->findOrFail($seriesId);
}

function seriesMonitorValidationCreateWatchlist(User $user, Series $series): Watchlist
{
    return Watchlist::query()->create([
        'user_id' => $user->id,
        'watchable_type' => Series::class,
        'watchable_id' => $series->series_id,
    ]);
}

function seriesMonitorValidationCreateMonitor(User $user, Series $series, Watchlist $watchlist, array $overrides = []): SeriesMonitor
{
    return SeriesMonitor::query()->create(array_replace([
        'user_id' => $user->id,
        'series_id' => $series->series_id,
        'watchlist_id' => $watchlist->id,
        'enabled' => true,
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly,
        'schedule_daily_time' => null,
        'schedule_weekly_days' => [],
        'schedule_weekly_time' => null,
        'monitored_seasons' => [1],
        'per_run_cap' => 5,
        'next_run_at' => now()->subMinute(),
        'run_now_available_at' => null,
    ], $overrides));
}
