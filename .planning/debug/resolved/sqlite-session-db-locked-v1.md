---
status: resolved
trigger: "Investigate issue: sqlite-session-db-locked-v1"
created: 2026-03-04T20:20:15Z
updated: 2026-03-04T20:33:23Z
---

## Current Focus

hypothesis: Verified.
test: Completed.
expecting: Completed.
next_action: Return root cause and fix summary.

## Symptoms

expected: Web requests should start/read sessions without DB lock errors while scheduler + queue are active.
actual: App throws SQLite lock exception in Illuminate\Session\DatabaseSessionHandler->read from StartSession middleware.
errors: SQLSTATE[HY000]: General error: 5 database is locked on /data/database/database.sqlite during session table select.
reproduction: In deployed environment using SQLite DB + database session driver, run scheduler and queue workers concurrently (e.g., MonitorDownloads + DispatchDueMonitors) while handling HTTP requests that start sessions.
started: Started after recent v1 release; observed in production deployment.

## Eliminated

- hypothesis: Session read fails due to missing/slow index on sessions.id query path.
  evidence: sessions migration defines id as primary key; query is primary-key lookup.
  timestamp: 2026-03-04T20:27:52Z

## Evidence

- timestamp: 2026-03-04T20:22:20Z
  checked: config/session.php
  found: SESSION_DRIVER default is "database" and SESSION_CONNECTION defaults to null.
  implication: Session reads/writes use default DB connection unless overridden.

- timestamp: 2026-03-04T20:22:25Z
  checked: config/database.php
  found: Default DB is sqlite; sqlite connection has busy_timeout=null, journal_mode=null, synchronous=null.
  implication: No explicit sqlite lock mitigation configured at connection level.

- timestamp: 2026-03-04T20:22:37Z
  checked: app/Providers/AppServiceProvider.php
  found: sqlite PRAGMA statements are present but commented out, including busy_timeout and synchronous tuning.
  implication: runtime boot does not apply sqlite lock/backoff tuning despite intent.

- timestamp: 2026-03-04T20:22:49Z
  checked: routes/console.php + app/Jobs/MonitorDownloads.php + app/Jobs/AutoEpisodes/DispatchDueMonitors.php
  found: every-minute scheduled jobs dispatch queue work; MonitorDownloads performs multiple model save() writes per run.
  implication: Regular concurrent DB write activity exists while web requests read sessions.

- timestamp: 2026-03-04T20:23:30Z
  checked: config/queue.php + database/migrations/0001_01_01_000002_create_jobs_table.php
  found: QUEUE_CONNECTION default is "database" with jobs/job_batches/failed_jobs tables in main DB.
  implication: queue worker continuously writes to same database file by default.

- timestamp: 2026-03-04T20:23:58Z
  checked: config/cache.php + database/migrations/0001_01_01_000001_create_cache_table.php
  found: CACHE_STORE default is "database" and lock table is cache_locks.
  implication: withoutOverlapping/unique locks use database writes on same sqlite file.

- timestamp: 2026-03-04T20:24:30Z
  checked: runtime config via artisan tinker
  found: database.default=sqlite, session.driver=database, queue.default=database, PRAGMA journal_mode=delete.
  implication: production-like defaults funnel request sessions + workers into one rollback-journal sqlite file (higher lock contention).

- timestamp: 2026-03-04T20:26:40Z
  checked: git history for routes/console.php and monitor jobs against v1 tag
  found: v1 includes commits adding scheduler-driven monitor/retry jobs every minute (d460617, 1238d79, fe11a72, 104a28a).
  implication: timeline matches symptom onset after v1 due to increased concurrent DB activity.

- timestamp: 2026-03-04T20:27:20Z
  checked: sqlite concurrency experiment (DELETE journal mode)
  found: with concurrent writer lock, reader select on sessions table throws SQLSTATE[HY000]: General error: 5 database is locked.
  implication: observed production error is mechanically reproducible in same sqlite mode.

- timestamp: 2026-03-04T20:27:33Z
  checked: sqlite concurrency experiment (WAL journal mode)
  found: reader select succeeds during concurrent writer transaction (read-ok rows=1).
  implication: WAL mode directly mitigates session read lock failures under write concurrency.

- timestamp: 2026-03-04T20:30:18Z
  checked: config/database.php
  found: sqlite connection now sets DB_BUSY_TIMEOUT (default 60000), DB_JOURNAL_MODE (default WAL), DB_SYNCHRONOUS (default NORMAL).
  implication: all Laravel sqlite connections (web, queue, scheduler) apply concurrency-friendly pragmas consistently.

- timestamp: 2026-03-04T20:31:15Z
  checked: runtime PRAGMAs via artisan tinker after fix
  found: busy_timeout=60000, journal_mode=wal, synchronous=1 (NORMAL).
  implication: configured sqlite concurrency settings are active at runtime.

- timestamp: 2026-03-04T20:31:50Z
  checked: php artisan test --filter DownloadRetryPolicyTest
  found: passed (7 tests, 33 assertions).
  implication: monitor/retry flow remains intact after sqlite config change.

- timestamp: 2026-03-04T20:32:05Z
  checked: php artisan test --filter DispatchDueMonitorsTest (parallel run with other commands)
  found: failed with SQLITE_BUSY during app boot, likely from concurrent command contention.
  implication: result is confounded; requires serial rerun for valid verification.

- timestamp: 2026-03-04T20:33:12Z
  checked: php artisan test --filter DispatchDueMonitorsTest (serial rerun)
  found: passed (2 tests, 9 assertions).
  implication: sqlite config change does not break auto-episodes dispatcher path.

## Resolution

root_cause: SQLite runs in rollback journal mode (journal_mode=delete) while sessions, queue, cache locks, and worker writes all share one DB file; v1 increased concurrent writes so StartSession reads intermittently hit SQLITE_BUSY.
fix: Set sqlite connection defaults to concurrency-safe pragmas in config/database.php (DB_JOURNAL_MODE=WAL, DB_BUSY_TIMEOUT=60000, DB_SYNCHRONOUS=NORMAL), so all web/queue/scheduler connections use WAL + timeout behavior.
verification: Confirmed runtime pragmas via artisan tinker (journal_mode=wal, busy_timeout=60000, synchronous=NORMAL); targeted regression tests passed (DispatchDueMonitorsTest, DownloadRetryPolicyTest).
files_changed: [config/database.php]
