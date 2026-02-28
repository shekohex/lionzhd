---
phase: 07-auto-episodes-schedules-dedupe
plan: 10
subsystem: api
tags: [laravel, auto-episodes, formrequest, validation, pest]

# Dependency graph
requires:
  - phase: 07-07
    provides: monitoring mutation routes and controller contract for store/update/bulk operations
provides:
  - strict FormRequest validation for series monitor store/update and schedules bulk-apply payloads
  - 422-focused feature regression coverage for invalid schedule payloads and valid mutation flows
affects: [07-08-monitoring-ui, 07-09-settings-management-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - monitoring mutation validation centralized in dedicated FormRequests with conditional daily/weekly schedule requirements
    - auto-episodes validation tests assert 403 gate precedence for External members and 422 field errors for allowed roles

key-files:
  created:
    - app/Http/Requests/AutoEpisodes/StoreSeriesMonitorRequest.php
    - app/Http/Requests/AutoEpisodes/UpdateSeriesMonitorRequest.php
    - app/Http/Requests/AutoEpisodes/BulkUpdateSeriesMonitorsRequest.php
    - tests/Feature/AutoEpisodes/SeriesMonitorValidationTest.php
  modified:
    - tests/Feature/AutoEpisodes/SeriesMonitorValidationTest.php

key-decisions:
  - "Use `present|array|list` for `monitored_seasons` on store so empty arrays remain valid and represent monitor-all semantics"
  - "Keep bulk preset validation in the dedicated bulk-apply controller path while sharing one FormRequest contract"

patterns-established:
  - "Monitoring validation contract: FormRequest rules + conditional schedule-type checks + normalized integer list assertions in feature tests"
  - "Validation/gate split: External members get 403 via middleware, Internal/Admin users get 422 field-level validation errors"

# Metrics
duration: 13 min
completed: 2026-02-28
---

# Phase 7 Plan 10: Monitoring Mutation Validation Hardening Summary

**Monitoring store/update/bulk mutations now enforce strict schedule validation rules with 422 field errors and dedicated feature regression coverage.**

## Performance

- **Duration:** 13 min
- **Started:** 2026-02-28T14:46:29Z
- **Completed:** 2026-02-28T14:59:29Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Added dedicated FormRequests for monitor store, update, and bulk preset mutation payloads.
- Enforced timezone, schedule type, daily/weekly preset time rules, weekly day bounds, monitored seasons list handling, and per-run cap bounds.
- Added comprehensive 422/403/valid-flow feature coverage for store/update/bulk plus partial-update semantics.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add FormRequests for monitoring mutations and initial 422/valid coverage** - `c1346ce` (feat)
2. **Task 2: Expand edge-case validation coverage for weekly bounds/presets/partial updates/gating** - `9c7b1a4` (test)

## Files Created/Modified
- `app/Http/Requests/AutoEpisodes/StoreSeriesMonitorRequest.php` - Validates store payloads with conditional daily/weekly schedule requirements.
- `app/Http/Requests/AutoEpisodes/UpdateSeriesMonitorRequest.php` - Validates partial update payloads against effective schedule state.
- `app/Http/Requests/AutoEpisodes/BulkUpdateSeriesMonitorsRequest.php` - Validates bulk preset updates and series-id list shape.
- `tests/Feature/AutoEpisodes/SeriesMonitorValidationTest.php` - Regression suite covering 422 invalid cases, 403 gate precedence, and valid store/update/bulk paths.

## Decisions Made
- Treated empty `monitored_seasons` as valid monitor-all input by using `present` instead of `required`.
- Reused a shared bulk FormRequest contract for the existing dedicated bulk-apply controller route.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed empty monitored_seasons rejection in store validation**
- **Found during:** Task 1 verification (`php artisan test --filter SeriesMonitorValidationTest`)
- **Issue:** `required` on `monitored_seasons` rejected empty arrays, conflicting with plan requirement that empty means monitor all seasons.
- **Fix:** Switched to `present|array|list` for `monitored_seasons` in `StoreSeriesMonitorRequest`.
- **Files modified:** `app/Http/Requests/AutoEpisodes/StoreSeriesMonitorRequest.php`
- **Verification:** Added/ran `it accepts empty monitored seasons as monitor all seasons on store` in `SeriesMonitorValidationTest`.
- **Committed in:** `c1346ce`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Fix was required to honor schedule semantics and keep UI payload behavior correct; no scope creep.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Monitoring mutation validation contract is locked with endpoint-level feature tests.
- Ready for UI-focused follow-up plans (`07-08` / `07-09`) to rely on stable 422 field error behavior.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
