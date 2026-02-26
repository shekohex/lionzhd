---
phase: 04-category-browse-filter-ux
plan: 01
subsystem: api
tags: [laravel, inertia, categories, dto, aggregation]

# Dependency graph
requires:
  - phase: 03-categories-sync-categorization-correctness
    provides: Persisted categories with stable provider IDs and system uncategorized IDs
provides:
  - Shared TypeScript DTOs for category sidebar items and selected category filters
  - Shared Movies/Series sidebar builder action with aggregate category counts
  - Locked backend ordering and disabled-state assertions for category sidebars
affects: [04-02, 04-03, 04-04, 04-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Shared action with media-type context resolution for category sidebar payloads
    - Aggregate count queries with dedicated uncategorized normalization

key-files:
  created:
    - app/Data/CategorySidebarItemData.php
    - app/Data/CategoryBrowseFiltersData.php
    - app/Actions/BuildCategorySidebarItems.php
  modified:
    - tests/Feature/Settings/SyncCategoriesControllerTest.php

key-decisions:
  - "Use one action for Movies/Series sidebar payloads with MediaType-driven query context"
  - "Count uncategorized with a dedicated query that includes null, empty string, and system uncategorized provider ID"

patterns-established:
  - "Sidebar payload contract: id/name/disabled/isUncategorized via TypeScript DTOs"
  - "Disabled-zero rule: disable only when count is zero and the category is not selected"

# Metrics
duration: 5 min
completed: 2026-02-26
---

# Phase 4 Plan 1: Shared Sidebar DTOs and Builder Summary

**Shared category sidebar/filter DTO contracts and a single aggregate-count builder now produce consistent Movies/Series sidebar items with A–Z ordering, uncategorized-last, and selected-zero enabled behavior.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-26T01:39:43Z
- **Completed:** 2026-02-26T01:44:46Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Added TypeScript-stable backend DTOs for sidebar items and selected category filter state.
- Added `BuildCategorySidebarItems` action with shared media-type logic and aggregate count queries.
- Added backend tests for both media types covering A–Z ordering, uncategorized flag exclusivity, and disabled-zero selection rules.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add TypeScript DTOs for category sidebar items + selected filters** - `c867209` (feat)
2. **Task 2: Add BuildCategorySidebarItems action (A–Z, Uncategorized last, disabled-zero via aggregate counts)** - `f0fa946` (feat)

## Files Created/Modified
- `app/Data/CategorySidebarItemData.php` - Shared sidebar item DTO with stable TypeScript shape.
- `app/Data/CategoryBrowseFiltersData.php` - Shared selected-category filters DTO.
- `app/Actions/BuildCategorySidebarItems.php` - Shared Movies/Series sidebar builder using aggregate counts.
- `tests/Feature/Settings/SyncCategoriesControllerTest.php` - Added sidebar ordering/flags/disabled assertions for both media types and Inertia test header helper.

## Decisions Made
- Used one shared sidebar builder action keyed by `MediaType` to avoid duplicated Movies/Series logic.
- Treated uncategorized counts as `null|''|system-uncategorized-id` to preserve correctness across legacy and normalized rows.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Added Inertia version header helper in sync categories feature tests**
- **Found during:** Task 1 verification
- **Issue:** Inertia GET test requests returned 409 without `X-Inertia-Version`, blocking required verification command.
- **Fix:** Added shared `inertiaHeaders()` helper and used it in sync history test requests.
- **Files modified:** `tests/Feature/Settings/SyncCategoriesControllerTest.php`
- **Verification:** `php artisan test --filter SyncCategoriesControllerTest`
- **Committed in:** `f0fa946`

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Fix was required to run mandated verification; no scope creep.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
Ready for `04-02-PLAN.md` to wire Movies index filtering and sidebar payload usage.
No blockers.

---
*Phase: 04-category-browse-filter-ux*
*Completed: 2026-02-26*
