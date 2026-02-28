<?php

declare(strict_types=1);

namespace Tests\Feature\AutoEpisodes;

use App\Actions\AutoEpisodes\QueueEpisodeDownload;
use App\Actions\AutoEpisodes\ScanSeriesForNewEpisodes;
use App\Enums\AutoEpisodes\MonitorScheduleType;
use App\Enums\AutoEpisodes\SeriesMonitorEventType;
use App\Enums\AutoEpisodes\SeriesMonitorRunStatus;
use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\AddUriRequest;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\Aria2Config;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\AutoEpisodes\SeriesMonitorEpisode;
use App\Models\AutoEpisodes\SeriesMonitorEvent;
use App\Models\AutoEpisodes\SeriesMonitorRun;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

use function PHPUnit\Framework\assertArrayHasKey;

uses(RefreshDatabase::class);

it('keeps first scheduled scan as baseline and skips historical episodes', function (): void {
    $monitor = createMonitor(perRunCap: 2);

    bindXtreamSeriesInfo([
        episodePayload(id: '101', season: 1, episodeNum: 1),
        episodePayload(id: '102', season: 1, episodeNum: 2),
    ]);
    bindAria2AddUri('baseline-should-not-queue');

    ScanSeriesForNewEpisodes::run(monitorId: $monitor->id, trigger: SeriesMonitorRunTrigger::Scheduled);

    $run = SeriesMonitorRun::query()->latest('id')->firstOrFail();
    $monitor->refresh();

    expect(MediaDownloadRef::query()->count())->toBe(0)
        ->and($run->status)->toBe(SeriesMonitorRunStatus::Success)
        ->and($run->queued_count)->toBe(0)
        ->and($run->duplicate_count)->toBe(0)
        ->and($run->deferred_count)->toBe(0)
        ->and($run->error_count)->toBe(0)
        ->and($monitor->last_successful_check_at)->not->toBeNull()
        ->and($monitor->next_run_at)->not->toBeNull()
        ->and($monitor->last_attempt_status)->toBe(SeriesMonitorRunStatus::Success);

    expect(SeriesMonitorEpisode::query()->where('monitor_id', $monitor->id)->pluck('state')->all())
        ->toBe([SeriesMonitorEpisode::STATE_SKIPPED, SeriesMonitorEpisode::STATE_SKIPPED]);
});

it('queues oldest-first up to cap and defers the remainder', function (): void {
    $monitor = createMonitor(perRunCap: 1);

    bindXtreamSeriesInfo([
        episodePayload(id: '201', season: 1, episodeNum: 1),
        episodePayload(id: '202', season: 1, episodeNum: 2),
        episodePayload(id: '203', season: 2, episodeNum: 1),
    ]);
    bindAria2AddUri('cap-run-gid-1');

    $job = new RunMonitorScan($monitor->id, SeriesMonitorRunTrigger::Backfill, ['backfill_count' => 3]);
    $job->withFakeQueueInteractions()->assertNotFailed()->handle();

    $run = SeriesMonitorRun::query()->latest('id')->firstOrFail();
    $monitor->refresh();

    expect($run->status)->toBe(SeriesMonitorRunStatus::SuccessWithWarnings)
        ->and($run->queued_count)->toBe(1)
        ->and($run->deferred_count)->toBe(2)
        ->and($run->duplicate_count)->toBe(0)
        ->and($run->error_count)->toBe(0)
        ->and($monitor->last_successful_check_at)->not->toBeNull()
        ->and($monitor->next_run_at)->not->toBeNull()
        ->and($monitor->last_attempt_status)->toBe(SeriesMonitorRunStatus::SuccessWithWarnings)
        ->and(MediaDownloadRef::query()->count())->toBe(1);

    $statesByEpisode = SeriesMonitorEpisode::query()
        ->where('monitor_id', $monitor->id)
        ->orderBy('episode_id')
        ->pluck('state', 'episode_id')
        ->all();

    expect($statesByEpisode)->toBe([
        '201' => SeriesMonitorEpisode::STATE_QUEUED,
        '202' => SeriesMonitorEpisode::STATE_PENDING,
        '203' => SeriesMonitorEpisode::STATE_PENDING,
    ]);

    $eventTypes = SeriesMonitorEvent::query()
        ->where('run_id', $run->id)
        ->orderBy('id')
        ->get()
        ->map(fn (SeriesMonitorEvent $event): string => $event->type->value)
        ->all();

    expect($eventTypes)->toBe([
        SeriesMonitorEventType::Queued->value,
        SeriesMonitorEventType::Deferred->value,
        SeriesMonitorEventType::Deferred->value,
    ]);
});

it('does not create new download refs when a second scan sees the same queued episode set', function (): void {
    $monitor = createMonitor(perRunCap: 1);

    bindXtreamSeriesInfo([
        episodePayload(id: '301', season: 1, episodeNum: 1),
    ]);
    bindAria2AddUri('second-scan-initial-gid');

    ScanSeriesForNewEpisodes::run(monitorId: $monitor->id, trigger: SeriesMonitorRunTrigger::Backfill);
    ScanSeriesForNewEpisodes::run(monitorId: $monitor->id, trigger: SeriesMonitorRunTrigger::Scheduled);

    $latestRun = SeriesMonitorRun::query()->latest('id')->firstOrFail();

    expect(MediaDownloadRef::query()->count())->toBe(1)
        ->and($latestRun->status)->toBe(SeriesMonitorRunStatus::Success)
        ->and($latestRun->queued_count)->toBe(0)
        ->and($latestRun->duplicate_count)->toBe(0)
        ->and($latestRun->deferred_count)->toBe(0)
        ->and($latestRun->error_count)->toBe(0)
        ->and(SeriesMonitorEvent::query()->where('run_id', $latestRun->id)->count())->toBe(0);
});

it('records duplicate outcome when media download ref already exists', function (): void {
    $monitor = createMonitor(perRunCap: 2);

    bindXtreamSeriesInfo([
        episodePayload(id: '401', season: 1, episodeNum: 1),
    ]);
    $aria2Mock = bindAria2AddUri('duplicate-should-not-hit-aria2');

    MediaDownloadRef::query()->create([
        'gid' => 'pre-existing-duplicate-gid',
        'user_id' => $monitor->user_id,
        'media_id' => $monitor->series_id,
        'media_type' => Series::class,
        'downloadable_id' => 401,
        'season' => 1,
        'episode' => 1,
    ]);

    ScanSeriesForNewEpisodes::run(monitorId: $monitor->id, trigger: SeriesMonitorRunTrigger::Backfill);

    $run = SeriesMonitorRun::query()->latest('id')->firstOrFail();
    $event = SeriesMonitorEvent::query()->where('run_id', $run->id)->firstOrFail();

    expect(MediaDownloadRef::query()->count())->toBe(1)
        ->and($run->queued_count)->toBe(0)
        ->and($run->duplicate_count)->toBe(1)
        ->and($run->error_count)->toBe(0)
        ->and($event->type)->toBe(SeriesMonitorEventType::Duplicate)
        ->and($event->reason)->toBe('existing_download_ref');

    $aria2Mock->assertNotSent(AddUriRequest::class);
});

it('keeps queue action race-safe when invoked twice for same episode', function (): void {
    $monitor = createMonitor(perRunCap: 1)->load(['series']);

    $seriesInfo = SeriesInformation::fromJson($monitor->series_id, seriesInfoPayload([
        episodePayload(id: '501', season: 1, episodeNum: 1),
    ]));
    $episode = firstSeriesEpisode($seriesInfo);

    bindAria2AddUri('double-queue-gid-1');

    $first = QueueEpisodeDownload::run($monitor, $episode, $seriesInfo);
    $second = QueueEpisodeDownload::run($monitor, $episode, $seriesInfo);

    expect($first['status'])->toBe(QueueEpisodeDownload::STATUS_QUEUED)
        ->and($second['status'])->toBe(QueueEpisodeDownload::STATUS_DUPLICATE)
        ->and(MediaDownloadRef::query()->where('user_id', $monitor->user_id)
            ->where('media_type', Series::class)
            ->where('media_id', $monitor->series_id)
            ->where('downloadable_id', 501)
            ->count())
        ->toBe(1);
});

it('logs error and skips queueing when episode id is unsafe', function (): void {
    $monitor = createMonitor(perRunCap: 1);

    bindXtreamSeriesInfo([
        episodePayload(id: 'unsafe-id', season: 1, episodeNum: 1),
    ]);
    $aria2Mock = bindAria2AddUri('unsafe-should-not-queue');

    ScanSeriesForNewEpisodes::run(monitorId: $monitor->id, trigger: SeriesMonitorRunTrigger::Backfill);

    $run = SeriesMonitorRun::query()->latest('id')->firstOrFail();
    $event = SeriesMonitorEvent::query()->where('run_id', $run->id)->firstOrFail();
    assertArrayHasKey('raw_episode_id', $event->meta);

    expect(MediaDownloadRef::query()->count())->toBe(0)
        ->and($run->queued_count)->toBe(0)
        ->and($run->error_count)->toBe(1)
        ->and($event->type)->toBe(SeriesMonitorEventType::Error)
        ->and($event->reason)->toBe('unsafe_episode_id')
        ->and($event->meta['raw_episode_id'])->toBe('unsafe-id');

    $aria2Mock->assertNotSent(AddUriRequest::class);
});

function createMonitor(int $perRunCap): SeriesMonitor
{
    static $seriesId = 40_000;

    $seriesId++;
    $user = User::factory()->memberInternal()->create();

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return SeriesMonitor::query()->create([
        'user_id' => $user->id,
        'series_id' => $seriesId,
        'enabled' => true,
        'timezone' => 'UTC',
        'schedule_type' => MonitorScheduleType::Hourly,
        'schedule_daily_time' => null,
        'schedule_weekly_days' => [],
        'schedule_weekly_time' => null,
        'monitored_seasons' => [],
        'per_run_cap' => $perRunCap,
        'next_run_at' => now()->subMinute(),
    ]);
}

function bindXtreamSeriesInfo(array $episodes): MockClient
{
    $mockClient = new MockClient([
        GetSeriesInfoRequest::class => MockResponse::make(seriesInfoPayload($episodes), 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

function bindAria2AddUri(string $gid): MockClient
{
    $mockClient = new MockClient([
        AddUriRequest::class => MockResponse::make([
            'jsonrpc' => '2.0',
            'id' => $gid,
            'result' => $gid,
        ]),
    ]);

    app()->bind(JsonRpcConnector::class, static function () use ($mockClient): JsonRpcConnector {
        $connector = new JsonRpcConnector(app(Aria2Config::class));

        return $connector->withMockClient($mockClient);
    });

    return $mockClient;
}

function firstSeriesEpisode(SeriesInformation $seriesInfo): Episode
{
    foreach ($seriesInfo->seasonsWithEpisodes as $seasonEpisodes) {
        foreach ($seasonEpisodes as $episode) {
            if ($episode instanceof Episode) {
                return $episode;
            }
        }
    }

    throw new \RuntimeException('Expected at least one episode.');
}

function episodePayload(string $id, int $season, int $episodeNum): array
{
    return [
        'id' => $id,
        'season' => $season,
        'episode_num' => $episodeNum,
        'title' => sprintf('S%02dE%02d', $season, $episodeNum),
        'container_extension' => 'mkv',
        'custom_sid' => sprintf('sid-%s', $id),
        'added' => '2026-02-01 00:00:00',
        'direct_source' => '',
        'info' => [
            'duration_secs' => 2700,
            'duration' => '00:45:00',
            'bitrate' => 1000,
            'video' => [],
            'audio' => [],
        ],
    ];
}

function seriesInfoPayload(array $episodes): array
{
    $groupedEpisodes = [];

    foreach ($episodes as $episode) {
        $season = (string) $episode['season'];
        $groupedEpisodes[$season] ??= [];
        $groupedEpisodes[$season][] = $episode;
    }

    $seasonKeys = array_keys($groupedEpisodes);
    sort($seasonKeys);

    return [
        'info' => [
            'name' => 'Monitor Scan Series',
            'cover' => 'https://example.com/cover.jpg',
            'plot' => 'plot',
            'cast' => 'cast',
            'director' => 'director',
            'genre' => 'genre',
            'releaseDate' => '2026-01-01',
            'last_modified' => '2026-01-01 00:00:00',
            'rating' => '8.0',
            'rating_5based' => 4.0,
            'backdrop_path' => ['https://example.com/backdrop.jpg'],
            'youtube_trailer' => 'https://youtube.com/watch?v=test',
            'episode_run_time' => '00:45:00',
            'category_id' => '1',
        ],
        'seasons' => $seasonKeys,
        'episodes' => $groupedEpisodes,
    ];
}
