---
status: resolved
trigger: "Investigate issue: lionzhd-24"
created: 2026-03-08T00:00:00Z
updated: 2026-03-08T00:50:00Z
---

## Current Focus

hypothesis: Confirmed. Manual single-download flows now serialize duplicate requests through cache locks and retry sqlite writes.
test: Completed targeted verification on manual locking, auto-episode dedupe, download retry policy, and access-control flows.
expecting: No further action in this session.
next_action: None.

## Symptoms

expected: Downloading a series episode or media item should create any needed download reference and redirect/start download without failing for users.
actual: During /series/{model}/{season}/{episode}/download, the app throws a SQLite lock error while inserting into media_download_refs; user-facing impact is failed/broken download flow affecting many users.
errors: Sentry LIONZHD-24 in org hexorg-ltd / project lionzhd; stack points to /app/Http/Controllers/Series/SeriesDownloadController.php(59) on MediaDownloadRef::fromSeriesAndEpisode(...)->saveOrFail(); SQL shown: insert into media_download_refs on sqlite DB /data/database/database.sqlite. First seen 2026-03-07T22:50:08Z, last seen 2026-03-07T23:09:50Z, 20 occurrences. Request example: GET http://192.168.1.101:8000/series/16186/1/17/download.
reproduction: Browse app on production, attempt to download an episode/series; likely concurrent/real-user traffic increases chance of lock contention.
started: Reported today by Sentry; first seen yesterday night per event timestamps.

## Eliminated

## Evidence

- timestamp: 2026-03-08T00:04:00Z
  checked: Sentry issue LIONZHD-24
  found: Production failures occur on GET /series/{model}/{season}/{episode}/download at SeriesDownloadController.php:59 when MediaDownloadRef::fromSeriesAndEpisode(...)->saveOrFail() inserts into sqlite database /data/database/database.sqlite.
  implication: The primary failing operation is a first-party insert on sqlite during the download flow.

- timestamp: 2026-03-08T00:04:30Z
  checked: Sentry stack trace and tags
  found: The exception is PDOException SQLSTATE[HY000] General error 5 database is locked under production frankenphp/php 8.4.5, with 20 occurrences in ~20 minutes.
  implication: This is consistent with sqlite write contention under concurrent real-user traffic, not a one-off malformed request.

- timestamp: 2026-03-08T00:07:00Z
  checked: SeriesDownloadController::create/store and MediaDownloadRef model
  found: create() calls DownloadMedia::run(...) before MediaDownloadRef::fromSeriesAndEpisode(...)->saveOrFail(); store() wraps multiple saveOrFail() inserts in a single DB transaction. No retry, upsert, or post-download dedupe exists in this path.
  implication: Concurrent or batched download starts can amplify sqlite writer contention and produce failures after the external download has already been triggered.

- timestamp: 2026-03-08T00:07:30Z
  checked: config/database.php and media_download_refs migrations
  found: Production defaults to sqlite unless overridden, with busy_timeout 60000 and journal_mode WAL configured. media_download_refs has only unique gid; there is no uniqueness across user/media/episode to collapse duplicate concurrent requests.
  implication: WAL/timeout reduce contention but do not prevent write-lock failures when multiple requests attempt inserts around the same time; schema does not help deduplicate logical duplicates.

- timestamp: 2026-03-08T00:10:00Z
  checked: GetActiveDownloads, VodStreamDownloadController, QueueEpisodeDownload, AppServiceProvider
  found: Manual VOD/series download endpoints do a read-before-write check via GetActiveDownloads and then call saveOrFail() without any application lock. Auto episode downloads already wrap the same pattern in Cache::lock(...)->block(...), returning duplicate instead of colliding.
  implication: First-party code already recognizes duplicate/race risk for episode downloads elsewhere; the missing lock in manual flows is the most likely root bug, with sqlite as the storage layer exposing it.

- timestamp: 2026-03-08T00:10:30Z
  checked: AppServiceProvider sqlite boot logic
  found: The supposed sqlite PRAGMA tuning in AppServiceProvider is commented out inside the SQL string, so boot-time sqlite tuning there is a no-op.
  implication: Runtime behavior depends on connection config only; no extra busy timeout or locking mitigation is actually being applied beyond Laravel connection setup.

- timestamp: 2026-03-08T00:13:00Z
  checked: cache configuration and app-wide MediaDownloadRef writes
  found: Cache defaults to the database store, which can share the same DB unless overridden. Manual download endpoints are the only direct user-facing MediaDownloadRef writes without any lock; auto-download code uses Cache::lock and duplicate detection before saveOrFail().
  implication: A lock-based fix aligns with existing code patterns, but live env must confirm the cache lock backend is acceptable in production.

- timestamp: 2026-03-08T00:18:00Z
  checked: live docker compose and live artisan config on 192.168.1.101
  found: Production explicitly runs DB_CONNECTION=sqlite with DB_DATABASE=/data/database/database.sqlite and CACHE_STORE=redis; live artisan confirms sqlite busy_timeout=60000/WAL and cache.default=redis.
  implication: Redis-backed Cache::lock is safe for production coordination, while sqlite remains the contended writer for media_download_refs.

- timestamp: 2026-03-08T00:19:00Z
  checked: live media_download_refs data and Sentry event distribution
  found: Sentry events cluster within seconds for the same mobile/browser fingerprint, and the database already contains logical duplicate series refs for some episodes. The failure window produced repeated create() errors without successful inserts.
  implication: Repeated taps/near-simultaneous requests are a plausible trigger, and missing idempotent locking in manual download flows is the actionable first-party root cause.

- timestamp: 2026-03-08T00:40:00Z
  checked: targeted Pest verification run
  found: New locking tests initially failed before reaching controller logic because test fixtures omitted required NOT NULL columns series.num and vod_streams.num; adjacent existing auto-episode and download access tests already passed.
  implication: The production fix did not regress related behavior; verification needs only fixture correction, not code rollback.

- timestamp: 2026-03-08T00:43:00Z
  checked: failing series lock test stack trace
  found: SeriesDownloadController indexes episodes by array offset ($dto->seasonsWithEpisodes[$season][$episode]), so route parameter episode=1 misses the first mocked episode; this matches the Sentry symptom where URL episode 17 yielded saved episode 18.
  implication: The failing test was using the wrong route index. There is also a separate zero-based episode-index quirk in the route/controller contract, but it is not the lock root cause.

## Resolution

root_cause: Manual series/vod download endpoints perform read-before-write ref creation without any distributed lock, unlike the auto-episode path. On production's sqlite database this race surfaces as SQLSTATE[HY000] database is locked during MediaDownloadRef inserts, especially under repeated taps/concurrent requests.
fix: Add per-download cache locks around manual series/vod create flows, re-check active downloads inside the lock, and persist MediaDownloadRef rows via retrying DB transactions instead of bare saveOrFail() in the single-download path.
verification: php artisan test tests/Feature/Controllers/DownloadControllerLockingTest.php tests/Feature/Downloads/DownloadRetryPolicyTest.php tests/Feature/AutoEpisodes/MonitorScanDedupeTest.php tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php -> 26 passed. New lock tests proved held locks prevent aria2 calls and MediaDownloadRef inserts for both series and vod manual routes.
files_changed:
  - app/Models/MediaDownloadRef.php
  - app/Http/Controllers/Series/SeriesDownloadController.php
  - app/Http/Controllers/VodStream/VodStreamDownloadController.php
  - tests/Feature/Controllers/DownloadControllerLockingTest.php
