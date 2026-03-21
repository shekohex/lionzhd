---
phase: 11-correct-search-mode-ux
plan: 01
subsystem: api
tags: [search, laravel, inertia, pest, browser]
requires:
  - phase: 09-ignored-discovery-filters
    provides: shared user-scoped browse/filter semantics reused by search
provides:
  - canonical /search filter resolution for q, media_type, sort_by, and page
  - raw-query normalization for backend execution without hiding typed magic words
  - dedicated browser scaffold for search mode, layout, refresh, and history flows
affects: [11-02, 11-03, search-ui]
tech-stack:
  added: []
  patterns: [url-backed search filters, raw-query-preserving normalization, filtered-only search responses]
key-files:
  created: [tests/Browser/SearchModeUxTest.php]
  modified: [app/Data/SearchMediaData.php, app/Http/Controllers/SearchController.php, tests/Feature/Controllers/SearchControllerTest.php]
key-decisions:
  - "SearchMediaData now preserves raw q for the UI while exposing normalized execution query and resolved media_type/sort_by helpers."
  - "SearchController returns only the chosen media type in filtered mode and keeps explicit URL params authoritative over typed magic words."
patterns-established:
  - "Canonical search contract: explicit media_type and sort_by params win, q stays visible, backend uses stripped query text."
  - "Phase 11 browser coverage pattern: dedicated /search fixture helpers for URL polling, history navigation, refresh, active mode, and layout reads."
requirements-completed: [SRCH-01, SRCH-02, SRCH-04]
duration: 2 min
completed: 2026-03-21
---

# Phase 11 Plan 01: Canonicalize `/search` params and scaffold search-mode regressions Summary

**Canonical `/search` filter resolution with raw-query normalization, filtered-only controller responses, and a dedicated browser mode/history scaffold.**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-21T20:03:40Z
- **Completed:** 2026-03-21T20:06:26Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Added a dedicated `/search` browser suite scaffold with named mode-tab, filtered-layout, and history-restoration scenarios.
- Locked controller regressions for canonical `media_type`/`sort_by` precedence, filtered-only responses, and raw-query normalization.
- Implemented DTO and controller helpers so backend search executes against stripped query text while UI filters keep the raw visible query.

## task Commits

Each task was committed atomically:

1. **task 0: scaffold dedicated search-mode browser coverage** - `a133a76` (test)
2. **task 1: lock canonical controller expectations for mode, params, and normalization** - `fa8fc87` (test)
3. **task 2: implement the normalized search dto and controller contract** - `b6663bb` (feat)

**Plan metadata:** pending final docs commit

## Files Created/Modified
- `tests/Browser/SearchModeUxTest.php` - browser scaffold with `/search` fixtures plus URL, history, refresh, mode, and layout helpers.
- `tests/Feature/Controllers/SearchControllerTest.php` - controller regressions for canonical params, fallback mode parsing, and stripped-query execution.
- `app/Data/SearchMediaData.php` - normalized query parsing plus resolved media type and sort helpers.
- `app/Http/Controllers/SearchController.php` - canonical `/search` read contract using resolved filters and filtered-only execution.

## Decisions Made
- Kept `filters.q` raw for the UI while returning resolved `media_type` and `sort_by` values from the server contract.
- Moved fallback `type:` and `sort:` parsing into `SearchMediaData` so controller and future UI work read one normalized source.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Swapped unsupported Pest `-x` usage for `--stop-on-failure`**
- **Found during:** task 1 verification
- **Issue:** The installed Pest version rejects the plan's `-x` flag.
- **Fix:** Used `--stop-on-failure` for RED/GREEN controller verification.
- **Files modified:** none
- **Verification:** RED failed and GREEN passed with the supported flag.
- **Committed in:** N/A

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Verification flow only. No code scope change.

## Issues Encountered
- Pest in this repo does not support `-x`; verification used `--stop-on-failure` instead.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Ready for `11-02-PLAN.md`; canonical server-side search state and regression scaffolding are in place for page-level rewiring.
- Browser scenarios are intentionally scaffold-first and may stay red until the Phase 11 UI work lands.

## Self-Check: PASSED

---
*Phase: 11-correct-search-mode-ux*
*Completed: 2026-03-21*
