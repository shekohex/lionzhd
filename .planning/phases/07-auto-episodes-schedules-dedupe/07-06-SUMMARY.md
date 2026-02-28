---
phase: 07-auto-episodes-schedules-dedupe
plan: 06
subsystem: api
tags: [laravel, auto-episodes, dedupe, queues, xtream, aria2]

# Dependency graph
requires:
  - phase: 07-05
    provides: monitor dispatch scaffolding and monitor lock job execution
provides:
  - race-safe single-episode queue action with safe episode-id normalization
  - monitor scan pipeline with baseline skip, per-run cap/defer, and run/event persistence
  - feature regression coverage for baseline/dedupe/unsafe-id/double-queue scenarios
affects: [phase-07-next-plans, monitoring-activity-ui, manual-backfill-controls]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - per-episode lock + tuple dedupe before queueing media_download_refs
    - run/event journaling for scan outcomes (queued/duplicate/deferred/error)
    - baseline-first scan behavior to prevent implicit historical backfill

key-files:
  created:
    - app/Actions/AutoEpisodes/QueueEpisodeDownload.php
    - tests/Feature/AutoEpisodes/MonitorScanDedupeTest.php
  modified:
    - app/Actions/AutoEpisodes/ScanSeriesForNewEpisodes.php
    - app/Jobs/AutoEpisodes/RunMonitorScan.php
    - app/Jobs/AutoEpisodes/DispatchDueMonitors.php
    - app/Models/AutoEpisodes/SeriesMonitor.php
    - app/Models/AutoEpisodes/SeriesMonitorEpisode.php

key-decisions:
  - "Normalize Xtream episode IDs to unsigned 32-bit integers before lock/DB operations"
  - "Use first non-backfill run as baseline and mark discovered episodes skipped"
  - "Treat deferred-cap runs as success_with_warnings while persisting per-episode deferred events"

patterns-established:
  - "Queue dedupe pattern: lock(user+series+episode) -> tuple existence check -> queue"
  - "Scan bookkeeping pattern: create running run row, persist events, always update monitor attempt + next_run_at"

# Metrics
duration: 10 min
completed: 2026-02-28
---

# Phase 7 Plan 06: Monitor Scan Dedupe Pipeline Summary

**Episode monitoring now scans Xtream series data, persists run/event outcomes, and queues only non-duplicate safe episode IDs with per-run cap deferrals.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-02-28T14:20:15Z
- **Completed:** 2026-02-28T14:31:14Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments
- Added `QueueEpisodeDownload` with strict episode-id normalization, per-episode locking, and tuple-based duplicate checks.
- Implemented `ScanSeriesForNewEpisodes` end-to-end: baseline skip behavior, candidate diffing, cap/defer flow, queue outcomes, run/event persistence, and monitor next-run updates.
- Added feature tests for baseline, cap+defer, no-repeat scans, existing-ref duplicates, double-queue safety, and unsafe episode-id errors.

## Task Commits

Each task was committed atomically:

1. **Task 1: Implement race-safe queueing for a single episode (dedupe lock + DB checks)** - `46ce52c` (feat)
2. **Task 2: Implement scan+diff+cap pipeline with run/event logging and next_run_at updates** - `104a28a` (feat)
3. **Task 3: Add feature tests for diffing, dedupe, and cap/deferral** - `304bc84` (test)

## Files Created/Modified
- `app/Actions/AutoEpisodes/QueueEpisodeDownload.php` - Queue action with safe normalization, lock, duplicate detection, and queue result statuses.
- `app/Actions/AutoEpisodes/ScanSeriesForNewEpisodes.php` - Main scan pipeline with baseline behavior, run/event tracking, cap/defer handling, and monitor timestamp updates.
- `app/Jobs/AutoEpisodes/RunMonitorScan.php` - Trigger/options-aware scan dispatch under monitor lock.
- `app/Jobs/AutoEpisodes/DispatchDueMonitors.php` - Explicit scheduled-trigger dispatch to monitor scan job.
- `app/Models/AutoEpisodes/SeriesMonitor.php` - Added enum casts for schedule type and last attempt status.
- `app/Models/AutoEpisodes/SeriesMonitorEpisode.php` - Added canonical state constants used by scan transitions.
- `tests/Feature/AutoEpisodes/MonitorScanDedupeTest.php` - Regression suite covering AUTO-05/AUTO-06 dedupe and baseline behavior.

## Decisions Made
- Used lock key `auto:episodes:queue:user:{userId}:series:{seriesId}:episode:{downloadableId}` to serialize duplicate-sensitive queue paths.
- Implemented baseline as a success run that marks discovered episodes `skipped` unless trigger is `backfill`.
- Forwarded trigger/options through `RunMonitorScan` so scheduled/manual/backfill semantics are available at scan action level.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- AUTO-05/AUTO-06 backend pipeline and regression coverage are in place.
- Ready for `07-07-PLAN.md`.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
