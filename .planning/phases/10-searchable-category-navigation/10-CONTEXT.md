# Phase 10: Searchable Category Navigation - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Add category search inside the existing movies and series navigation on desktop and mobile so users can quickly jump to a category. This phase stays inside current navigation surfaces and browse flows; it does not add new discovery capabilities or change shared taxonomy behavior.

</domain>

<decisions>
## Implementation Decisions

### Search scope
- Search includes normal visible categories and ignored-visible categories.
- Hidden categories stay out of search results because hidden remains a recovery-only surface.
- `All categories` is fixed only for normal browse and should be hidden while a search query is active.
- `Uncategorized` remains part of search results and should stay anchored at the bottom when it matches.
- Search should feel fuzzy rather than strict exact/prefix-only matching.

### Search interaction
- Search is inline inside the existing navigation UI, not a separate page or detached modal flow.
- On desktop, the search field sits directly below the sidebar title.
- Results update live while the user types.
- Filtered results should rank top hits first rather than preserving the normal pinned/visible/ignored grouping.
- When search is active, show match results only; do not keep the currently selected non-matching category pinned into the filtered list.
- Search result presentation should be search-first, with bold matched text and keyboard navigation support on desktop (`Arrow` keys + `Enter`).

### No-match state
- No-match UI should use guided recovery rather than a bare empty message.
- The primary recovery action is `Clear search`.
- No-match copy should stay simple and should not bring hidden-category concepts into normal search messaging.
- Search query state resets each time the user leaves and reopens the navigation surface.

### Mobile flow
- Mobile search lives at the top of the existing category sheet.
- Opening the mobile sheet in browse mode should not auto-focus the search field.
- Selecting a category from mobile search closes the sheet and jumps to that category.
- Search should also be available in mobile manage mode, not just browse mode.

### OpenCode's Discretion
- Exact fuzzy-ranking algorithm and whether the fuzzy lookup stays client-side or uses server assistance, as long as the UX remains live and fuzzy.
- Exact visual treatment for bold match highlighting, result emphasis, and keyboard-focus states.
- Exact copy for guided no-match messaging and any search affordance hints.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/js/components/category-sidebar.tsx`: shared desktop sidebar and mobile sheet shell with browse/manage mode switching, title/header areas, and current mobile close-on-select behavior.
- `resources/js/components/category-sidebar/browse.tsx`: current browse list renderer for `All categories`, pinned, visible, ignored, and `Uncategorized` rows; natural insertion point for inline search behavior.
- `resources/js/components/category-sidebar/manage.tsx`: existing management surface that now also needs search availability on mobile.
- `resources/js/components/ui/command.tsx`: existing `cmdk` primitives that can support keyboard navigation, ranked results, or richer search interactions without introducing a new UI foundation.
- `resources/js/hooks/use-category-browser.ts`: central category selection and preference mutation hook shared by movies and series browse pages.
- `app/Data/CategorySidebarData.php` and `app/Data/CategorySidebarItemData.php`: backend DTO surfaces that already shape sidebar payloads for search filtering metadata if needed.
- `tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php` and `tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php`: regression base for personalized/ignored category payload behavior.

### Established Patterns
- Category selection stays URL-driven with partial Inertia reloads through the shared browse hook.
- `All categories` stays fixed at the top and `Uncategorized` stays fixed at the bottom outside active search.
- Ignored categories remain visible/selectable in navigation, while hidden categories stay separate as recovery/manage state.
- Movies and series share the same sidebar component and interaction model, while preference data stays isolated per media type.
- Category management remains browse-attached rather than moving to a separate settings area.

### Integration Points
- `resources/js/components/category-sidebar.tsx`: desktop title/header area and mobile `SheetHeader` are the main UI attachment points for search inputs.
- `resources/js/components/category-sidebar/browse.tsx`: main render path where filtered result ordering and no-match state will plug in.
- `resources/js/components/category-sidebar/manage.tsx`: mobile manage-mode entry point that needs search access per the gathered decisions.
- `resources/js/pages/movies/index.tsx` and `resources/js/pages/series/index.tsx`: page-level consumers that already pass sidebar props and should remain behaviorally aligned.
- `app/Http/Controllers/VodStream/VodStreamController.php` and `app/Http/Controllers/Series/SeriesController.php`: read-path controllers that provide category payloads and may need search-support metadata if the chosen implementation requires it.

</code_context>

<specifics>
## Specific Ideas

- Search should feel fuzzy and forgiving, not strict exact matching.
- Top hits should rise to the top during search instead of preserving the normal category grouping layout.
- Result styling should feel search-first, with bold match emphasis rather than heavy category-state treatment.
- Server-backed search is acceptable if needed, but the locked requirement is the fuzzy/live UX rather than a specific implementation.

</specifics>

<deferred>
## Deferred Ideas

None - discussion stayed within phase scope.

</deferred>

---

*Phase: 10-searchable-category-navigation*
*Context gathered: 2026-03-19*
