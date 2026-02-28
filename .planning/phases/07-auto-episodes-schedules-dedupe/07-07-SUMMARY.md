---
phase: 07-auto-episodes-schedules-dedupe
plan: 07
subsystem: api
tags: [laravel, auto-episodes, access-control, inertia, schedules]

# Dependency graph
requires:
  - phase: 07-06
    provides: monitor/run models and scan pipeline used by run-now endpoint dispatch
  - phase: 07-01
    provides: auto-download-schedules gate contract (external blocked from mutations)
provides:
  - monitoring HTTP controllers and routes for enable/update/delete/run-now operations
  - settings schedules page controller with gated bulk preset and global pause/resume mutations
  - feature access-control suite asserting GET-visible and mutation-gated monitoring behavior
affects: [07-08-monitoring-ui, 07-09-settings-management-ui, 07-10-validation-hardening, 07-12-dto-typing]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - route-level can:auto-download-schedules gating for all monitoring mutations while keeping settings GET visible
    - monitor enable/update flow requires watchlist membership plus immediate next_run_at recomputation
    - run-now dispatch flow updates cooldown timestamp atomically with queue dispatch

key-files:
  created:
    - app/Http/Controllers/AutoEpisodes/AutoEpisodesPauseController.php
    - app/Http/Controllers/AutoEpisodes/MonitoringPageController.php
    - app/Http/Controllers/AutoEpisodes/SeriesMonitoringController.php
    - app/Http/Controllers/AutoEpisodes/SeriesMonitoringRunNowController.php
    - tests/Feature/AccessControl/AutoEpisodesAccessTest.php
  modified:
    - routes/web.php
    - routes/settings.php
    - resources/js/types/generated.d.ts

key-decisions:
  - "Keep GET /settings/schedules broadly visible while gate-checking all monitoring mutations at route middleware"
  - "Require watchlist presence before monitor enable/update and persist watchlist_id on monitors for cascade-aligned disable+remove"
  - "Enforce manual run-now cooldown via run_now_available_at updated at dispatch time"

patterns-established:
  - "Monitoring mutation contract: route middleware gate + inline validation + redirect response"
  - "Access tests assert both behavioral authorization (403) and route middleware wiring"

# Metrics
duration: 7 min
completed: 2026-02-28
---

# Phase 7 Plan 07: Monitoring HTTP Access Contract Summary

**Monitoring endpoints are now wired for series-level enable/update/delete/run-now and settings-level bulk/pause mutations with external-visible GET access but mutation gating.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-02-28T14:36:38Z
- **Completed:** 2026-02-28T14:44:07Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- Added Auto Episodes controllers and route wiring for series monitoring lifecycle and run-now dispatch.
- Replaced schedules settings closure with controller-rendered Inertia props and added gated settings mutations.
- Added feature tests locking the contract: schedules page remains visible to authenticated users while all monitoring mutations require `can:auto-download-schedules`.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add monitor management controllers + routes (series endpoints + settings page)** - `cfc0374` (feat)
2. **Task 2: Add access-control feature tests for GET-visible + mutation-gated monitoring** - `a197934` (test)
3. **Verification artifact: TypeScript transform output refresh** - `9ffc47a` (chore)

## Files Created/Modified
- `app/Http/Controllers/AutoEpisodes/SeriesMonitoringController.php` - Implements enable/update/delete and bulk preset mutation handling.
- `app/Http/Controllers/AutoEpisodes/SeriesMonitoringRunNowController.php` - Dispatches manual monitor scans with cooldown enforcement.
- `app/Http/Controllers/AutoEpisodes/AutoEpisodesPauseController.php` - Toggles user-level auto-episodes pause state.
- `app/Http/Controllers/AutoEpisodes/MonitoringPageController.php` - Supplies schedules page monitoring props for authenticated users.
- `routes/web.php` - Adds gated per-series monitoring mutation routes.
- `routes/settings.php` - Wires schedules page controller and gated schedules mutations.
- `tests/Feature/AccessControl/AutoEpisodesAccessTest.php` - Verifies GET-visible and mutation-gated access contract.
- `resources/js/types/generated.d.ts` - Refreshed generated TypeScript declarations after transform.

## Decisions Made
- Kept `GET /settings/schedules` outside `can:auto-download-schedules` and applied gate middleware only to mutation routes.
- Required watchlist existence for monitor enable/update and persisted `watchlist_id` for disable+remove flow.
- Used `run_now_available_at` cooldown timestamp updates at run-now dispatch time to enforce manual-run rate limiting.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed missing current-user attributes on schedules page render**
- **Found during:** Task 2 verification (`php artisan test --filter AutoEpisodesAccessTest`)
- **Issue:** `MonitoringPageController` accessed `auto_episodes_paused_at` on an auth user instance that did not have the column loaded, causing a 500.
- **Fix:** Refreshed the current user before reading pause fields.
- **Files modified:** `app/Http/Controllers/AutoEpisodes/MonitoringPageController.php`
- **Verification:** `php artisan test --filter AutoEpisodesAccessTest` passes for external/internal/admin schedules GET requests.
- **Committed in:** `a197934`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Fix was required for correctness of the schedules GET-visible contract; no scope creep.

## Issues Encountered
- Initial test run failed due global helper function name collision; resolved by prefixing helper names in `AutoEpisodesAccessTest`.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Monitoring HTTP contract and access boundaries are in place for upcoming UI and validation plans.
- Ready for `07-08-PLAN.md`.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
