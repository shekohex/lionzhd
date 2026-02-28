---
phase: 07-auto-episodes-schedules-dedupe
plan: 02
subsystem: database
tags: [laravel, migrations, eloquent, auto-episodes]

# Dependency graph
requires:
  - phase: 05-download-lifecycle-reliability
    provides: media_download_refs persistence used by monitor episode linkage
  - phase: 07-auto-episodes-schedules-dedupe
    provides: authenticated schedules page visibility baseline from 07-01
provides:
  - users-level auto episodes pause and last-seen timestamps
  - series monitor configuration persistence with per-user per-series uniqueness
  - known monitor episode state persistence with monitor+episode dedupe key
affects: [07-03-schedule-math, 07-05-dispatcher-jobs, 07-06-scan-dedupe, 07-07-monitoring-endpoints, 07-08-series-monitoring-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - db-level uniqueness for user+series monitors and monitor+episode states
    - immutable datetime casting for user auto-episodes lifecycle timestamps

key-files:
  created:
    - database/migrations/2026_02_27_000100_add_auto_episodes_fields_to_users_table.php
    - database/migrations/2026_02_27_000110_create_series_monitors_table.php
    - database/migrations/2026_02_27_000120_create_series_monitor_episodes_table.php
    - app/Models/AutoEpisodes/SeriesMonitor.php
    - app/Models/AutoEpisodes/SeriesMonitorEpisode.php
  modified:
    - app/Models/User.php

key-decisions:
  - "Use unique(user_id, series_id) on series_monitors to enforce one monitor per user-series"
  - "Model known episodes as series_monitor_episodes with unique(monitor_id, episode_id) and per-episode state"

patterns-established:
  - "Monitor persistence pattern: schedule config + next_run_at and run bookkeeping on series_monitors"
  - "Known-episode set pattern: episode identity dedupe plus queue/download state on series_monitor_episodes"

# Metrics
duration: 4 min
completed: 2026-02-28
---

# Phase 7 Plan 02: Monitoring Persistence Summary

**Relational monitor and known-episode persistence with dedupe constraints plus user-level auto-episodes pause/last-seen tracking.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-28T13:58:52Z
- **Completed:** 2026-02-28T14:03:03Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Added migration-backed persistence for user auto-episodes pause and last-seen timestamps.
- Added `series_monitors` table with schedule config, run bookkeeping, and uniqueness/index constraints for due-run querying.
- Added `series_monitor_episodes` table and AutoEpisodes models for persisted known-episode identity/state tracking and download-ref linkage.

## Task Commits

Each task was committed atomically:

1. **Task 1: Create migrations for user auto-episodes fields, series monitors, and known episodes** - `9550671` (feat)
2. **Task 2: Add AutoEpisodes Eloquent models and User casts** - `a99c1e1` (feat)

**Plan metadata:** (captured in docs commit for this plan)

## Files Created/Modified
- `database/migrations/2026_02_27_000100_add_auto_episodes_fields_to_users_table.php` - Adds user-level auto-episodes pause and last-seen timestamps.
- `database/migrations/2026_02_27_000110_create_series_monitors_table.php` - Persists per-user per-series monitor configs, scheduling, and run bookkeeping.
- `database/migrations/2026_02_27_000120_create_series_monitor_episodes_table.php` - Persists known episodes with dedupe key and queue/download state metadata.
- `app/Models/AutoEpisodes/SeriesMonitor.php` - Eloquent model for monitor config with `user`, `series`, and `episodes` relationships.
- `app/Models/AutoEpisodes/SeriesMonitorEpisode.php` - Eloquent model for known episodes with `monitor` and `downloadRef` relationships.
- `app/Models/User.php` - Adds immutable datetime casts for auto-episodes paused/last-seen fields.

## Decisions Made
- Enforced monitor uniqueness at DB level via `unique(user_id, series_id)` so users cannot create duplicate monitors per series.
- Modeled known episode persistence as `series_monitor_episodes` keyed by `unique(monitor_id, episode_id)` to support deterministic dedupe and state transitions.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Ready for `07-03-PLAN.md` (schedule math implementation + tests).
- Persistence foundation for monitor configuration and known-episode dedupe is complete.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
