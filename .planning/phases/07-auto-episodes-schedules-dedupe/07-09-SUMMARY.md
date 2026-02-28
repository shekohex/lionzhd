---
phase: 07-auto-episodes-schedules-dedupe
plan: 09
subsystem: ui
tags: [react, inertia, typescript, auto-episodes, monitoring]

requires:
  - phase: 07-07
    provides: monitoring mutation endpoints and access-control enforcement
  - phase: 07-08
    provides: reusable schedule editor dialog and series-detail monitoring UX patterns
  - phase: 07-12
    provides: typed monitoring DTO contracts for schedules page props
provides:
  - central Settings → Monitoring management page listing monitored series and run status
  - bulk schedule preset apply and global automation pause/resume controls
  - activity log filtering and visible-but-disabled mutation UX for External members
affects: [phase-7-completion, next-phase-planning]

tech-stack:
  added: []
  patterns:
    - centralized monitoring management UI in settings using existing schedule editor primitives
    - role-aware disabled control pattern keeps External member visibility while preventing mutations

key-files:
  created:
    - resources/js/types/auto-episodes.ts
  modified:
    - resources/js/layouts/settings/layout.tsx
    - resources/js/pages/settings/schedules.tsx

key-decisions:
  - "Expose Settings monitoring navigation to all authenticated users, not admin-only"
  - "Render full schedules page for External members with all mutation controls disabled and explained"

patterns-established:
  - "Settings monitoring pattern: monitor table + bulk actions + activity log + global pause state"
  - "Shared schedule-editor contract reused across single-item and bulk management flows"

duration: 4 min
completed: 2026-02-28
---

# Phase 7 Plan 9: Central monitoring management page in settings/schedules summary

**Settings → Schedules now ships a typed central monitoring console with monitor status visibility, bulk preset operations, global pause/resume, and External-member visible-but-disabled controls.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-28T15:20:49Z
- **Completed:** 2026-02-28T15:25:22Z
- **Tasks:** 3/3
- **Files modified:** 3

## Accomplishments
- Added a non-admin-only settings navigation entry to `/settings/schedules` labeled for monitoring management access.
- Replaced schedules placeholder UI with monitor list/status details, bulk preset application, global pause/resume toggle, and activity-log filtering.
- Completed human verification checkpoint with approved behavior for internal/admin controls and External-member disabled UX.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add settings navigation entry for monitoring/schedules** - `48c36d5` (feat)
2. **Task 2: Implement monitoring management UI in settings/schedules page** - `d8c0842` (feat)
3. **Task 3: Human verification checkpoint** - Approved (no code commit)

## Files Created/Modified
- `resources/js/layouts/settings/layout.tsx` - Added monitoring/schedules link in settings navigation for all users.
- `resources/js/pages/settings/schedules.tsx` - Implemented full management page with monitor table, bulk presets, pause/resume, and activity filters.
- `resources/js/types/auto-episodes.ts` - Added schedules-page monitoring types aligned with backend DTO props.

## Decisions Made
- Exposed the Settings monitoring navigation entry to all authenticated users while preserving mutation authorization boundaries in page actions.
- Kept External-member UX visible by rendering the full management surface but disabling all mutating controls with explanation copy.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 7 plan set is now complete with approved monitoring management UX.
- Ready for roadmap transition to the next phase.

---
*Phase: 07-auto-episodes-schedules-dedupe*
*Completed: 2026-02-28*
