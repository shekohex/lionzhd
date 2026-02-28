---
phase: 07-auto-episodes-schedules-dedupe
plan: 01
subsystem: access-control
tags: [laravel, routes, inertia, access-control, pest]
requires:
  - phase: 01-access-control
    provides: Stable gate contract including auto-download-schedules restrictions by member subtype
  - phase: 07-auto-episodes-schedules-dedupe
    provides: Phase decision that external members must see monitoring UI as disabled
provides:
  - GET /settings/schedules is reachable for any authenticated role/subtype
  - Access test contract asserts external/internal/admin all receive schedules Inertia page
affects: [07-02, 07-07, 07-08, 07-09]
tech-stack:
  added: []
  patterns:
    - Keep page visibility route-level open for authenticated users while deferring mutation gating to later plans
key-files:
  created:
    - .planning/phases/07-auto-episodes-schedules-dedupe/07-01-SUMMARY.md
  modified:
    - routes/settings.php
    - tests/Feature/AccessControl/SchedulesAccessTest.php
key-decisions:
  - Expose schedules page GET route to all authenticated users and keep control disabling/mutation authorization for subsequent plans.
  - Assert schedules visibility contract through Inertia component checks for all role/subtype permutations.
patterns-established:
  - "Visible-but-disabled UX starts by widening read access while preserving stricter action gates."
duration: 1 min
completed: 2026-02-28
---

# Phase 7 Plan 01: Allow GET schedules page for all (visible-but-disabled UX) Summary

**Schedules management page is now readable by every authenticated user while preserving the future action-gating boundary for monitoring mutations.**

## Performance

- **Duration:** 1 min
- **Started:** 2026-02-28T13:58:52Z
- **Completed:** 2026-02-28T14:00:04Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Moved `GET /settings/schedules` outside the `can:auto-download-schedules` middleware group so External members can open the page.
- Preserved the existing route name (`schedules`) and Inertia component (`settings/schedules`).
- Updated access-control tests so external/internal/admin users all assert `200 OK` plus the schedules Inertia component.

## Task Commits

Each task was committed atomically:

1. **Task 1: Update schedules settings route to be visible to all authenticated users** - `6af8971` (feat)
2. **Task 2: Update access-control test to match visible-but-disabled decision** - `7bf804b` (test)

## Files Created/Modified
- `routes/settings.php` - exposes schedules page GET route to all authenticated users.
- `tests/Feature/AccessControl/SchedulesAccessTest.php` - updates external-member assertion to `200 OK` and Inertia component checks.

## Decisions Made
- Kept read access (`GET /settings/schedules`) open to all authenticated users so External members can see monitoring UI.
- Deferred mutation route gating to subsequent Phase 7 plans, preserving scope for this plan.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Ready for `07-02-PLAN.md` (monitoring + known-episodes persistence).
- No blockers carried forward.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
