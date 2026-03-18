# Phase 9: Ignored Discovery Filters - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can ignore movie or series categories per media type so matching titles disappear from that user's movie/series catalog listings, while recovery stays available when hidden or ignored preferences leave navigation or catalog results empty. This phase covers catalog discovery only, not search, detail-page access, watchlist access, or admin access.

</domain>

<decisions>
## Implementation Decisions

### Ignore controls
- Ignore stays browse-attached, not in a separate settings flow.
- Desktop browse rows expose ignore as a quick row action alongside the existing pin/hide actions.
- Mobile keeps ignore inside the existing category-sheet manage view rather than adding more controls to the browse picker.
- Hide and ignore remain separate controls with separate meanings.

### Ignored navigation treatment
- Ignored categories remain visible in navigation rather than moving to a hidden-only recovery surface.
- Ignored categories stay selectable from the main browse list.
- Ignored categories use a muted visual treatment, not badge-heavy styling.
- Ignored categories sort to the bottom of the main visible list instead of preserving their prior position.

### Ignored direct URL behavior
- If a user lands on an ignored category URL, keep them on that category instead of redirecting to `All categories`.
- Ignored-category URLs show a recovery state on that category rather than showing ignored titles.
- The ignored category remains selected in navigation while that recovery state is shown.
- If the user unignores from that recovery state, the same category page should immediately restore its normal results.

### Empty-state recovery
- On an ignored category page with no results, the primary recovery action is to unignore that category.
- If `All categories` is empty because ignores filtered everything out, the main recovery action is to open/manage ignored categories.
- When hidden and ignored preferences both contribute to emptiness, the message should explicitly mention both causes.
- Full reset is available as a secondary escape hatch, not the primary recovery action.

### OpenCode's Discretion
- Exact copy for ignore actions, recovery messaging, and mixed hidden/ignored empty states.
- Exact muted styling and iconography for ignored rows as long as they remain visibly distinct from normal and hidden categories.
- Exact placement/presentation of the secondary full-reset action within browse/manage recovery surfaces.

</decisions>

<specifics>
## Specific Ideas

- Recovery should stay browse-first and in-context rather than sending users to a separate preferences area.
- Ignored categories should remain easy to recover without collapsing the main navigation into a hidden-only management flow.
- Granular recovery is preferred over heavy-handed reset flows.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Actions/BuildPersonalizedCategorySidebar.php`: existing per-user sidebar builder already handles visible/hidden group assembly, selected hidden metadata, pin limits, and reset availability.
- `app/Models/UserCategoryPreference.php`: current user-scoped, media-type-scoped preference model backing pin/hide/order behavior.
- `app/Http/Controllers/Preferences/CategoryPreferenceController.php`: existing preference update/reset endpoints already sit in the browse flow.
- `app/Actions/SaveUserCategoryPreferences.php`: current transactional persistence path for category preference snapshots.
- `resources/js/components/category-sidebar.tsx`: shared desktop sidebar + mobile sheet shell with browse/manage modes.
- `resources/js/components/category-sidebar/browse.tsx`: existing hover/tap row-action pattern for pin/hide in browse mode.
- `resources/js/components/category-sidebar/manage.tsx`: existing heavier mobile/desktop management surface for recoverable category state.
- `resources/js/hooks/use-category-browser.ts`: shared Inertia partial-reload pattern for category selection and preference mutations.
- `resources/js/components/empty-state.tsx`: reusable empty/recovery component already used on browse pages.
- `tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php` and `tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php`: regression base for user-scoped preference reads, hidden-category URLs, and reset isolation.

### Established Patterns
- Movie and series behavior should stay aligned while keeping preferences fully isolated per media type.
- `All categories` stays fixed at the top and `Uncategorized` stays fixed at the bottom.
- Category selection stays URL-driven, with partial Inertia reloads preserving the current browse flow.
- Hidden selected categories already keep their direct URLs readable with a banner; ignored-category behavior is intentionally different in this phase.
- Preference changes save immediately inside browse-attached UI instead of sending users through a separate settings workflow.

### Integration Points
- `app/Http/Controllers/VodStream/VodStreamController.php`: movie catalog read path where ignored-category exclusion and recovery metadata must be applied.
- `app/Http/Controllers/Series/SeriesController.php`: series catalog read path that must mirror movie ignored-filter behavior.
- `resources/js/pages/movies/index.tsx`: existing hidden-category banner, empty-state, and sidebar wiring for movie recovery UX.
- `resources/js/pages/series/index.tsx`: series-page counterpart that should stay behaviorally consistent.
- `app/Data/CategorySidebarData.php` and `app/Data/CategoryBrowseFiltersData.php`: DTO surfaces that currently carry sidebar state and selected category info back to the frontend.

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 09-ignored-discovery-filters*
*Context gathered: 2026-03-18*
