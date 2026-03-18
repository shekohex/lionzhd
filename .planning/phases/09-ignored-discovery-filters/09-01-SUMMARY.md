---
phase: 09-ignored-discovery-filters
plan: 01
subsystem: api
tags: [laravel, inertia, typescript, categories, ignored-filters]
requires:
  - phase: 08-personal-category-controls
    provides: personalized sidebar wrappers and browse-attached category read paths
  - phase: 09-ignored-discovery-filters
    provides: persisted is_ignored preference storage from plan 06
provides:
  - ignored-aware sidebar DTO fields for visible rows and selected recovery state
  - generated browse filter recovery contracts shared by movie and series pages
  - regression coverage for ignored sidebar ordering and hidden-state separation
affects: [phase-09-browse-filters, phase-09-ui-recovery, movies-page, series-page]
tech-stack:
  added: []
  patterns: [generated browse filter aliases, separate hidden vs ignored sidebar metadata]
key-files:
  created: [app/Data/CategoryBrowseRecoveryStateData.php]
  modified:
    [app/Actions/BuildPersonalizedCategorySidebar.php, app/Data/CategoryBrowseFiltersData.php, app/Data/CategorySidebarData.php, app/Data/CategorySidebarItemData.php, resources/js/types/generated.d.ts, resources/js/types/movies.ts, resources/js/types/series.ts, tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php]
key-decisions:
  - "Recovery metadata lives under filters.recovery so later browse pages can distinguish hidden-vs-ignored empty states without new top-level props."
  - "Ignored categories stay in visibleItems with explicit ignored flags instead of reusing hidden-category behavior."
patterns-established:
  - "Movie and series browse prop types alias App.Data.CategoryBrowseFiltersData so generated contracts remain the single source of truth."
  - "Sidebar selection metadata exposes hidden and ignored states independently while keeping selectedCategoryName shared for recovery copy."
requirements-completed: [IGNR-01, IGNR-02]
duration: 6 min
completed: 2026-03-18
---

# Phase 09 Plan 01: Ignored Sidebar and Browse Contract Summary

**Ignored-aware sidebar DTOs now expose visible muted rows, selected ignored recovery metadata, and shared generated browse recovery contracts for movies and series.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-03-18T19:22:04Z
- **Completed:** 2026-03-18T19:27:47Z
- **Tasks:** 2
- **Files modified:** 12

## Accomplishments
- Added `CategoryBrowseRecoveryStateData` and extended browse/sidebar DTOs with explicit ignored-state fields.
- Regenerated TypeScript contracts and replaced handwritten movie/series browse filter interfaces with generated aliases.
- Added ignored-state sidebar regression coverage and updated sidebar assembly to keep ignored rows visible while separating hidden rows.

## task Commits

Each task was committed atomically:

1. **task 0: define ignored-state backend and TS contracts** - `5a3a8e7` (feat)
2. **task 1: assemble ignored categories as visible-but-muted sidebar rows** - `1519363` (test), `e88bc1b` (feat)

**Plan metadata:** Pending

_Note: TDD tasks may have multiple commits (test → feat → refactor)_

## Files Created/Modified
- `app/Data/CategoryBrowseRecoveryStateData.php` - Adds page-level hidden/ignored empty-state recovery flags.
- `app/Data/CategoryBrowseFiltersData.php` - Carries selected category plus optional recovery metadata.
- `app/Data/CategorySidebarData.php` - Exposes selected ignored-state metadata alongside hidden-state metadata.
- `app/Data/CategorySidebarItemData.php` - Adds per-row ignored-state visibility to sidebar items.
- `app/Actions/BuildPersonalizedCategorySidebar.php` - Keeps ignored categories visible, sorted after non-ignored rows, and detectable when selected.
- `resources/js/types/generated.d.ts` - Regenerated TS declarations for the ignored-aware browse/sidebar DTOs.
- `resources/js/types/movies.ts` - Aliases movie browse filters to the generated DTO contract.
- `resources/js/types/series.ts` - Aliases series browse filters to the generated DTO contract.
- `tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php` - Covers ignored row ordering, selected ignored state, and hidden/ignored separation.

## Decisions Made
- Kept browse recovery flags under `filters.recovery` so later page plans can consume one stable read contract.
- Preserved separate hidden and ignored sidebar metadata instead of collapsing ignored categories into the hidden collection.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Updated existing DTO call sites for the expanded read contract**
- **Found during:** task 0 (define ignored-state backend and TS contracts)
- **Issue:** Adding new browse/sidebar DTO fields left existing constructor call sites incomplete, which would have broken current browse payload assembly.
- **Fix:** Updated browse controllers and `BuildCategorySidebarItems` to supply the new recovery and ignored fields while preserving existing behavior.
- **Files modified:** `app/Actions/BuildCategorySidebarItems.php`, `app/Http/Controllers/VodStream/VodStreamController.php`, `app/Http/Controllers/Series/SeriesController.php`
- **Verification:** `php artisan typescript:transform`
- **Committed in:** `5a3a8e7`

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Required to keep the expanded DTO contract compatible with current browse reads. No scope creep.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Movie and series browse plans can now attach actual `filters.recovery` values without changing frontend prop types again.
- UI recovery work can rely on `categories.selectedCategoryIsIgnored`, `selectedCategoryName`, and per-row `isIgnored` flags.

## Self-Check: PASSED

- Found `.planning/phases/09-ignored-discovery-filters/09-01-SUMMARY.md`
- Found commit `5a3a8e7`
- Found commit `1519363`
- Found commit `e88bc1b`
