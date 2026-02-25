# Phase 2: Download Ownership & Authorization - Context

**Gathered:** 2026-02-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Make download records and download operations user-owned and authorization-correct across downloads pages and APIs. Members are constrained to their own downloads and actions; admins can view and operate across users; new downloads stay owned by the initiating user.

</domain>

<decisions>
## Implementation Decisions

### List scope rules
- Members are strict own-only across downloads pages and list endpoints (no cross-user rows or aggregate leakage).
- Admin downloads pages default to all users.
- Admin unfiltered list endpoints return all users.
- Mixed-owner admin lists use global newest-first ordering.
- Admin owner filtering supports multi-owner selection.
- Owner filter preference is not persisted; open on all-users each time.
- Summary counts reflect the current filtered scope.
- Include a one-click `My downloads` toggle for admins.

### Admin cross-user flow
- Primary admin workflow is owner chips plus owner search.
- Admin executes operations from inline row actions.
- Cancel/retry require confirmation showing owner, item title, and action.
- After actions, keep current filters/sort/page context unchanged.
- Clicking owner chips toggles owners in the active multi-owner filter set.
- Owner search matches username/display name and email.

### Ownership visibility
- Member views hide owner fields entirely.
- Admin list rows show owner as badge token style.
- Admin rows include a subtle `Mine` marker for downloads owned by the current admin.
- Ownership metadata appears in both list and details views.

### Unauthorized responses
- Unauthorized member read-by-ID requests return `404 Not Found`.
- If non-owned items appear in member UI (stale/shared paths), actions are disabled with reason.
- Unauthorized action feedback uses detailed ownership/permission messaging (not generic).
- When items are removed by authorization filtering, UI auto-refreshes and shows a brief toast.

### OpenCode's Discretion
- Exact copy text and visual styling of badges, toasts, and dialogs, as long as behavior above is preserved.

</decisions>

<specifics>
## Specific Ideas

No specific external product references were requested.

</specifics>

<deferred>
## Deferred Ideas

None - discussion stayed within phase scope.

</deferred>

---

*Phase: 02-download-ownership-authorization*
*Context gathered: 2026-02-25*
