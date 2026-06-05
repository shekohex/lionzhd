<?php

declare(strict_types=1);

use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Jobs\RefreshMediaContents;
use App\Jobs\SyncCategories;
use App\Models\Aria2Config;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns and updates typed admin config resources', function (): void {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('external-api', ['admin'])->plainTextToken;
    Aria2Config::query()->create(['host' => 'http://aria2.test', 'port' => 6800, 'secret' => 'old', 'use_ssl' => false]);

    $this->withToken($token)
        ->getJson('/api/v1/settings/aria2', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'settings')
        ->assertJsonPath('data.id', 'aria2')
        ->assertJsonPath('data.attributes.port', 6800);

    $this->withToken($token)
        ->patchJson('/api/v1/settings/aria2', [
            'host' => 'https://aria2.test',
            'port' => 6801,
            'secret' => 'new-secret',
            'use_ssl' => true,
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.host', 'https://aria2.test')
        ->assertJsonPath('data.attributes.port', 6801)
        ->assertJsonPath('data.attributes.use_ssl', true);
});

it('queues sync jobs through admin settings api and rejects non admins', function (): void {
    Queue::fake();

    $member = User::factory()->memberInternal()->create();
    $admin = User::factory()->admin()->create();
    $memberToken = $member->createToken('external-api', ['admin'])->plainTextToken;
    $adminToken = $admin->createToken('external-api', ['admin'])->plainTextToken;

    $this->withToken($memberToken)
        ->patchJson('/api/v1/settings/sync-media', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.detail', 'Admin-only');

    auth()->forgetGuards();

    $this->flushHeaders()
        ->withToken($adminToken)
        ->patchJson('/api/v1/settings/sync-media', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.type', 'settings-actions')
        ->assertJsonPath('data.attributes.status', 'queued');

    Queue::assertPushed(RefreshMediaContents::class);

    $this->flushHeaders()
        ->withToken($adminToken)
        ->patchJson('/api/v1/settings/sync-categories', ['force_empty_vod' => true], ['Accept' => 'application/vnd.api+json'])
        ->assertOk();

    Queue::assertPushed(SyncCategories::class);
});

it('lists users with pagination and enforces super admin role updates', function (): void {
    $admin = User::factory()->admin()->create(['is_super_admin' => false]);
    $superAdmin = User::factory()->admin()->create(['is_super_admin' => true]);
    $member = User::factory()->memberExternal()->create();
    $adminToken = $admin->createToken('external-api', ['admin', 'super-admin'])->plainTextToken;
    $superToken = $superAdmin->createToken('external-api', ['admin', 'super-admin'])->plainTextToken;

    $this->withToken($adminToken)
        ->getJson('/api/v1/settings/users?page[size]=1', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.0.type', 'users')
        ->assertJsonPath('meta.per_page', 1);

    Sanctum::actingAs($admin, ['admin', 'super-admin']);

    $this->flushHeaders()
        ->patchJson("/api/v1/settings/users/{$member->id}/role", ['role' => 'admin'], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden();

    auth()->forgetGuards();

    $this->flushHeaders()
        ->withToken($superToken)
        ->patchJson("/api/v1/settings/users/{$member->id}/role", ['role' => 'admin'], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.role', 'admin');
});

it('returns and updates monitoring schedule settings for monitoring admins', function (): void {
    $user = User::factory()->memberInternal()->create();
    $token = $user->createToken('external-api', ['monitoring:admin'])->plainTextToken;
    $series = settingsApiCreateSeries(801);
    SeriesMonitor::query()->create([
        'user_id' => $user->id,
        'series_id' => $series->series_id,
        'watchlist_id' => $user->watchlists()->create(['watchable_type' => Series::class, 'watchable_id' => $series->series_id])->id,
        'enabled' => true,
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly,
        'schedule_daily_time' => null,
        'schedule_weekly_days' => [],
        'schedule_weekly_time' => null,
        'monitored_seasons' => [1],
        'per_run_cap' => 5,
        'next_run_at' => now()->addHour(),
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/settings/schedules', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.id', 'schedules')
        ->assertJsonPath('data.attributes.monitors.0.series_id', 801);

    $this->withToken($token)
        ->patchJson('/api/v1/settings/schedules/pause', ['paused' => true], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.is_paused', true);
});

function settingsApiCreateSeries(int $seriesId): Series
{
    /** @var Series $series */
    $series = Series::withoutSyncingToSearch(static function () use ($seriesId): Series {
        $series = new Series;
        $series->forceFill([
            'series_id' => $seriesId,
            'num' => $seriesId,
            'name' => 'Settings Series',
            'cover' => 'https://example.test/cover.jpg',
            'plot' => 'Plot',
            'cast' => 'Cast',
            'director' => 'Director',
            'genre' => 'Drama',
            'releaseDate' => '2026-01-01',
            'last_modified' => now(),
            'rating' => 8.0,
            'rating_5based' => 4.0,
            'backdrop_path' => ['https://example.test/backdrop.jpg'],
            'category_id' => 'drama',
        ])->save();

        return $series;
    });

    return $series;
}
