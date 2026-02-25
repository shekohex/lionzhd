---
phase: 02-download-ownership-authorization
plan: 01
subsystem: database
tags: [laravel, eloquent, migration, download-ownership, spatie-data, typescript]

requires:
  - phase: 01-access-control
    provides: role/subtype gates and restricted download capability boundaries from phase 1
provides:
  - Nullable `media_download_refs.user_id` persistence contract with FK to users
  - `MediaDownloadRef` owner relation and ownership-aware constructors for future caller wiring
  - Typed owner metadata DTO fields in downloads payload contracts for admin ownership visibility
affects: [phase-2-plan-02-download-create-ownership, phase-2-plan-03-download-authorization-enforcement, phase-2-plan-04-downloads-ui-ownership]

tech-stack:
  added: []
  patterns:
    - Persist ownership at reference level with nullable FK first, then enforce stricter flows in later plans
    - Compose nested owner preview DTOs into download payloads while preserving existing media type normalization

key-files:
  created:
    - database/migrations/2026_02_25_000002_add_user_id_to_media_download_refs_table.php
    - app/Data/MediaDownloadOwnerData.php
  modified:
    - app/Models/MediaDownloadRef.php
    - app/Data/MediaDownloadRefData.php
    - resources/js/types/generated.d.ts

key-decisions:
  - Accept `User|int|null` in MediaDownloadRef static constructors so ownership assignment can be call-site friendly without breaking current callers.
  - Keep `user_id` nullable in this phase to preserve legacy download rows and avoid migration/backfill risk.

patterns-established:
  - Download ownership additions ship as schema + model + DTO contract together to keep backend and TS payloads aligned.

duration: 3 min
completed: 2026-02-25
---

# Phase 2 Plan 1: Download ownership persistence contract summary

**Download refs now persist nullable owner IDs with an owner relation and typed owner DTO payloads for upcoming own-only enforcement and admin visibility work.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-25T06:42:48Z
- **Completed:** 2026-02-25T06:46:14Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments
- Added a new migration introducing nullable `media_download_refs.user_id` with a constrained foreign key to `users`.
- Updated `MediaDownloadRef` to persist `user_id`, expose an `owner()` relation, and accept optional ownership args in static constructors.
- Added `MediaDownloadOwnerData` and extended `MediaDownloadRefData`/generated TS types with `user_id` and optional `owner` while preserving media type normalization.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add media_download_refs.user_id (nullable) with FK + index** - `2fedd8d` (feat)
2. **Task 2: Add owner relation + user_id support to MediaDownloadRef** - `f9ef2a9` (feat)
3. **Task 3: Extend downloads DTOs to carry owner metadata (typed)** - `ef2a1fd` (feat)

## Files Created/Modified
- `database/migrations/2026_02_25_000002_add_user_id_to_media_download_refs_table.php` - Adds nullable ownership FK column for download refs.
- `app/Models/MediaDownloadRef.php` - Adds `user_id` fillable support, `owner()` relation, and optional owner constructor inputs.
- `app/Data/MediaDownloadOwnerData.php` - Defines typed owner preview contract for frontend payloads.
- `app/Data/MediaDownloadRefData.php` - Adds `user_id` and optional nested `owner` payload fields.
- `resources/js/types/generated.d.ts` - Regenerated frontend types with new owner DTO contracts.

## Decisions Made
- Static constructors accept `User|int|null` owner input to simplify future controller wiring without forcing immediate call-site refactors.
- Ownership persistence remains nullable in this plan to keep pre-existing rows valid and unblock phased rollout.

## Deviations from Plan

None - plan executed exactly as written.

## Authentication Gates

None.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Ownership schema/model/DTO contract is in place for assigning owners at download creation.
- Ready for `02-02-PLAN.md` to wire ownership into creation flows and scoped dedupe behavior.

---
*Phase: 02-download-ownership-authorization*
*Completed: 2026-02-25*
