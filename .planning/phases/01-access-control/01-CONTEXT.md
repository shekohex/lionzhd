# Phase 1: Access Control - Context

**Gathered:** 2026-02-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Deliver role + access boundaries across UI and APIs:
- First registered user is Admin; subsequent users are Members by default.
- Admin can mark Members as Internal or External.
- Members cannot access admin-only areas (user management, system settings, sync/import controls, download operations, analytics/monitoring).
- External Members are direct-link only and cannot use server-download actions.
- External Members cannot configure or run auto-download schedules.

Out of scope for this phase: download ownership/authorization (Phase 2), categories sync/browse (Phases 3-4), download lifecycle reliability (Phase 5), mobile pagination (Phase 6), auto-episodes feature itself (Phase 7).

</domain>

<decisions>
## Implementation Decisions

### Role bootstrap
- Signup model: open registration.
- First-ever registered user becomes Admin.
- System must not allow a zero-admin state via UI: prevent deleting/demoting the last Admin.
- New users default: `Member` + `External`.
- Internal/External subtypes do not apply to Admin accounts.
- Users see a header badge showing role + subtype when relevant:
  - Show badge for Admin.
  - Show badge for External Members.
  - No badge for Internal Members.
- Role/subtype changes take effect immediately (no re-login required).

### User management
- System supports multiple Admins.
- Super-admin model:
  - The first Admin is the initial super-admin.
  - Only the super-admin can promote/demote Admin.
  - Super-admin status is transferable (explicit transfer).
  - While someone is super-admin, they are protected from demotion/deletion via UI.
  - After transfer, the old super-admin can be demoted to Member.
  - UI should explicitly label who is super-admin.
- Any Admin (not only super-admin) can change a Member's subtype (Internal/External).
- Internal/External is editable inline via toggle on the Users list.
- Members cannot change their own subtype.
- Users list: minimal fields only (name/email + role + subtype).
- Confirmations: require confirmation for both role changes and subtype toggles.
- When changing a user Internal -> External, do not force logout; restrictions apply without forced sign-out.

### External restrictions
- External Members are allowed to use direct-download links; direct-download is the primary allowed path.
- Direct download UX:
  - Available on content detail pages only.
  - Click opens in a new tab.
- Server-download actions for External Members:
  - Visible but disabled with explanation.
  - If triggered (e.g. via stale UI), show a toast.
  - Toast messaging should be specific + guiding (mention direct link and contacting admin).
- Downloads section for External Members:
  - Downloads page is visible but read-only.
  - Existing server-side downloads remain visible read-only.
  - Operations (pause/cancel/retry/etc) appear disabled with explanation.
- Schedule-related UI (now or future): visible but disabled with explanation for External Members.
- Header badge should be clickable and open a help modal explaining External limitations.

### Unauthorized UX
- If a Member navigates to an admin-only page URL directly: show a 403 page.
- Admin-only navigation items are hidden for non-admins.
- Unauthorized messaging is explicit about the permission required (e.g. Admin-only / Internal-only).
- If an Admin is demoted while currently on an admin page: allow the page to remain until the next action; then apply unauthorized handling.
- 403 page presentation:
  - Render within the normal app shell.
  - Show specific reason.
  - Provide Back/Home actions.
  - Include a “contact admin” instruction (text only; no button/link).

### Contact admin messaging
- The UI should not show emails.
- Messaging should instruct users to contact the super-admin (as plain text).

### OpenCode's Discretion
- Exact copywriting (wording) for badges, toasts, help modal, and 403 page (within the constraints above).
- Exact placement of the header badge and help modal trigger.

</decisions>

<specifics>
## Specific Ideas

- This is a team app: “contact admin” should be in-message text (no clickable contact UI).

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within Phase 1 scope.

</deferred>

---

*Phase: 01-access-control*
*Context gathered: 2026-02-25*
