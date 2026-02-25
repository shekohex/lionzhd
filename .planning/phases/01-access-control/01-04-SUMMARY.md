---
phase: 01-access-control
plan: 04
subsystem: auth
tags: [laravel, gates, middleware, access-control, pest, direct-download]

requires:
  - phase: 01-01
    provides: persisted role/subtype defaults and user factory role states
  - phase: 01-02
    provides: stable `server-download` and `download-operations` gates with deny messaging
provides:
  - Route-level middleware enforcement separating server-download and direct-download access
  - Regression coverage proving external members cannot trigger server-download/admin download operations
  - Regression coverage proving signed direct-download resolution remains publicly accessible
affects: [01-05, phase-2-download-ownership, phase-5-download-lifecycle]

tech-stack:
  added: []
  patterns:
    - Named gate middleware is applied directly to sensitive routes for server-side enforcement
    - Direct-download routes remain intentionally ungated by admin-only abilities

key-files:
  created:
    - tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php
  modified:
    - routes/web.php

key-decisions:
  - Separate download paths by intent at the router: `server-download` for queueing actions, unrestricted direct links for externals.
  - Keep download operations (`downloads.edit`, `downloads.destroy`) admin-only until ownership controls are implemented in Phase 2.

patterns-established:
  - Access boundaries for downloads are enforced at route middleware before controller integrations execute.
  - Authorization regression tests cover deny-paths and allow-paths together for ACCS-05 stability.

duration: 2 min
completed: 2026-02-25
---

# Phase 1 Plan 4: External Download Route Enforcement Summary

**Server-download actions are now admin-gated at the route layer while signed direct-download resolution remains available for external users, with regression tests locking both deny and allow paths.**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-25T04:46:54Z
- **Completed:** 2026-02-25T04:49:40Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Applied `can:server-download` to movie/series server-download routes and preserved unrestricted direct-download routes.
- Applied `can:download-operations` to download operation routes (`downloads.edit`, `downloads.destroy`).
- Added access-control regression tests for external/internal deny paths and direct signed-link redirect allow path.
- Verified route middleware wiring and passed full test suite.

## Task Commits

Each task was committed atomically:

1. **Task 1: Restrict server-download routes using can:server-download** - `0669781` (feat)
2. **Task 2: Add regression tests that server-download routes are blocked for External members** - `07129e3` (test)

## Files Created/Modified
- `routes/web.php` - Enforces `server-download` and `download-operations` gates on protected download routes while leaving direct routes accessible.
- `tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php` - Covers external/internal 403 boundaries and direct signed URL redirect accessibility.

## Decisions Made
- Enforce ACCS-05 at route middleware to block bypasses before controller-side integrations (Xtream/aria2) can run.
- Keep direct-download route access unchanged for external users to preserve the intended allowed path.
- Cover deny-paths with minimal DB fixtures and avoid integration-heavy controller execution in these regression tests.

## Deviations from Plan

None - plan executed exactly as written.

## Authentication Gates

None.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- ACCS-05 server-side route enforcement is in place and regression-tested.
- Ready for remaining Phase 1 access-control plans (`01-03-PLAN.md` and `01-05-PLAN.md`).

---
*Phase: 01-access-control*
*Completed: 2026-02-25*
