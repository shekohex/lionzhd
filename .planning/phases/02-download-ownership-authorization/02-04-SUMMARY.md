---
phase: 02-download-ownership-authorization
plan: 04
subsystem: ui
tags: [react, inertia, downloads, authorization, ownership]

requires:
  - phase: 02-01
    provides: Download ownership fields and DTO owner metadata
  - phase: 02-02
    provides: Ownership-aware server-download redirects and dedupe behavior
  - phase: 02-03
    provides: Server-side own-only/member-vs-admin authorization boundaries
provides:
  - Internal members can trigger server-download from movie/series pages
  - Internal members can operate downloads from /downloads while external members remain read-only
  - Admin rows show owner token metadata with subtle Mine marker
  - Cancel/retry actions require owner/title/action confirmation
  - Stale highlighted gid query values self-heal with a scope toast and URL cleanup
affects: [02-05, downloads-ux, authorization-ux]

tech-stack:
  added: []
  patterns:
    - Role capability flags drive action enablement in downloads UI
    - Admin-only owner metadata rendering with member-safe visibility
    - Query-param self-heal for stale deep-links after ownership scoping

key-files:
  created: []
  modified:
    - resources/js/pages/downloads.tsx
    - resources/js/pages/movies/show.tsx
    - resources/js/pages/series/show.tsx
    - resources/js/components/download-info.tsx

key-decisions:
  - "Use canOperate = isAdmin || isInternalMember for downloads actions"
  - "Keep owner metadata visible only to admins while still marking Mine subtly"
  - "Clear stale gid query param client-side with informational toast instead of HTTP interception"

patterns-established:
  - "Downloads page action handlers gate once via canOperate and preserve URL context for PATCH"
  - "Cancel/retry flows always require explicit owner-aware confirmation"

duration: 2 min
completed: 2026-02-25
---

# Phase 2 Plan 04: Downloads Ownership UX Alignment Summary

**Downloads UI now aligns with ownership rules: internal members can act, external members stay read-only, admins see owner context, and stale highlighted gids self-heal.**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-25T06:59:40Z
- **Completed:** 2026-02-25T07:02:24Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Enabled internal members for server-download entry points on movie/series pages and downloads operations on `/downloads`.
- Added admin ownership affordances (owner token + subtle Mine marker) and owner-aware confirmation dialogs for cancel/retry.
- Added stale `gid` self-heal: toast informs user and URL automatically drops inaccessible highlight while preserving other filters.

## Task Commits

Each task was committed atomically:

1. **Task 1: Enable server-download UI for internal members; keep external restricted** - `b38029e` (feat)
2. **Task 2: Show owner badge for admins + Mine marker + confirm dialogs for cancel/retry** - `a5eeed9` (feat)
3. **Task 3: Toast + self-heal when highlighted download is no longer in scope** - `ed151cc` (feat)

**Plan metadata:** pending docs(02-04) commit

## Files Created/Modified
- `resources/js/pages/downloads.tsx` - Added capability flags (`isInternalMember`, `canOperate`), admin context props, and stale `gid` self-heal toast/query cleanup.
- `resources/js/pages/movies/show.tsx` - Server-download visibility now enables for admin + internal members and disables for external.
- `resources/js/pages/series/show.tsx` - Server-download visibility now enables for admin + internal members and disables for external.
- `resources/js/components/download-info.tsx` - Added admin owner badge + Mine marker and confirmation gating for cancel/retry actions.

## Decisions Made
- Consolidated downloads action authorization into a single `canOperate` UI capability flag for role/subtype clarity.
- Kept ownership metadata hidden from members while exposing admin row context with explicit owner token and Mine marker.
- Implemented stale highlight recovery in client query-state (`gid`) to handle scoped list removals without extra server error paths.

## Deviations from Plan

None - plan executed exactly as written.

## Authentication Gates

None.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- UI behavior now matches Phase 2 authorization boundaries for member/admin roles.
- Ready for `02-05-PLAN.md` (admin owner filtering + verification).
- No blockers or concerns.

---
*Phase: 02-download-ownership-authorization*
*Completed: 2026-02-25*
