# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-25)

**Core value:** Users can quickly find the right movie/series and reliably get their own downloads with correct permissions and automation.
**Current focus:** Phase 5 (Download Lifecycle Reliability)

## Current Position

Phase: 5 of 7 (Download Lifecycle Reliability)
Plan: 0 of TBD in current phase
Status: Ready to plan
Last activity: 2026-02-26 - Phase 4 verified complete

Progress: [██████░░░░] 57%

## Performance Metrics

- Total plans completed: 19
- Average duration: 5.1 min
- Total execution time: 1.60 hours

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 1 (Access Control) | 5 | 40 min | 8 min |
| 2 (Download Ownership & Authorization) | 5 | 19 min | 3.8 min |
| 3 (Categories Sync & Categorization Correctness) | 4 | 13 min | 3.2 min |
| 4 (Category Browse/Filter UX) | 5 | 24 min | 4.8 min |

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
| 2 | Represent admin multi-owner filtering in a normalized `owners` query string | Keep chips/search filter state stable, shareable, and deterministic |
| 2 | Forward retry `return_to` only for safe relative `/downloads` paths | Preserve filtered context after actions while blocking unsafe redirect targets |
| 3 | Persist separate VOD and Series Uncategorized categories as system rows with stable provider IDs | Keep uncategorized remap behavior deterministic across re-sync cycles |
| 3 | Use enum-backed sync run statuses (`running`, `success`, `success_with_warnings`, `failed`) in history model | Provide a stable contract for sync logic and admin history UI |
| 3 | Standardize media DTO `category_id` as nullable string | Align contracts with provider category ID identity semantics |
| 3 | Accept only list-shaped JSON payloads in Xtream category requests (`array_is_list`) | Prevent object-shaped responses from being misinterpreted as category collections |
| 3 | Run global hard-delete cleanup only when both VOD and Series sources are apply-safe | Prevent destructive category removal from incomplete source data |
| 3 | Require explicit force-empty flags before applying empty source payloads | Guard against accidental destructive syncs on provider zero-category responses |
| 3 | Serialize SyncCategories job execution via cache lock and release-on-busy retry | Preserve queued dispatches while enforcing single active sync run |
| 3 | Preflight VOD+Series category fetches in settings sync controller and block unforced empty-source dispatches | Prevent destructive empty-sync runs unless admin explicitly confirms source-specific force |
| 3 | Re-submit sync confirmation with per-source force flags only for empty sources | Keep confirmation precise and avoid altering behavior for non-empty sources |
| 3 | Expose category sync run history in settings with pagination and top issues | Make sync outcomes auditable without CLI access |
| 4 | Use one shared sidebar builder action keyed by MediaType for Movies and Series | Keep category-sidebar behavior consistent and avoid duplicated query/count logic |
| 4 | Count uncategorized media as null, empty string, or system uncategorized provider ID | Preserve accurate uncategorized counts during category normalization transition |
| 4 | Normalize blank/whitespace Movies `category` query values to null (All) | Keep URL filter semantics deterministic and avoid treating empty input as invalid category |
| 4 | Validate Movies category IDs against `in_vod` provider IDs and redirect invalid IDs with warning flash | Prevent cross-dataset/missing-category states while preserving user feedback and fallback behavior |
| 4 | Validate Series category IDs against `in_series` provider IDs and redirect invalid IDs with warning flash | Keep URL-driven Series filter state safe and deterministic when stale/invalid IDs are requested |
| 4 | Preserve selected Series category across pagination links using `withQueryString()` | Ensure back/forward/next-page navigation retains active category context |
| 4 | Keep category sidebar/sheet UI state outside keyed results subtree while remounting results by `filters.category` | Preserve mobile sheet openness across category switches while resetting per-category infinite-scroll state |
| 4 | Use `router.reload({ only: ['movies|series','filters','categories'] })` for Retry categories | Retry against current URL-selected category without synthesizing a new navigation target |
| 4 | Accept orchestrator-provided approval as the completion signal for the manual smoke checkpoint in continuation mode | Non-interactive checkpoint continuation already carried explicit approval state |

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-02-26T02:09:12Z
Stopped at: Phase 4 verified complete; ready for Phase 5 planning
Resume file: None
