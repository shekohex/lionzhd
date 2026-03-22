# Phase 12: Detail Page Category Context - Context

**Gathered:** 2026-03-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Show assigned categories on movie and series detail pages so users keep category context when they land there from browse, search, or watchlist flows. This phase clarifies detail-page presentation and navigation only; it does not add new discovery capabilities beyond surfacing existing category assignments.

</domain>

<decisions>
## Implementation Decisions

### Placement and prominence
- Category context lives in the hero area on both movie and series detail pages.
- Categories render on their own chip row, separate from the existing genre chips.
- The row stays unlabeled; no visible `Categories` heading or icon is needed.
- Movie and series pages use the same placement pattern.

### Click-through behavior
- Category chips are clickable, not read-only.
- Clicking a chip opens the matching same-media browse page with that category selected.
- Category navigation stays in the same tab and follows normal in-app navigation.
- Hidden or ignored categories still navigate when clicked; the existing browse recovery/banner behavior handles those states.

### Ordering and state treatment
- Category chips use canonical synced category order, not alphabetical order and not the user's personalized navigation order.
- Hidden and ignored categories render neutrally like normal category chips.
- All assigned categories stay in one list; hidden or ignored categories are not grouped separately.
- Detail pages do not add extra copy, notes, or tooltips explaining hidden or ignored state.

### Overflow handling
- Desktop shows all assigned categories by default; chips wrap across lines as needed.
- There is no expander or `+N` collapse pattern by default.
- Mobile may use a more compact layout than desktop, but must still preserve access to the full assigned category set.
- Long category names should fit in the UI rather than truncate with ellipsis.

### OpenCode's Discretion
- Exact chip styling, spacing, and hover/focus treatment.
- Exact compact mobile layout, as long as it remains more space-efficient than desktop while preserving full category visibility.
- Exact animation/responsive details for the added hero row.

</decisions>

<specifics>
## Specific Ideas

- Keep categories hero-adjacent so users see them before scrolling.
- Keep genres and categories visually separate rather than merging them into one badge set.
- Treat hidden/ignored as browse concerns, not detail-page warnings.
- Favor direct, always-visible category context over collapsed or badge-count summaries.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/js/components/media-hero-section.tsx`: existing hero metadata and badge pattern; natural place to add the detail-page category row.
- `resources/js/components/ui/badge.tsx`: current chip primitive already used for genres and detail metadata.
- `resources/js/pages/movies/show.tsx` and `resources/js/pages/series/show.tsx`: page-level consumers that already pass hero props and can wire new category props consistently.
- `app/Data/CategorySidebarItemData.php`, `app/Data/CategorySidebarData.php`, and `app/Actions/BuildPersonalizedCategorySidebar.php`: existing category data/contracts that reflect current browse semantics and recovery rules.

### Established Patterns
- Hide and ignore remain discovery-only preferences; they must not suppress category context or block direct detail access.
- Movies and series should stay behaviorally aligned unless the user explicitly asks for divergence.
- Current detail pages already rely on shared hero-first presentation, with browse/search decisions flowing through canonical URL-driven read paths elsewhere in the app.
- Detail-page category context should come from local category mapping/canonical assignment data rather than mutating Xtream response DTOs to carry presentation-only state.

### Integration Points
- `app/Http/Controllers/VodStream/VodStreamController.php:217` and `app/Http/Controllers/Series/SeriesController.php:230`: show actions that need to provide category context props.
- `resources/js/types/movies.ts` and `resources/js/types/series.ts`: TypeScript page props that need category additions.
- `routes/web.php:45` and `routes/web.php:62`: existing detail routes whose browse destinations should stay aligned with chip click behavior.
- Browse controllers and category recovery UX already define how hidden/ignored category destinations behave after a chip click.

</code_context>

<deferred>
## Deferred Ideas

None - discussion stayed within phase scope.

</deferred>

---

*Phase: 12-detail-page-category-context*
*Context gathered: 2026-03-22*
