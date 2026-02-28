---
phase: 07-auto-episodes-schedules-dedupe
plan: 11
subsystem: api
tags: [laravel, auto-episodes, backfill, access-control, queue]

# Dependency graph
requires:
  - phase: 07-06
    provides: scan trigger/options support for backfill_count handling in RunMonitorScan
  - phase: 07-07
    provides: monitoring route/controller access contract and auto-download-schedules mutation gating
provides:
  - explicit POST backfill endpoint for monitored series
  - request validation for backfill_count preset selection
  - access-control coverage for explicit-only backfill dispatch semantics
affects: [07-08-monitoring-ui, 07-09-settings-management-ui, 07-10-validation-hardening, 07-12-dto-typing]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - explicit-only backfill dispatch through dedicated monitoring mutation endpoint
    - config-driven backfill count validation via preset list

key-files:
  created:
    - app/Http/Controllers/AutoEpisodes/SeriesMonitoringBackfillController.php
    - app/Http/Requests/AutoEpisodes/BackfillSeriesMonitorRequest.php
  modified:
    - routes/web.php
    - tests/Feature/AccessControl/AutoEpisodesAccessTest.php

key-decisions:
  - "Backfill is triggered only by POST /series/{model}/monitoring/backfill and never implicitly during enable/update"
  - "Backfill count accepts only config('auto_episodes.backfill_preset_counts') values"

patterns-established:
  - "Monitoring mutation expansion pattern: dedicated controller + dedicated FormRequest + gate-protected route"
  - "Access tests assert both route-level authorization and queue dispatch behavior"

# Metrics
duration: 3 min
completed: 2026-02-28
---

# Phase 7 Plan 11: Explicit Monitoring Backfill Endpoint Summary

**Monitoring backfill is now an explicit, gated action that queues a backfill-triggered scan with validated preset counts and never auto-runs during enable/update.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-28T14:46:30Z
- **Completed:** 2026-02-28T14:49:41Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Added `SeriesMonitoringBackfillController` to enforce watchlist+enabled-monitor preconditions and dispatch backfill scans.
- Added `BackfillSeriesMonitorRequest` to validate `backfill_count` against configured preset counts.
- Extended `AutoEpisodesAccessTest` to enforce external denial, internal/admin explicit backfill dispatch, and no implicit backfill dispatch on enable/update.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add explicit backfill endpoint (recent-only) that dispatches a backfill-triggered scan** - `9343b5c` (feat)
2. **Task 2: Extend access-control tests to cover backfill endpoint** - `fa6f96c` (test)

## Files Created/Modified
- `app/Http/Controllers/AutoEpisodes/SeriesMonitoringBackfillController.php` - Handles explicit backfill requests and dispatches `RunMonitorScan` with backfill trigger/options.
- `app/Http/Requests/AutoEpisodes/BackfillSeriesMonitorRequest.php` - Validates `backfill_count` as required integer constrained to configured presets.
- `routes/web.php` - Registers `series.monitoring.backfill` route under `can:auto-download-schedules`.
- `tests/Feature/AccessControl/AutoEpisodesAccessTest.php` - Verifies access restrictions and explicit-only dispatch contract for backfill.

## Decisions Made
- Kept backfill as an explicit-only mutation endpoint to preserve baseline no-auto-backfill behavior on monitor enable/update.
- Reused config-driven preset counts to keep server validation aligned with UI backfill choices.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Explicit backfill contract is in place for monitoring UX integration.
- Ready for remaining Phase 7 plans.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
