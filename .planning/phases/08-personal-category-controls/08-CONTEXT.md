# Phase 8: Personal Category Controls - Context

**Gathered:** 2026-03-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can personalize category navigation separately for movies and series with per-user reorder, pin, hide, and reset behavior, without mutating shared taxonomy or changing what other users see.

</domain>

<decisions>
## Implementation Decisions

### Management surface
- Personalization uses a browse-attached hybrid surface, not a settings-only flow.
- Daily editing happens in the existing category browse UI, with full controls available there.
- Category labels continue to navigate; edit actions use separate controls on each row.
- Row controls are revealed on hover/tap rather than always visible at full density.
- A browse-attached manage surface exists for heavier bulk management sessions.
- Reset-to-default lives inside that manage surface, per media type.

### Ordering and pinning rules
- Movies and series keep fully separate preference sets.
- Pinned categories stay above non-pinned categories.
- Users can manually order categories within the pinned group.
- Users can manually order categories within the non-pinned group.
- `All categories` stays fixed at the top and is not user-customizable.
- `Uncategorized` stays fixed at the bottom and is not user-customizable.
- Zero-item categories remain editable in place even if they stay disabled for navigation.
- Trying to pin a sixth category is blocked with a clear explanation; no automatic replacement.
- When a pinned category is unpinned, it returns to the user's manual non-pinned order.
- When sync introduces a new category later, it appears at the end of the non-pinned list by default.

### Hidden category recovery
- Hidden categories are removed from the main visible navigation list but remain available in a dedicated hidden section.
- If a user hides every visible category for a media type, show both a reset action and the hidden section so they can selectively recover.
- Hiding the category currently being viewed does not immediately force navigation away from the current results.
- Hidden-category direct URLs and history entries still render; the page should show a banner explaining that the category is hidden for this user.

### Mobile editing flow
- Mobile editing stays inside the existing category sheet rather than moving to a separate page.
- The mobile sheet includes a dedicated manage view for editing.
- Reordering on mobile uses drag handles in that manage view.
- Hidden categories appear in the same sheet inside a collapsed section.
- Mobile changes save instantly; closing the sheet is not the save step.

### OpenCode's Discretion
- Exact copy for pin-limit feedback, hidden-category banner text, and reset/recovery messaging.
- Exact visual treatment for hover/tap-revealed row controls on desktop.
- Exact presentation of the browse-attached bulk-management surface as long as it stays attached to the browse flow.

</decisions>

<specifics>
## Specific Ideas

- The experience should feel browse-first, with category management attached to discovery instead of buried in settings.
- Full controls stay available in browse for daily use, while a dedicated attached manage surface supports heavier cleanup/bulk edits.
- Mobile should not fork into a separate management destination; editing remains inside the existing sheet flow.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/js/components/category-sidebar.tsx`: existing shared movies/series category UI, including desktop sidebar and mobile sheet shell.
- `resources/js/components/ui/sheet.tsx`: existing mobile bottom-sheet pattern for category interactions.
- `resources/js/components/ui/command.tsx`: existing searchable list primitive that can support manage/recovery flows later.
- `resources/js/components/empty-state.tsx`: existing empty/retry state component for no-categories or recovery messaging.
- `app/Actions/BuildCategorySidebarItems.php`: current server-side category list builder with alphabetical ordering and uncategorized-last behavior.
- `app/Data/CategorySidebarItemData.php`: current category sidebar DTO shape that will need preference-related fields.

### Established Patterns
- Phase 4 browse behavior is already locked: `All categories` at top, `Uncategorized` at bottom, URL-driven category selection, and mobile sheet-based category picking.
- `resources/js/pages/movies/index.tsx` and `resources/js/pages/series/index.tsx` already use partial Inertia reloads, preserve state, and category-specific list restoration patterns.
- `app/Http/Controllers/VodStream/VodStreamController.php` and `app/Http/Controllers/Series/SeriesController.php` already build category props and validate browse category IDs per media type.
- Existing tests in `tests/Feature/Discovery/MoviesCategoryBrowseTest.php` and `tests/Feature/Discovery/SeriesCategoryBrowseTest.php` already cover baseline ordering, zero-item handling, and category URL behavior.

### Integration Points
- `resources/js/components/category-sidebar.tsx` is the main frontend integration point for browse-attached controls and the mobile manage flow.
- `resources/js/pages/movies/index.tsx` and `resources/js/pages/series/index.tsx` are the page-level integration points for personalization actions and recovery behavior.
- `app/Actions/BuildCategorySidebarItems.php` is the natural merge point for default category data plus per-user preferences.
- `app/Http/Controllers/VodStream/VodStreamController.php` and `app/Http/Controllers/Series/SeriesController.php` are the read-path entry points that must stay consistent across movies and series.

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 08-personal-category-controls*
*Context gathered: 2026-03-15*
