---
phase: 02-download-ownership-authorization
plan: 03
subsystem: auth
tags: [laravel, gates, inertia, authorization, ownership]

requires:
  - phase: 02-01
    provides: MediaDownloadRef owner column, owner relation, owner-aware DTO payloads
provides:
  - Role-correct server-download gate allowing admin/internal and denying external with explicit message
  - Model-aware download-operations gate with member own-only enforcement and denyAsNotFound cross-user behavior
  - Member-own-only /downloads list scoping with admin-wide listing and owner eager-loading
  - Regression tests for role/ownership gates and downloads list scoping without aria2 dependency
affects: [02-04, 02-05, downloads-ui, retry-flow]

tech-stack:
  added: []
  patterns:
    - Centralized model-aware Gate authorization for ownership checks
    - Controller-level role-based query scoping for list payload isolation
    - Gate-first authorization tests avoiding controller-side side effects

key-files:
  created: []
  modified:
    - app/Providers/AppServiceProvider.php
    - routes/web.php
    - app/Http/Controllers/MediaDownloadsController.php
    - tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php

key-decisions:
  - "Use denyAsNotFound for member cross-user download operations"
  - "Scope member /downloads at query layer, not global model scopes"
  - "Mock aria2 transport in list-scope tests to keep suite offline"

patterns-established:
  - "download-operations uses can middleware with bound model argument"
  - "admin listing enriches owner metadata via eager-loaded owner relation"

duration: 6 min
completed: 2026-02-25
---

# Phase 2 Plan 03: Ownership Authorization Boundary Summary

**Model-aware download gates now enforce admin-any/member-own-only behavior, and /downloads payloads are scoped to owned rows for members.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-02-25T06:48:31Z
- **Completed:** 2026-02-25T06:54:51Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Updated `server-download` gate to allow admins/internal members and explicitly deny external members with Direct Download guidance.
- Converted `download-operations` into a model-aware gate enforcing internal member ownership with `denyAsNotFound()` on cross-user access.
- Bound downloads routes to `can:download-operations,model` and scoped member `/downloads` query results to own `user_id` while preserving admin-wide view and owner metadata.
- Expanded `ExternalDownloadRestrictionsTest` with gate-level assertions and explicit member list scoping assertions.

## Task Commits

Each task was committed atomically:

1. **Task 1: Update server-download + download-operations gates** - `6ce901d` (feat)
2. **Task 2: Bind model in can middleware and scope downloads index** - `d0fd057` (feat)
3. **Task 3: Update download restriction regression tests** - `4d89c6b` (test)

**Plan metadata:** pending docs(02-03) commit

## Files Created/Modified
- `app/Providers/AppServiceProvider.php` - Role-aware `server-download` and model-aware `download-operations` gates with ownership enforcement.
- `routes/web.php` - Route middleware now passes bound `model` into `download-operations` gate.
- `app/Http/Controllers/MediaDownloadsController.php` - Member own-only list query, admin owner eager-loading, preserved pagination/query behavior.
- `tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php` - Added gate assertions and `/downloads` list scoping coverage.

## Decisions Made
- Kept authorization contract centralized in `AppServiceProvider` and enforced ownership through gate + route middleware model binding.
- Applied member list isolation in controller query composition rather than global scopes.
- Used mocked aria2 responses in list scoping tests to keep tests deterministic and network-independent.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- DOWN-01/02/03 server-boundary authorization requirements are implemented and covered.
- Ready for `02-04-PLAN.md`.
- No blockers or concerns.

---
*Phase: 02-download-ownership-authorization*
*Completed: 2026-02-25*
