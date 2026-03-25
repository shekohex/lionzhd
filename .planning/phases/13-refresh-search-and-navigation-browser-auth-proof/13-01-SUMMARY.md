---
phase: 13-refresh-search-and-navigation-browser-auth-proof
plan: 01
subsystem: testing
tags: [browser, pest, playwright, auth, inertia]
requires:
  - phase: 10-searchable-category-navigation
    provides: current desktop and mobile category-search browser assertions
  - phase: 11-correct-search-mode-ux
    provides: current /search browser assertions and auth-proof targets
provides:
  - shared live browser login bootstrap loaded from Pest
  - canonical login-copy and /discover redirect assertions for browser suites
  - sidebar browser coverage migrated off its suite-local auth helper
affects: [13-02, 13-03, browser-tests]
tech-stack:
  added: []
  patterns: [shared browser auth bootstrap, live login-path assertions, Pest support helper loading]
key-files:
  created: [tests/Browser/Support/browser-auth.php]
  modified: [tests/Pest.php, tests/Browser/CategorySidebarScrollTest.php]
key-decisions:
  - "Browser auth proof now lives in a single Pest-loaded support helper instead of suite-local login helpers."
  - "The shared helper asserts the live login form copy and the authenticated /discover landing before suite navigation begins."
patterns-established:
  - "Browser suites can call browserLogin or browserLoginAndVisit to reuse the live auth bootstrap."
  - "Verification for browser auth proof runs against the local composer dev stack when Vite-backed pages need live assets."
requirements-completed: [NAVG-01, SRCH-01, SRCH-02, SRCH-03, SRCH-04]
duration: 3min
completed: 2026-03-25
---

# Phase 13 Plan 01: Canonical browser auth bootstrap summary

**Shared Pest browser login bootstrap that proves live auth copy and the `/discover` redirect before sidebar, search, and navigation flows continue.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-25T10:31:00Z
- **Completed:** 2026-03-25T10:34:21Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Added one shared browser auth helper file for live login assertions.
- Loaded the shared helper from Pest so browser suites can reuse the same bootstrap.
- Migrated the sidebar overflow browser suite off its stale local login helper.

## task Commits

Each task was committed atomically:

1. **task 1: register a shared live-login browser helper** - `2581ecb` (test)
2. **task 2: migrate the sidebar scroll browser suite to the shared bootstrap** - `c5a15eb` (test)

**Plan metadata:** Pending

## Files Created/Modified
- `tests/Browser/Support/browser-auth.php` - shared live browser login helpers with login-copy and `/discover` assertions.
- `tests/Pest.php` - loads the shared browser auth support file for global helper access.
- `tests/Browser/CategorySidebarScrollTest.php` - replaces the suite-local login helper with the shared bootstrap.

## Decisions Made
- Centralized browser auth proof in `tests/Browser/Support/browser-auth.php` so future suite refreshes can reuse one canonical helper.
- Kept the helper test-only and treated the app's real login copy plus `/discover` redirect as the source of truth.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Started the local app and Vite stack for browser verification**
- **Found during:** task 1 (register a shared live-login browser helper)
- **Issue:** the first browser verification hit a blank `/login` render because the local Laravel/Vite stack was not running, so live auth copy never became visible.
- **Fix:** started `composer dev` in background PTY `pty_1fa9c19b` and re-ran the browser verification against the live stack.
- **Files modified:** none
- **Verification:** `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/CategorySidebarScrollTest.php --filter="matches the desktop movie category sidebar height to the main content section and scrolls long category lists" --stop-on-failure`
- **Committed in:** N/A

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Verification environment only. No product or test-scope creep.

## Issues Encountered
- Initial browser verification produced a blank login page screenshot until the local composer dev stack was running.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `13-02-PLAN.md` can migrate `/search` browser coverage onto the shared live-login bootstrap.
- `13-03-PLAN.md` can reuse the same helper for searchable-navigation browser proof instead of editing auth steps per suite.

## Self-Check: PASSED

- Found `.planning/phases/13-refresh-search-and-navigation-browser-auth-proof/13-01-SUMMARY.md`
- Found commit `2581ecb`
- Found commit `c5a15eb`

---
*Phase: 13-refresh-search-and-navigation-browser-auth-proof*
*Completed: 2026-03-25*
