---
phase: 07-auto-episodes-schedules-dedupe
plan: 05
subsystem: infra
tags: [laravel, scheduler, queue, cache-lock, auto-episodes, pest]

# Dependency graph
requires:
  - phase: 07-auto-episodes-schedules-dedupe
    provides: schedule math defaults and monitor persistence from 07-03 and 07-04
provides:
  - minute-level auto-episodes dispatcher scheduling in Laravel console routes
  - due monitor dispatch filtering by enabled state, next run timestamp, and user pause state
  - per-monitor scan job serialization via cache lock key
  - dispatcher feature tests for due dispatch and paused-user skip behavior
affects: [07-06-scan-dedupe, 07-07-monitoring-endpoints, 07-08-monitoring-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - queued dispatcher job implements ShouldBeUnique and bounded due-monitor dispatch
    - per-monitor scan execution serialized with DB-backed cache locks

key-files:
  created:
    - app/Jobs/AutoEpisodes/DispatchDueMonitors.php
    - app/Jobs/AutoEpisodes/RunMonitorScan.php
    - app/Actions/AutoEpisodes/ScanSeriesForNewEpisodes.php
    - tests/Feature/Jobs/AutoEpisodes/DispatchDueMonitorsTest.php
  modified:
    - routes/console.php

key-decisions:
  - "Join users in dispatcher query and skip monitors when auto_episodes_paused_at is set"
  - "Use monitor-scoped cache lock key auto:episodes:monitor:{id} before scan action entrypoint"

patterns-established:
  - "Schedule::job(DispatchDueMonitors::class)->everyMinute()->withoutOverlapping()->sentryMonitor()"
  - "DispatchDueMonitors selects due monitors in bounded batches and emits RunMonitorScan jobs"

# Metrics
duration: 4 min
completed: 2026-02-28
---

# Phase 7 Plan 05: Dispatcher Scheduler Pipeline Summary

**Minute-scheduled unique dispatcher with per-monitor cache-locked scan jobs and feature coverage for due-monitor enqueue semantics.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-28T14:14:01Z
- **Completed:** 2026-02-28T14:18:08Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments
- Added `DispatchDueMonitors` queued unique job that selects due enabled monitors, excludes paused users, and dispatches bounded per-monitor scans.
- Added `RunMonitorScan` job with monitor-scoped `Cache::lock` and wired `ScanSeriesForNewEpisodes::run(monitorId: ...)` entrypoint.
- Wired dispatcher into `routes/console.php` as an every-minute scheduled job and added feature tests validating due dispatch and paused-user skip behavior.

## Task Commits

Each task was committed atomically:

1. **Task 1: Create dispatcher + scan jobs (unique + locking, no overlap)** - `fe11a72` (feat)
2. **Task 2: Wire dispatcher into Laravel scheduler (every minute)** - `1238d79` (feat)
3. **Task 3: Add a feature test ensuring due monitors are dispatched** - `71b066b` (test)

**Plan metadata:** (captured in docs commit for this plan)

## Files Created/Modified
- `app/Jobs/AutoEpisodes/DispatchDueMonitors.php` - Queries due monitors with pause-state filtering and enqueues bounded scan jobs.
- `app/Jobs/AutoEpisodes/RunMonitorScan.php` - Acquires per-monitor cache lock and invokes scan action entrypoint safely.
- `app/Actions/AutoEpisodes/ScanSeriesForNewEpisodes.php` - Introduces executable scan action contract for upcoming scan/dedupe implementation.
- `routes/console.php` - Registers minute-level `auto-episodes-dispatch` scheduler entry.
- `tests/Feature/Jobs/AutoEpisodes/DispatchDueMonitorsTest.php` - Verifies due dispatch behavior and paused-user skip semantics.

## Decisions Made
- Joined `users` in the dispatcher query and filtered on `users.auto_episodes_paused_at IS NULL` so paused users never receive scheduled scans.
- Used per-monitor lock key `auto:episodes:monitor:{monitorId}` in `RunMonitorScan` to serialize monitor scans across workers.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Ready for `07-06-PLAN.md`.
Dispatcher pipeline and lock entrypoint are in place for scan/dedupe implementation.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
