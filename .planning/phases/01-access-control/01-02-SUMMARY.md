---
phase: 01-access-control
plan: 02
subsystem: auth
tags: [laravel, inertia, gates, access-control, telescope, pulse]

requires:
  - phase: 01-01
    provides: persisted role/subtype/super-admin user fields and factory states
provides:
  - Role/subtype authorization gates with explicit denial reasons
  - Admin-only enforcement for system settings and sync-media endpoints
  - Inertia forbidden page with reason-aware 403 responses
  - Last-admin and super-admin self-delete protection
affects: [01-03, 01-04, 01-05, phase-2-download-ownership, phase-7-auto-episodes]

tech-stack:
  added: []
  patterns:
    - Gate responses return explicit denial messages for UI-facing 403 reasons
    - Settings access is enforced by route-level can middleware
    - Inertia authorization failures render a shared in-app forbidden page

key-files:
  created:
    - resources/js/pages/settings/schedules.tsx
    - resources/js/pages/errors/forbidden.tsx
    - tests/Feature/AccessControl/SchedulesAccessTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - app/Providers/TelescopeServiceProvider.php
    - app/Providers/PulseServiceProvider.php
    - routes/settings.php
    - bootstrap/app.php
    - app/Http/Controllers/Settings/ProfileController.php
    - tests/Feature/Controllers/SyncMediaControllerTest.php
    - database/factories/UserFactory.php

key-decisions:
  - Define stable named gates in AppServiceProvider and reuse them via can middleware.
  - Restrict Inertia 403 rendering to Inertia requests to preserve non-Inertia error behavior.
  - Enforce no-zero-admin invariant in profile self-delete by blocking super-admin and last-admin deletion.

patterns-established:
  - Authorization errors expose actionable reason strings to frontend error UX.
  - Admin-only monitoring access is role-driven (no user-id special casing).

duration: 7 min
completed: 2026-02-25
---

# Phase 1 Plan 2: Access Boundary Enforcement Summary

**Role-based gates now enforce admin/internal/external boundaries across settings and monitoring, with a reasoned Inertia 403 experience and safeguards against deleting super-admin or the last admin.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-02-25T04:37:19Z
- **Completed:** 2026-02-25T04:44:18Z
- **Tasks:** 3
- **Files modified:** 11

## Accomplishments
- Added all required access-control gates (`admin`, `super-admin`, member subtype gates, download/admin gates, and `auto-download-schedules`) with `Response::deny(...)` messages.
- Replaced Telescope/Pulse `id===1` authorization with admin-role checks.
- Locked xtreamcodes/aria2/syncmedia settings routes behind `can:admin` and added `/settings/schedules` behind `can:auto-download-schedules`.
- Added schedule placeholder page for Phase 7 and authorization coverage tests for admin/internal/external behavior.
- Added centralized Inertia forbidden page rendering for authorization 403 responses and wired explicit reason/message props.
- Protected profile self-delete from removing super-admin accounts or collapsing the system into zero-admin state.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add gates for Admin/Member + Internal/External + super-admin** - `d40dba9` (feat)
2. **Task 2: Lock down admin-only settings + sync/import endpoints** - `2d982ce` (feat)
3. **Task 3: Inertia 403 UX + prevent self-delete of super-admin/last admin** - `2552ded` (feat)

## Files Created/Modified
- `app/Providers/AppServiceProvider.php` - Defines all phase-required gates with explicit denial reasons.
- `app/Providers/TelescopeServiceProvider.php` - Gates Telescope access by admin role.
- `app/Providers/PulseServiceProvider.php` - Gates Pulse access by admin role.
- `routes/settings.php` - Applies `can:admin` and `can:auto-download-schedules` route protection.
- `resources/js/pages/settings/schedules.tsx` - Adds schedule settings placeholder page with “Coming in Phase 7.”
- `tests/Feature/AccessControl/SchedulesAccessTest.php` - Verifies external denied; internal/admin allowed for `/settings/schedules`.
- `tests/Feature/Controllers/SyncMediaControllerTest.php` - Verifies admin allow/member deny and Inertia forbidden response shape.
- `bootstrap/app.php` - Adds exceptions response handler to render `errors/forbidden` for Inertia 403 authorization failures.
- `resources/js/pages/errors/forbidden.tsx` - Provides in-app forbidden UX with reason, message, Back/Home, and super-admin contact guidance.
- `app/Http/Controllers/Settings/ProfileController.php` - Blocks super-admin or last-admin self-delete via authorization exception.
- `database/factories/UserFactory.php` - Fixes state closures for reliable role/subtype factory state usage in tests.

## Decisions Made
- Keep gate names stable and central in `AppServiceProvider` so route middleware and future policies share one authorization contract.
- Use explicit denial messages (`Admin-only`, `Internal-only`, `External accounts cannot perform this action`) to drive user-visible 403 reasons.
- Scope Inertia 403 rendering to Inertia requests to avoid changing non-Inertia response behavior unexpectedly.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed factory state closures that crashed role-based test setup**
- **Found during:** Task 2 verification (`php artisan test --filter SyncMediaControllerTest`)
- **Issue:** `UserFactory` used static state closures, causing `Cannot bind an instance to a static closure` when invoking role/subtype factory states.
- **Fix:** Converted state closures (`unverified`, `admin`, `superAdmin`, `memberInternal`, `memberExternal`) to bindable closures.
- **Files modified:** `database/factories/UserFactory.php`
- **Verification:** Sync media and full test suite passed after fix.
- **Committed in:** `2d982ce`

**2. [Rule 3 - Blocking] Adjusted schedules access tests to Inertia requests**
- **Found during:** Task 2 verification (`php artisan test`)
- **Issue:** Non-Inertia successful page assertions attempted to render Blade app shell and failed in tests due missing Vite build manifest.
- **Fix:** Switched schedule access assertions to Inertia request headers and validated component payload (`settings/schedules`).
- **Files modified:** `tests/Feature/AccessControl/SchedulesAccessTest.php`
- **Verification:** `php artisan test --filter SchedulesAccessTest` passed, then full suite passed.
- **Committed in:** `2d982ce`

---

**Total deviations:** 2 auto-fixed (2 blocking)
**Impact on plan:** Both fixes were required to complete mandatory test verification; no feature scope expansion.

## Authentication Gates

None.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Ready for `01-03-PLAN.md`.
- Authorization primitives and forbidden UX are now in place for user-management workflows.

---
*Phase: 01-access-control*
*Completed: 2026-02-25*
