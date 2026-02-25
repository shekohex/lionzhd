---
phase: 02-download-ownership-authorization
plan: 02
subsystem: api
tags: [laravel, authorization, downloads, aria2, ownership]

requires:
  - phase: 02-download-ownership-authorization
    provides: download ownership schema/model/DTO contract from 02-01
provides:
  - Movie and series server-download creation persists initiating `user_id` on each new `MediaDownloadRef`
  - Member active-download dedupe checks are owner-scoped while admin dedupe remains global
  - Safe optional `return_to` forwarding for server-download redirects under `/downloads`
affects: [phase-2-plan-03-download-authorization-enforcement, phase-2-plan-04-downloads-ui-ownership, phase-5-download-lifecycle-reliability]

tech-stack:
  added: []
  patterns:
    - Route-triggered download creation always binds ownership from authenticated user at write time
    - Active-download lookup accepts caller context and enforces member-own-only query scoping
    - Downloads index tolerates aria2 RPC outages by falling back to empty status payloads

key-files:
  created: []
  modified:
    - app/Http/Controllers/VodStream/VodStreamDownloadController.php
    - app/Http/Controllers/Series/SeriesDownloadController.php
    - app/Actions/GetActiveDownloads.php
    - app/Http/Controllers/MediaDownloadsController.php
    - tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php

key-decisions:
  - Validate `return_to` against `^/downloads(?:[/?]|$)` and prefer it over default downloads route redirects when present.
  - Scope member dedupe queries in `GetActiveDownloads` by `user_id`; keep admin dedupe global.
  - Swallow aria2 JSON-RPC failures during downloads index status hydration to avoid user-facing 500s.

patterns-established:
  - Dedupe checks now require explicit caller context for ownership-safe reuse.

duration: 6 min
completed: 2026-02-25
---

# Phase 2 Plan 2: Owned server-download creation and ownership-safe dedupe summary

**Server-download creation now writes owner IDs, member dedupe no longer leaks cross-user active downloads, and `/downloads` redirects can safely round-trip `return_to` context.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-02-25T06:48:37Z
- **Completed:** 2026-02-25T06:55:29Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Wired movie/series server-download creation to persist `user_id` for the initiating authenticated user (including series batch flow).
- Added safe optional `return_to` handling so server-download redirects can return to an existing `/downloads` context when requested.
- Made `GetActiveDownloads` ownership-aware by scoping member lookups to `user_id` and passing current user from both movie and series controllers.

## Task Commits

Each task was committed atomically:

1. **Task 1: Persist user_id on new download refs for movies + series (single + batch)** - `d7bae0f` (feat)
2. **Task 2: Make GetActiveDownloads ownership-aware for members** - `cf208a8` (feat)

## Files Created/Modified
- `app/Http/Controllers/VodStream/VodStreamDownloadController.php` - Persists owner on new movie refs, supports safe `return_to`, passes user into dedupe lookup.
- `app/Http/Controllers/Series/SeriesDownloadController.php` - Persists owner for single/batch episode refs, supports safe `return_to`, passes user into dedupe lookup.
- `app/Actions/GetActiveDownloads.php` - Accepts optional current user and scopes member dedupe queries by `user_id`.
- `app/Http/Controllers/MediaDownloadsController.php` - Handles aria2 RPC failures during index status hydration without crashing the page.
- `tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php` - Aligns internal non-owner operation expectation to 404 behavior.

## Decisions Made
- `return_to` is honored only when it targets `/downloads` (or a subpath/query under it), preventing unsafe open redirects.
- Member dedupe is ownership-scoped, while admin dedupe behavior remains global by design.
- Downloads index treats aria2 connectivity failures as non-fatal and still returns owned rows.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Updated stale access-control expectation for internal non-owner operations**
- **Found during:** Task 1 verification (`php artisan test`)
- **Issue:** Existing test expected `403` for internal member operations on non-owned downloads, but gate contract returns `404` via `denyAsNotFound()`.
- **Fix:** Updated the test to create a non-owned row and assert `404`.
- **Files modified:** `tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php`
- **Verification:** `php artisan test` passes with ownership gate behavior.
- **Committed in:** `d7bae0f`

**2. [Rule 2 - Missing Critical] Added aria2 outage fallback in downloads index status hydration**
- **Found during:** Task 2 verification (`php artisan test`)
- **Issue:** Downloads list could throw `JsonRpcException` and return `500` when aria2 was unavailable.
- **Fix:** Wrapped status hydration in `try/catch` and fallback to an empty status collection.
- **Files modified:** `app/Http/Controllers/MediaDownloadsController.php`
- **Verification:** `php artisan test` passes; downloads index remains available without aria2.
- **Committed in:** `cf208a8`

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 missing critical)
**Impact on plan:** Both fixes were required for stable verification and production-safe behavior; scope remained within download ownership flows.

## Authentication Gates

None.

## Issues Encountered
- Route cache initially preserved stale authorization middleware wiring; clearing framework caches resolved verification drift.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- DOWN-04 ownership write path is implemented for movie + series server-download creation.
- Member dedupe leakage risk is closed by owner-scoped active-download matching.
- Ready for `02-03-PLAN.md` to continue authorization enforcement/routing and end-to-end ownership boundaries.

---
*Phase: 02-download-ownership-authorization*
*Completed: 2026-02-25*
