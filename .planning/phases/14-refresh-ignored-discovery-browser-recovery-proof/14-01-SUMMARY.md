---
phase: 14-refresh-ignored-discovery-browser-recovery-proof
plan: 01
subsystem: testing
tags: [browser, pest, playwright, auth, ignored-discovery]
requires:
  - phase: 09-ignored-discovery-filters
    provides: ignored recovery UI, current copy, and recovery URL contract
  - phase: 13-refresh-search-and-navigation-browser-auth-proof
    provides: shared browser auth bootstrap and guard-cleanup pattern
provides:
  - ignored discovery browser proof enters through shared live auth for movies and series
  - current ignored-category and empty-view recovery copy assertions across desktop and mobile flows
  - deterministic selected-category recovery checks that stay on the active browse URL
affects: [phase-14, browser-tests, ignored-discovery]
tech-stack:
  added: []
  patterns: [shared browserLoginAndVisit entrypoints, guard cleanup after seeded preference patches]
key-files:
  created: []
  modified: [tests/Browser/IgnoredDiscoveryFiltersTest.php]
key-decisions:
  - "IgnoredDiscoveryFiltersTest now enters every movie and series proof through browserLoginAndVisit instead of actingAs-only browser visits."
  - "Seeded category preference PATCH setup clears auth guards, while mixed desktop/mobile checks reuse the authenticated browser session for follow-up visits."
patterns-established:
  - "Ignored recovery browser proof should pair recovery copy with same-path and category-query assertions before and after targeted unignore actions."
  - "Shared live auth plus plain authenticated revisits keeps combined desktop/mobile browser proofs deterministic within one test."
requirements-completed: [IGNR-01, IGNR-02]
duration: 2h 49m
completed: 2026-03-25
---

# Phase 14 Plan 01: Refresh Ignored Discovery Browser Recovery Proof Summary

**Ignored discovery browser proof now uses the shared live login bootstrap and current movie/series recovery copy while keeping targeted unignore flows on the same browse URL.**

## Performance

- **Duration:** 2h 49m
- **Started:** 2026-03-25T11:46:23Z
- **Completed:** 2026-03-25T14:35:50Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- Moved every movie ignored-discovery browser entrypoint onto `browserLoginAndVisit` and guard-safe seeded preference setup.
- Refreshed series browser proof to use the same live auth bootstrap and current empty/recovery copy.
- Verified the full ignored discovery browser suite plus ignored feature coverage pass deterministically.

## task Commits

Each task was committed atomically:

1. **task 1: move movie ignored recovery proof onto shared live auth** - `14cae45` (test)
2. **task 2: refresh series recovery proof through shared live auth** - `f9c1719` (test)

**Plan metadata:** pending final docs commit

## Files Created/Modified
- `tests/Browser/IgnoredDiscoveryFiltersTest.php` - refreshes movie and series ignored recovery browser coverage around shared live auth, current copy, and same-URL assertions.

## Decisions Made
- Reused `browserLoginAndVisit` for every browser entrypoint instead of keeping any `actingAs`-only browse startup in this suite.
- Kept current movie and series page copy as the assertion source of truth and paired targeted recovery checks with browse path/query invariants.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Reused the authenticated browser session for the movie desktop/mobile affordance proof**
- **Found during:** task 1 (move movie ignored recovery proof onto shared live auth)
- **Issue:** logging in a second time inside the same browser test hit the already-authenticated redirect path and timed out on the login copy assertion.
- **Fix:** kept the shared live-auth entrypoint for the test, then reused the authenticated session with a follow-up visit for the mobile half of the proof.
- **Files modified:** `tests/Browser/IgnoredDiscoveryFiltersTest.php`
- **Verification:** `php -l tests/Browser/IgnoredDiscoveryFiltersTest.php && ./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php --filter="movie" --stop-on-failure`
- **Committed in:** `14cae45`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** The fix stayed inside the browser harness and was required for deterministic mixed desktop/mobile proof.

## Issues Encountered
- The first series browser-subset verification timed out on the shared login page once, but an immediate retry passed and the final full-suite verification was green.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 14 ignored discovery browser recovery proof is aligned with the shared auth helper and current shipped copy.
- Phase 15 can refresh detail-page category browser proof on top of the same live-auth testing pattern.

## Self-Check: PASSED
- Found `.planning/phases/14-refresh-ignored-discovery-browser-recovery-proof/14-01-SUMMARY.md`.
- Verified commits `14cae45` and `f9c1719` in git history.
