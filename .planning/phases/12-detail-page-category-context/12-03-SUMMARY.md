---
phase: 12-detail-page-category-context
plan: 03
subsystem: api
tags: [laravel, inertia, categories, series, typescript, pest]
requires:
  - phase: 12-detail-page-category-context
    provides: shared detail-category resolver and DTO contract from plan 12-05
provides:
  - series show category_context props sourced from the shared resolver
  - typed series detail page contract for category chips
  - feature regressions for series detail ordering, hrefs, and uncategorized normalization
affects: [phase 12-04, series detail hero chips, CTXT-02]
tech-stack:
  added: []
  patterns:
    - series detail props stay server-owned and append local category_context beside existing watchlist and monitoring data
    - series detail page types reuse generated DTO contracts instead of duplicating chip interfaces
key-files:
  created:
    - tests/Feature/Controllers/SeriesDetailCategoryContextTest.php
  modified:
    - app/Http/Controllers/Series/SeriesController.php
    - resources/js/types/series.ts
key-decisions:
  - "SeriesController@show consumes ResolveDetailPageCategories::forSeries directly and preserves existing monitor/watchlist payloads."
  - "SeriesInformationPageProps uses App.Data.DetailPageCategoryChipData[] instead of a hand-written duplicate chip type."
patterns-established:
  - "Detail controllers should append local category_context props without mutating Xtream DTO payloads."
  - "Series detail controller regressions should use mocked Xtream responses plus Inertia JSON assertions for prop contracts."
requirements-completed: [CTXT-02]
duration: 3 min
completed: 2026-03-23
---

# Phase 12 Plan 03: Expose series detail category_context through the show controller Summary

**Series detail pages now receive shared category chips from the server with typed browse-target payloads.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-23T00:04:17Z
- **Completed:** 2026-03-23T00:07:54Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Added focused feature coverage for the series detail `category_context` contract, including browse hrefs and uncategorized normalization.
- Wired `SeriesController@show` to expose `category_context` from `ResolveDetailPageCategories::forSeries` without changing existing monitoring or watchlist props.
- Extended `SeriesInformationPageProps` to use the generated detail chip DTO contract.

## task Commits

Each task was committed atomically:

1. **task 1: add series detail controller regressions for category context** - `0d8adaa` (test)
2. **task 2: wire series show responses and types to the shared category resolver** - `c2b1763` (feat)

**Plan metadata:** final docs commit records summary/state updates.

## Files Created/Modified
- `tests/Feature/Controllers/SeriesDetailCategoryContextTest.php` - Series show regression coverage for ordering, hrefs, neutral hidden/ignored treatment, and uncategorized normalization.
- `app/Http/Controllers/Series/SeriesController.php` - Appends shared `category_context` data to the series detail Inertia payload.
- `resources/js/types/series.ts` - Adds the typed series detail `category_context` prop via the generated DTO contract.

## Decisions Made
- Series detail category context stays server-owned and reads from the shared resolver instead of the Xtream detail DTO.
- The series page contract references `App.Data.DetailPageCategoryChipData[]` so the frontend stays aligned with PHP-owned DTO generation.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Corrected the regression expectation for shared resolver tie ordering**
- **Found during:** task 2 (wire series show responses and types to the shared category resolver)
- **Issue:** The new controller regression expected provider-id ordering for equal sync-order categories, but the shared resolver intentionally breaks that tie with assignment source order.
- **Fix:** Updated the expected payload to match the shared resolver's canonical `series-drama` then `series-action` ordering.
- **Files modified:** tests/Feature/Controllers/SeriesDetailCategoryContextTest.php
- **Verification:** `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Controllers/SeriesDetailCategoryContextTest.php --stop-on-failure`
- **Committed in:** `c2b1763`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Kept the new controller coverage aligned with the shared resolver contract. No scope creep.

## Issues Encountered
- Initial verification failed because the new regression assumed the wrong tie-breaker for equal sync-order categories; correcting the expectation resolved the mismatch.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `series/show` now exposes the full category chip payload needed by the shared hero rendering plan.
- Phase 12-04 can consume the typed `category_context` prop without revisiting series controller wiring.

## Self-Check

PASSED

---
*Phase: 12-detail-page-category-context*
*Completed: 2026-03-23*
