---
phase: 12-detail-page-category-context
plan: 05
subsystem: api
tags: [laravel, categories, detail-pages, typescript-transformer, pest]
requires:
  - phase: 12-detail-page-category-context
    provides: normalized media_category_assignments storage plus movie and series sync-order columns from plan 12-01
provides:
  - shared movie and series detail-category resolver entrypoints
  - DTO-backed detail category chip payload with generated TypeScript contract
  - regression coverage for canonical ordering and uncategorized normalization
affects: [movie detail controller, series detail controller, media hero category chips]
tech-stack:
  added: []
  patterns:
    - DTO-first detail category chips generated from PHP data classes
    - canonical detail ordering from category sync order plus assignment source order with deterministic provider fallback
key-files:
  created:
    - app/Actions/ResolveDetailPageCategories.php
    - app/Data/DetailPageCategoryChipData.php
    - tests/Feature/Actions/ResolveDetailPageCategoriesTest.php
  modified:
    - resources/js/types/generated.d.ts
    - tests/Feature/Actions/ResolveDetailPageCategoriesTest.php
key-decisions:
  - "Detail-page category chips read only from media_category_assignments joined to local categories, never user preference state or legacy detail DTO fields."
  - "Categories with sync-order values sort canonically first; unsorted categories fall back deterministically by provider id."
patterns-established:
  - "Shared detail resolvers should emit PHP DTOs and let typescript:transform own frontend contract generation."
  - "Uncategorized detail context is normalized at the resolver layer instead of controller-time inference."
requirements-completed: [CTXT-01, CTXT-02]
duration: 8 min
completed: 2026-03-22
---

# Phase 12 Plan 05: Add shared detail-category resolver and exported chip DTO contract Summary

**Shared detail-page category chips resolved from normalized assignments with a generated TypeScript DTO contract.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-22T23:51:43Z
- **Completed:** 2026-03-22T23:59:53Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Added one shared resolver for movie and series detail category chips backed by authoritative assignment storage.
- Added a TypeScript-exported `DetailPageCategoryChipData` DTO for later Inertia page-prop wiring.
- Locked canonical ordering, uncategorized normalization, and neutral hidden/ignored treatment with focused Pest coverage.

## task Commits

Each task was committed atomically:

1. **task 1: add shared resolver regressions for movie and series category chips** - `2919d69` (test)
2. **task 2: add the shared detail-category resolver and exported chip dto** - `9b990a1` (feat)

**Plan metadata:** final docs commit records summary/state updates.

## Files Created/Modified
- `app/Actions/ResolveDetailPageCategories.php` - Shared movie/series resolver over normalized assignment storage.
- `app/Data/DetailPageCategoryChipData.php` - PHP-to-TypeScript chip contract for detail page props.
- `tests/Feature/Actions/ResolveDetailPageCategoriesTest.php` - Resolver regressions for ordering, uncategorized normalization, and neutral preference handling.
- `resources/js/types/generated.d.ts` - Generated TypeScript export for the new detail chip DTO.

## Decisions Made
- Detail page category context stays server-owned and reads only from `media_category_assignments` plus local categories.
- Missing assignments normalize to the system uncategorized chip, while missing category rows do not invent fallback labels.
- Unsorted categories keep deterministic browse targets by falling back to provider-id ordering.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- Initial Eloquent join hydration aliased category provider ids through `id`; switched the resolver query to a base table join with explicit `provider_id` mapping before final verification.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Movie and series show controllers can now consume one shared DTO-backed resolver without exploring assignment storage directly.
- Hero UI/controller wiring plans can rely on generated TS types and resolver regressions already covering browse-link targets and normalization.

## Self-Check

PASSED

---
*Phase: 12-detail-page-category-context*
*Completed: 2026-03-22*
