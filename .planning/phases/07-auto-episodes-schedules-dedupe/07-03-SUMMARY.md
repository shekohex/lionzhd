---
phase: 07-auto-episodes-schedules-dedupe
plan: 03
subsystem: api
tags: [laravel, carbon, timezone, scheduling, pest]

# Dependency graph
requires:
  - phase: 07-auto-episodes-schedules-dedupe
    provides: series monitor persistence and schedule fields from 07-02
provides:
  - auto episodes schedule defaults for presets, run caps, cooldown, and retention
  - timezone-aware next-run computation for hourly, daily, and weekly monitor schedules
  - unit coverage for schedule boundary behavior and multi-day weekly selection
affects: [07-04-activity-log, 07-05-dispatcher-jobs, 07-06-scan-dedupe, 07-07-monitoring-endpoints, 07-08-series-monitoring-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - compute next run from current user-local time and persist UTC target
    - validate daily and weekly schedule times against config preset values

key-files:
  created:
    - config/auto_episodes.php
    - app/Enums/AutoEpisodes/MonitorScheduleType.php
    - app/Actions/AutoEpisodes/ComputeNextRunAt.php
    - tests/Unit/Actions/AutoEpisodes/ComputeNextRunAtTest.php
  modified: []

key-decisions:
  - "Daily/weekly schedule times must come from config preset_times with first preset as deterministic fallback"
  - "Weekly scheduling picks the nearest future candidate across normalized unique weekday inputs"

patterns-established:
  - "Schedule math contract: nowUtc + user timezone + schedule config => next_run_at UTC"
  - "Preset-guarded schedule times: reject non-preset HH:MM values to keep UI and backend aligned"

# Metrics
duration: 4 min
completed: 2026-02-28
---

# Phase 7 Plan 03: Schedule Math Summary

**Timezone-aware hourly/daily/weekly next-run computation with preset-validated schedule inputs and deterministic UTC outputs.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-28T14:06:04Z
- **Completed:** 2026-02-28T14:10:31Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Added `config/auto_episodes.php` defaults for schedule preset times, backfill preset counts, run cap, run-now cooldown, and activity retention.
- Added `MonitorScheduleType` enum and `ComputeNextRunAt` action to compute UTC next-run timestamps from user-local hourly/daily/weekly schedules.
- Added focused unit tests covering hourly boundary behavior, daily before/after-time transitions, and weekly multi-day candidate selection.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add auto-episodes config defaults (preset times, caps, cooldown, retention)** - `653f549` (feat)
2. **Task 2: Implement ComputeNextRunAt action + schedule type enum** - `f3498e7` (feat)
3. **Task 3: Add unit tests for schedule math edge cases** - `a6895f1` (test)

**Plan metadata:** (captured in docs commit for this plan)

## Files Created/Modified
- `config/auto_episodes.php` - Defines deterministic default preset times/counts and automation guardrail settings.
- `app/Enums/AutoEpisodes/MonitorScheduleType.php` - Introduces strongly typed schedule modes (`hourly`, `daily`, `weekly`).
- `app/Actions/AutoEpisodes/ComputeNextRunAt.php` - Computes next run in user timezone and returns immutable UTC timestamp.
- `tests/Unit/Actions/AutoEpisodes/ComputeNextRunAtTest.php` - Verifies hourly, daily, and weekly schedule math behavior.

## Decisions Made
- Enforced that daily/weekly schedule times must be members of `config('auto_episodes.preset_times')` to keep backend schedule semantics aligned with preset-based UX.
- Normalized weekly days to unique sorted `0..6` integers and selected the nearest future candidate to make multi-day scheduling deterministic.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Ready for `07-04-PLAN.md` (monitor run/event activity persistence).
- Schedule defaults and UTC next-run math are in place for dispatcher and scan-job wiring.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
