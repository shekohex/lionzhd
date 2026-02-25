# Phase 4: Category Browse/Filter UX - Context

**Gathered:** 2026-02-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can browse and filter Movies and Series by category using sidebar-driven flows, including an explicit Uncategorized option, on the Movies and Series index pages.

</domain>

<decisions>
## Implementation Decisions

### Sidebar category model
- Baseline ordering is alphabetical (A-Z) on both Movies and Series.
- Include an explicit `All categories` option at the top.
- Place `Uncategorized` at the bottom.
- Do not show item counts.
- Show zero-item categories as disabled.
- Long category names are single-line truncated.
- Mobile category selection uses a slide-over panel.
- Tapping an already-selected category toggles back to `All`.

### Category selection flow
- Default state is `All categories`.
- URL is source of truth; no remembered state.
- Non-default category selections are encoded in query params; `All` removes category param.
- Use stable category IDs in URL values.
- If URL points to missing category, fallback to `All` with toast.
- If URL points to existing zero-item category, honor selection and show empty results.
- Category changes apply immediately, reset pagination to first page, and scroll to top.
- Browser back/forward should fully restore category/list state.
- Hard refresh with a category param restores that category.
- On mobile, selecting a category keeps the slide-over panel open.

### Filtered result states
- On category switch, replace current list with loading skeletons.
- Empty-category state should prompt user to try another category.
- Uncategorized empty state uses same pattern as other categories.
- Category-load failure shows inline error with retry.
- Retry should target current selected category.
- Disabled zero-item categories show tooltip reason (`No items currently`).
- If active category disappears after sync/refresh, fallback to `All` with toast.
- If category list is unavailable/empty, primary action is retry categories.

### Movies/Series consistency
- Keep behavior mostly the same on both pages; allow minor text differences.
- Treat category datasets independently per media type.
- Use the same `Uncategorized` label on both pages.
- Use media-specific sidebar titles (`Movie Categories`, `Series Categories`).
- Use media-specific empty-state CTA wording.
- Keep sorting and Uncategorized placement rules identical across both pages.
- Keep mobile category panel behavior identical on both pages.

### OpenCode's Discretion
- Whether category selection carries between Movies and Series navigation.

</decisions>

<specifics>
## Specific Ideas

- Sidebar categories should eventually support user-managed reorder and hide/exclude preferences (captured as deferred for this phase).

</specifics>

<deferred>
## Deferred Ideas

- User-managed category reorder in settings/preferences.
- User-managed hide/exclude category controls in settings/preferences.

</deferred>

---

*Phase: 04-category-browse-filter-ux*
*Context gathered: 2026-02-25*
