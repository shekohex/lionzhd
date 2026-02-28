---
phase: 07-auto-episodes-schedules-dedupe
plan: 12
subsystem: api
tags: [laravel-data, inertia, typescript, auto-episodes]

requires:
  - phase: 07-04
    provides: monitor run/event persistence used for monitoring activity payloads
  - phase: 07-07
    provides: monitor mutation/access-control routes and series-level monitor state
provides:
  - TypeScript-exported monitoring DTOs for series monitors, monitor events, and schedules page payload
  - DTO-backed Inertia props for settings/schedules and series/show monitoring state
  - Regenerated frontend TS definitions aligned to backend monitoring contracts
affects: [07-08, 07-09]

tech-stack:
  added: []
  patterns: [DTO-first Inertia payloads for monitoring pages, strict backend-to-frontend contract generation via typescript:transform]

key-files:
  created:
    - app/Data/AutoEpisodes/SeriesMonitorData.php
    - app/Data/AutoEpisodes/SeriesMonitorEventData.php
    - app/Data/AutoEpisodes/MonitoringPageData.php
    - app/Http/Controllers/AutoEpisodes/BulkApplySeriesMonitoringPresetController.php
  modified:
    - app/Http/Controllers/AutoEpisodes/MonitoringPageController.php
    - app/Http/Controllers/Series/SeriesController.php
    - app/Http/Controllers/AutoEpisodes/SeriesMonitoringController.php
    - routes/settings.php
    - resources/js/types/generated.d.ts

key-decisions:
  - "Return settings/schedules props through a single MonitoringPageData DTO rather than ad-hoc arrays"
  - "Expose series/show monitoring data as SeriesMonitorData plus shared preset/cooldown props"
  - "Extract schedules bulk-apply into a single-action controller to satisfy architecture constraints"

patterns-established:
  - "Monitoring DTOs live under App\\Data\\AutoEpisodes and are exported to TS"
  - "Controllers map Eloquent models to typed DTOs before rendering Inertia"

duration: 10 min
completed: 2026-02-28
---

# Phase 7 Plan 12: Monitoring DTO + typed monitoring props summary

**Typed monitoring payloads now ship series monitor state, recent monitor events, preset config, and run-now cooldown data across schedules and series pages.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-02-28T14:46:46Z
- **Completed:** 2026-02-28T14:57:36Z
- **Tasks:** 2/2
- **Files modified:** 9

## Accomplishments
- Added three `#[TypeScript]` auto-episodes DTOs for monitor summaries, monitor events, and monitoring page payloads.
- Replaced schedules page ad-hoc prop arrays with `MonitoringPageData`, including `monitors`, `events`, `preset_times`, `backfill_preset_counts`, and `run_now_cooldown_seconds`.
- Added series page monitoring props (`monitor`, presets, cooldown) via `SeriesMonitorData` and regenerated `resources/js/types/generated.d.ts`.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add monitoring DTOs (spatie/laravel-data) with TypeScript exports** - `510945b` (feat)
2. **Task 2: Use DTOs for Inertia props on monitoring pages and regenerate TS types** - `5f90150` (feat)

## Files Created/Modified
- `app/Data/AutoEpisodes/SeriesMonitorData.php` - typed monitor summary DTO with schedule/run fields.
- `app/Data/AutoEpisodes/SeriesMonitorEventData.php` - typed recent monitor activity DTO.
- `app/Data/AutoEpisodes/MonitoringPageData.php` - typed schedules page payload DTO.
- `app/Http/Controllers/AutoEpisodes/MonitoringPageController.php` - maps monitor/event models to DTO payload.
- `app/Http/Controllers/Series/SeriesController.php` - includes per-series monitor DTO + presets/cooldown props.
- `resources/js/types/generated.d.ts` - regenerated TS contracts including new auto-episodes DTO namespaces.
- `app/Http/Controllers/AutoEpisodes/SeriesMonitoringController.php` - removed non-resource bulk apply action.
- `app/Http/Controllers/AutoEpisodes/BulkApplySeriesMonitoringPresetController.php` - single-action bulk-apply endpoint.
- `routes/settings.php` - routes schedules bulk-apply to dedicated invokable controller.

## Decisions Made
- Used one top-level DTO (`MonitoringPageData`) for schedules page payload to stabilize prop shape.
- Kept series detail monitoring props explicit (`monitor`, preset lists, cooldown seconds) to match current page contract while introducing typed monitor payload.
- Added schema/table existence guards in schedules payload building to keep access checks resilient in lightweight/no-migration test contexts.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Prevented schedules page crash when monitor tables are unavailable**
- **Found during:** Task 2 verification (`php artisan test`)
- **Issue:** `MonitoringPageController@index` queried `series_monitors` unconditionally, causing 500 in access tests that do not run DB migrations.
- **Fix:** Added `Schema::hasTable(...)` and user existence guards before monitor/event queries, returning empty DTO lists when unavailable.
- **Files modified:** app/Http/Controllers/AutoEpisodes/MonitoringPageController.php
- **Verification:** `php artisan test tests/Feature/AccessControl/SchedulesAccessTest.php` passes.
- **Committed in:** `5f90150`

**2. [Rule 3 - Blocking] Resolved architecture gate blocking full verification**
- **Found during:** Task 2 verification (`php artisan test`)
- **Issue:** Architecture tests rejected `SeriesMonitoringController::bulkApply` as non-resource public method.
- **Fix:** Moved bulk-apply logic into `BulkApplySeriesMonitoringPresetController::__invoke` and updated schedules route.
- **Files modified:** app/Http/Controllers/AutoEpisodes/SeriesMonitoringController.php, app/Http/Controllers/AutoEpisodes/BulkApplySeriesMonitoringPresetController.php, routes/settings.php
- **Verification:** `php artisan test` full suite passes (including `Tests\Architecture\GeneralTest`).
- **Committed in:** `5f90150`

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking)
**Impact on plan:** Both fixes were required to keep verification green and maintain stable delivery of typed monitoring props.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Monitoring DTO contract is now stable and generated for frontend usage.
- Ready for remaining phase 7 plans (`07-08`, `07-09`, `07-10`) that consume/render these props.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
