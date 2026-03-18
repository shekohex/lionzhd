---
phase: 09-ignored-discovery-filters
plan: 04
subsystem: ui
tags: [react, inertia, typescript, sidebar, ignored-filters]
requires:
  - phase: 08-personal-category-controls
    provides: browse-attached sidebar manage surfaces and shared preference mutations
  - phase: 09-ignored-discovery-filters
    provides: ignored sidebar DTO contracts and persisted ignored preference metadata from plans 01 and 06
provides:
  - ignored-aware sidebar snapshots that send ignored ids through the existing preference PATCH route
  - shared category browser helpers for selected-category unignore and page-driven manage-mode requests
  - desktop browse and manage affordances that keep ignored rows visible, muted, and directly recoverable
affects: [phase-09-ui-recovery, movies-page, series-page, category-sidebar]
tech-stack:
  added: []
  patterns: [separate ignored-visible sidebar state, page-to-sidebar manage request handoff]
key-files:
  created: []
  modified:
    [resources/js/components/category-sidebar.tsx, resources/js/components/category-sidebar/browse.tsx, resources/js/components/category-sidebar/manage.tsx, resources/js/components/category-sidebar/types.ts, resources/js/hooks/use-category-browser.ts]
key-decisions:
  - "Manage-mode recovery now uses a monotonic request key so page CTAs can open sidebar manage mode without reaching into sidebar internals."
  - "Ignored rows are tracked separately from pinned and normal visible groups so unignore flows can restore saved pin and sort metadata."
patterns-established:
  - "Sidebar preference snapshots always serialize ignoredIds alongside pinnedIds, visibleIds, and hiddenIds."
  - "Desktop browse can mutate ignore state inline, while mobile keeps ignore controls inside the existing manage surface."
requirements-completed: [IGNR-01, IGNR-02]
duration: 8 min
completed: 2026-03-18
---

# Phase 09 Plan 04: Shared Ignore Mutation Summary

**Ignored category mutations now flow through shared sidebar snapshots, page-level unignore/manage helpers, and desktop/mobile controls that keep ignored rows visible but muted.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-18T19:32:45Z
- **Completed:** 2026-03-18T19:40:56Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Extended sidebar preference snapshots and the browser hook to carry `ignoredIds` through the existing `category-preferences.update` PATCH flow.
- Added shared hook helpers for selected-category unignore and page-triggered manage-mode recovery without rebuilding snapshot payloads in page files.
- Implemented desktop browse/manage ignore controls plus mobile manage-only ignore/unignore flows while keeping ignored rows visible, muted, and sorted below normal visible rows.

## task Commits

Each task was committed atomically:

1. **task 1: extend shared snapshot and hook helpers for ignored recovery actions** - `d1329bf` (feat)
2. **task 2: implement desktop and mobile ignore affordances with muted visible rows** - `2b3dc99` (feat)

**Plan metadata:** Pending

_Note: TDD tasks may have multiple commits (test → feat → refactor)_

## Files Created/Modified
- `resources/js/components/category-sidebar/types.ts` - Adds `ignoredIds` and a page-driven manage request prop to the shared sidebar contract.
- `resources/js/components/category-sidebar.tsx` - Tracks ignored rows as a separate visible group, serializes ignored ids, and wires manage-request/unignore state transitions.
- `resources/js/hooks/use-category-browser.ts` - Sends `ignored_ids`, exposes `handleUnignoreCategory`, and exposes `requestManageMode`/`manageRequestKey` for recovery CTAs.
- `resources/js/components/category-sidebar/browse.tsx` - Adds desktop quick ignore/unignore actions and muted ignored-row rendering while keeping mobile browse action-free.
- `resources/js/components/category-sidebar/manage.tsx` - Adds ignore/unignore controls inside manage mode with a dedicated ignored section for desktop and mobile.

## Decisions Made
- Used a monotonic `manageRequestKey` handoff so future page recovery CTAs can request sidebar manage mode without controlling the sidebar view state directly.
- Restored ignored categories back into pinned or non-pinned visible groups based on existing metadata so saved order survives unignore actions.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Switched verification from `pnpm` to the repo's available Bun runtime**
- **Found during:** task 1 (extend shared snapshot and hook helpers for ignored recovery actions)
- **Issue:** The plan's `pnpm lint` verification command could not run because `pnpm` was unavailable and the repo declares `bun` as its package manager.
- **Fix:** Verified both tasks with `bun run lint`, which executes the same ESLint script through the configured package manager.
- **Files modified:** None
- **Verification:** `bun run lint`
- **Committed in:** N/A (verification-only deviation)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Verification command changed to match the repo runtime. Implementation scope stayed unchanged.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Movie and series recovery pages can now call `handleUnignoreCategory` and `requestManageMode` without rebuilding sidebar snapshots.
- Sidebar browse/manage surfaces now expose the ignored-state affordances that the Phase 09 recovery UI and browser tests expect.

## Self-Check: PASSED

- Found `.planning/phases/09-ignored-discovery-filters/09-04-SUMMARY.md`
- Found commit `d1329bf`
- Found commit `2b3dc99`
