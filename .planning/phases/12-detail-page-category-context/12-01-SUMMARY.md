---
phase: 12-detail-page-category-context
plan: 01
subsystem: database
tags: [laravel, categories, sync, migrations, pest]
requires:
  - phase: 09-ignored-discovery-filters
    provides: browse recovery semantics and shared category ids used by detail context
provides:
  - normalized media category assignment storage for movies and series
  - legacy category backfill into authoritative assignment rows
  - separate canonical sync order for vod and series categories
affects: [detail-page-category-context, controllers, resolver, ui]
tech-stack:
  added: []
  patterns: [normalized category assignments, media-specific canonical sync ordering, migration backfill for legacy reads]
key-files:
  created: [database/migrations/2026_03_22_000001_add_detail_sync_order_to_categories_table.php, database/migrations/2026_03_22_000002_create_media_category_assignments_table.php, app/Models/MediaCategoryAssignment.php]
  modified: [app/Actions/SyncCategories.php, app/Actions/SyncMedia.php, app/Models/Category.php, tests/Feature/Jobs/SyncCategoriesTest.php, tests/Feature/Jobs/RefreshMediaContentsTest.php]
key-decisions:
  - "Canonical detail category order is persisted on categories as separate vod_sync_order and series_sync_order columns."
  - "Authoritative detail-page category membership is read from media_category_assignments, backfilled from legacy category_id values and refreshed from upstream category_ids payloads."
patterns-established:
  - "SyncMedia sanitizes upstream rows before upsert while persisting richer assignment data through a separate normalized table."
  - "Category sync clears per-media order columns when a category leaves that media scope."
requirements-completed: [CTXT-01, CTXT-02]
duration: 12min
completed: 2026-03-22
---

# Phase 12 Plan 01: Canonical detail-category storage summary

**Normalized movie and series category assignment storage with migration backfill and separate canonical sync order for each media type**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-22T23:38:00Z
- **Completed:** 2026-03-22T23:50:22Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- Added authoritative `media_category_assignments` storage for movie and series category membership.
- Backfilled legacy single-category media rows so existing titles keep detail context before the next upstream refresh.
- Persisted independent VOD and series canonical sync order and locked both sync contracts with focused backend tests.

## task Commits

Each task was committed atomically:

1. **task 1: lock authoritative assignment and sync-order contracts** - `7a85b7f` (test)
2. **task 2: persist authoritative title-category assignments and per-media canonical sync order** - `493ccc6` (feat)

**Plan metadata:** Pending

## Files Created/Modified
- `database/migrations/2026_03_22_000001_add_detail_sync_order_to_categories_table.php` - adds `vod_sync_order` and `series_sync_order` to categories.
- `database/migrations/2026_03_22_000002_create_media_category_assignments_table.php` - creates normalized assignment storage and backfills legacy media rows.
- `app/Models/MediaCategoryAssignment.php` - authoritative read model for synced title-category assignments.
- `app/Actions/SyncCategories.php` - preserves upstream payload order per media type and clears stale per-scope order state.
- `app/Actions/SyncMedia.php` - rewrites assignment rows from upstream payloads while preserving legacy browse/search category ids.
- `tests/Feature/Jobs/SyncCategoriesTest.php` - locks VOD and series canonical order persistence.
- `tests/Feature/Jobs/RefreshMediaContentsTest.php` - locks normalized assignment writes, dedupe behavior, and legacy backfill coverage.

## Decisions Made
- Persisted canonical detail ordering separately for VOD and series so shared category ids cannot drift between media scopes.
- Kept legacy media-table `category_id` untouched for existing browse/search behavior while making normalized assignment rows the new authoritative detail source.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Renamed duplicate refresh-media test helpers**
- **Found during:** task 2 (persist authoritative title-category assignments and per-media canonical sync order)
- **Issue:** new refresh-media helper names collided with existing sync-category test helpers and prevented the RED run from executing.
- **Fix:** renamed the refresh-media helper functions and updated the new test calls.
- **Files modified:** `tests/Feature/Jobs/RefreshMediaContentsTest.php`
- **Verification:** `./vendor/bin/pest tests/Feature/Jobs/SyncCategoriesTest.php tests/Feature/Jobs/RefreshMediaContentsTest.php --stop-on-failure`
- **Committed in:** `493ccc6` (part of task commit)

**2. [Rule 3 - Blocking] Re-ran the real assignment backfill migration inside coverage**
- **Found during:** task 2 (persist authoritative title-category assignments and per-media canonical sync order)
- **Issue:** the new series backfill assertion needed to prove the actual migration bootstrap behavior, not a handwritten approximation.
- **Fix:** dropped the assignment table in-test and re-executed the new migration after inserting legacy rows.
- **Files modified:** `tests/Feature/Jobs/RefreshMediaContentsTest.php`
- **Verification:** `./vendor/bin/pest tests/Feature/Jobs/SyncCategoriesTest.php tests/Feature/Jobs/RefreshMediaContentsTest.php --stop-on-failure`
- **Committed in:** `493ccc6` (part of task commit)

---

**Total deviations:** 2 auto-fixed (2 blocking)
**Impact on plan:** Both fixes were required to keep the new regression coverage executable and representative. No scope creep.

## Issues Encountered
- Targeted RED initially failed on helper redeclaration and then on numeric-id assertion mismatch; both were corrected inline before the final green run.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Controllers and shared detail resolvers can now read authoritative multi-category assignments without relying on legacy single-category fields.
- UI work can trust stable per-media canonical ordering from `categories.vod_sync_order` and `categories.series_sync_order`.

## Self-Check: PASSED

- Found `.planning/phases/12-detail-page-category-context/12-01-SUMMARY.md`
- Found commit `7a85b7f`
- Found commit `493ccc6`

---
*Phase: 12-detail-page-category-context*
*Completed: 2026-03-22*
