---
phase: 11-correct-search-mode-ux
plan: 02
subsystem: ui
tags: [search, inertia, react, tabs, browser]
requires:
  - phase: 11-correct-search-mode-ux
    provides: canonical /search params, normalized query handling, and browser scaffolding from 11-01
provides:
  - page-owned draft query state for full-page /search
  - segmented All/Movies/TV Series mode tabs backed by committed URL visits
  - browser coverage for mode, sort, clear, submit, and history restoration sync
affects: [11-03, search-ui, browser-regressions]
tech-stack:
  added: []
  patterns: [page-owned draft query, route-built inertia search visits, query-param-level browser assertions]
key-files:
  created: []
  modified: [resources/js/components/search-input.tsx, resources/js/pages/search.tsx, tests/Browser/SearchModeUxTest.php]
key-decisions:
  - "Full-page /search now keeps draft query text local in the page while the URL and server props remain the committed source of truth."
  - "Committed search actions build one canonical search URL so mode, sort, clear, submit, and history restoration move together."
patterns-established:
  - "Search ownership pattern: controlled SearchInput for /search, autonomous autocomplete for lightweight search surfaces."
  - "Browser sync pattern: assert decoded query params plus history back/forward instead of brittle raw URL substrings."
requirements-completed: [SRCH-01, SRCH-04]
duration: 24 min
completed: 2026-03-21
---

# Phase 11 Plan 02: Move full-search state into the page and add segmented mode tabs Summary

**Page-owned `/search` draft state with segmented mode tabs, canonical committed visits, and browser-proven history restoration for committed search changes.**

## Performance

- **Duration:** 24 min
- **Started:** 2026-03-21T20:10:32Z
- **Completed:** 2026-03-21T20:34:54Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- Converted the shared search input so `/search` can control draft query text without autonomous full-page visits while lightweight autocomplete callers keep existing behavior.
- Replaced the media-type popover with segmented `All`, `Movies`, and `TV Series` tabs wired through one committed Inertia visit builder.
- Expanded browser coverage to prove typing stays draft-only while mode, sort, reset, submit, and history navigation stay aligned with canonical URL params.

## task Commits

Each task was committed atomically:

1. **task 1 RED: add failing draft query browser coverage** - `41d9ef5` (test)
2. **task 1 GREEN: make full-page search input page-controlled** - `ce9dbf1` (feat)
3. **task 2 RED: add failing mode-tab synchronization coverage** - `d4d15d6` (test)
4. **task 2 GREEN: replace search mode popover with tabs** - `bf28ac6` (feat)
5. **task 3: keep committed search history states restorable** - `fde911a` (fix)

**Plan metadata:** pending final docs commit

## Files Created/Modified
- `resources/js/components/search-input.tsx` - adds controlled-value support for `/search` while preserving lightweight autocomplete behavior elsewhere.
- `resources/js/pages/search.tsx` - owns draft query state, renders segmented mode tabs, and commits canonical search URLs for mode, sort, reset, and submit actions.
- `tests/Browser/SearchModeUxTest.php` - locks query-param, tab, sort, reset, submit, and history restoration behavior for committed `/search` states.

## Decisions Made
- Reused the shared `SearchInput` by adding controlled-page behavior instead of creating a search-page-only input component.
- Built committed search visits from a canonical route URL so empty-query resets clear stale params instead of inheriting the previous `q` value.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Forced browser search fixtures onto the Scout database driver**
- **Found during:** task 1 RED verification
- **Issue:** Browser search scenarios failed immediately because the default Meilisearch driver was unavailable in the test environment.
- **Fix:** Set `scout.driver` to `database` inside the search browser fixture setup.
- **Files modified:** `tests/Browser/SearchModeUxTest.php`
- **Verification:** RED and GREEN browser runs executed against seeded movie and series records without Meilisearch connectivity errors.
- **Committed in:** `41d9ef5`

**2. [Rule 1 - Bug] Fixed committed reset visits inheriting stale query params**
- **Found during:** task 3 browser coverage
- **Issue:** Clearing the committed search state emptied the visible input but left the previous `q` param in the URL/history.
- **Fix:** Built committed visits from a canonical `route('search.full', params)` URL and added an explicit reset action that clears the committed query through the same visit helper.
- **Files modified:** `resources/js/pages/search.tsx`, `tests/Browser/SearchModeUxTest.php`
- **Verification:** `./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="syncs mode tabs with url"` passes.
- **Committed in:** `fde911a`

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 bug)
**Impact on plan:** Both fixes were required to verify the planned UX correctly. No scope creep beyond committed search-state correctness.

## Issues Encountered
- The repo's installed Pest version does not support the plan's `-x` flag, so verification used filtered runs without it.
- Browser assertions exercise the built Vite bundle, so frontend asset builds were required before Playwright-backed verification.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Ready for `11-03-PLAN.md`; the page now has one committed search contract for tabs, sort, reset, submit, and history restoration.
- `tests/Browser/SearchModeUxTest.php::it renders filtered full width layout` remains deferred to 11-03 and is logged in `deferred-items.md`.

## Self-Check: PASSED

---
*Phase: 11-correct-search-mode-ux*
*Completed: 2026-03-21*
