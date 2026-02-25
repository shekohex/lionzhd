# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-25)

**Core value:** Users can quickly find the right movie/series and reliably get their own downloads with correct permissions and automation.
**Current focus:** Phase 2 (Download Ownership & Authorization)

## Current Position

Phase: 2 of 7 (Download Ownership & Authorization)
Plan: 4 of 5 in current phase
Status: In progress
Last activity: 2026-02-25 - Completed 02-04-PLAN.md

Progress: [█████████░] 90%

## Performance Metrics

- Total plans completed: 9
- Average duration: 6 min
- Total execution time: 1.0 hours

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 1 (Access Control) | 5 | 40 min | 8 min |
| 2 (Download Ownership & Authorization) | 4 | 17 min | 4.25 min |

## Accumulated Context

### Decisions

| Phase | Decision | Rationale |
|-------|----------|-----------|
| 1 | Persist role/subtype/super-admin as non-null user fields with defaults | Establish stable authorization contract for all new and existing users |
| 1 | Keep subtype persisted for admins too | Preserve deterministic behavior on future admin demotion |
| 1 | Assign first-user bootstrap role inside registration transaction | Reduce concurrent registration race window |
| 1 | Centralize stable gate names in AppServiceProvider and enforce via can middleware | Keep authorization contract auditable and reusable across routes/features |
| 1 | Return explicit gate deny messages for UI-facing unauthorized reasons | Ensure forbidden UX communicates exact required permission |
| 1 | Render Inertia 403 page only for Inertia requests | Keep SPA UX consistent without altering non-Inertia response behavior |
| 1 | Enforce download restrictions via `can:server-download` and `can:download-operations` on route definitions | Guarantee ACCS-05 and admin-only download operations at server boundary even if UI is bypassed |
| 1 | Restrict admin promotion/demotion and super-admin transfer to super-admin while allowing all admins to toggle member subtype | Preserve governance boundaries while enabling routine subtype administration |
| 1 | Route users mutations through a single public update action with operation defaults | Keep endpoint separation and satisfy architecture constraints on controller public methods |
| 1 | Keep server-download actions visible but disabled for External members while hiding them for Internal members | Make boundary reasons explicit to restricted users without adding noise for unrestricted member paths |
| 1 | Show shell access badges only for Admin and External users | Surface permission-relevant identity state without clutter for Internal members |
| 2 | Accept User/int/null owner input in MediaDownloadRef constructors | Enable ownership assignment wiring without breaking existing constructor call sites |
| 2 | Keep `media_download_refs.user_id` nullable in the first ownership migration | Preserve legacy rows and allow phased enforcement in later phase-2 plans |
| 2 | Make `download-operations` model-aware and return `denyAsNotFound()` for member cross-user access | Enforce own-only operations at middleware boundary without leaking resource existence |
| 2 | Scope `/downloads` member queries by `user_id` and pass bound model via `can:download-operations,model` | Ensure own-only list + operation enforcement is server-side and route-level consistent |
| 2 | Accept optional `return_to` only for `/downloads` targets in server-download redirects | Preserve downloads context while preventing unsafe redirects |
| 2 | Scope member active-download dedupe by owner `user_id` while keeping admin dedupe global | Prevent cross-user active-download redirect leakage for members |
| 2 | Fail open on aria2 status hydration errors in downloads index | Keep downloads page available when aria2 RPC is temporarily unavailable |
| 2 | Use `canOperate = isAdmin || isInternalMember` in downloads UI | Keep member permissions aligned with Phase 2 server authorization contract |
| 2 | Show owner token metadata only to admins with a subtle Mine marker | Preserve member privacy while improving admin cross-user operational clarity |
| 2 | Self-heal stale `/downloads?gid=` highlights by clearing `gid` and toasting scope loss | Prevent broken deep-link states after ownership scoping without extra server error paths |

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-02-25T07:02:24Z
Stopped at: Completed 02-04-PLAN.md
Resume file: None
