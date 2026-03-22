# Phase 11: Correct Search Mode UX - Context

**Gathered:** 2026-03-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Make `/search` trustworthy across `all`, `movies`, and `series` modes so users can switch modes, see correct media-type results, get the right layout for filtered searches, and keep that state correct across refreshes, deep links, and back/forward navigation.

</domain>

<decisions>
## Implementation Decisions

### Search mode control
- Search mode uses segmented tabs for `All`, `Movies`, and `TV Series`.
- The mode control sits below the search box, not inside a secondary filter surface.
- Switching mode keeps the current typed query and immediately reruns the search in the chosen mode.
- The visible search field keeps `type:` magic words if the user types them; mode is also represented by explicit UI state.

### Filtered results layout
- `Movies` and `TV Series` modes replace the mixed-results layout with one full-width results section for the chosen media type.
- Filtered-mode cards should feel slightly roomier than the current mixed-results grid.
- The filtered results header should emphasize mode + result count + query together.
- Filtered mode should hide the other media type completely rather than showing cross-type counts or hints.

### URL and history behavior
- Canonical search URLs use dedicated params for mode and sort rather than relying on magic words alone.
- Changing the query or mode resets results back to page 1.
- Each committed search state should create its own browser history entry.
- Refresh, deep links, and back/forward should restore the exact search state represented by the URL: query, mode, sort, and page.

### Result and empty-state messaging
- Filtered-mode empty states should explicitly name the active media type.
- In filtered mode, the primary recovery path is editing or clearing the query rather than broadening to another mode.
- `All` mode should show a combined total while section headers reinforce per-type counts.
- Filtered-mode summary copy should use human labels such as `Movies only` and `TV Series only`.

### OpenCode's Discretion
- Exact tab styling, active-state treatment, and mobile presentation of the segmented control.
- Exact filtered-grid column counts and spacing, as long as filtered mode feels roomier than mixed mode.
- Exact copy wording for summaries and empty states within the decisions above.
- Exact placement of per-section counts inside the existing search results framing.

</decisions>

<specifics>
## Specific Ideas

- Keep the search field feeling like a normal text box while still allowing visible `type:` magic words for power users.
- Filtered searches should feel focused and trustworthy: one mode, one full-width section, no mixed-result distractions.
- Filtered summaries should read naturally, e.g. `Movies only` or `TV Series only`.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/js/pages/search.tsx`: existing search page with current filter controls, mixed movie/series sections, result summary, and empty state wiring.
- `resources/js/components/search-input.tsx`: shared search input surface already used for full-page and lightweight search flows.
- `resources/js/components/media-section.tsx`: current section wrapper for movie and series result blocks.
- `resources/js/components/media-card.tsx`: existing result card component used for poster-grid search results.
- `resources/js/components/empty-state.tsx`: reusable empty/recovery state component for no-result messaging.
- `resources/js/components/ui/pagination.tsx`: existing pagination component with Inertia partial reload support.
- `resources/js/hooks/use-query-params.tsx`: existing browser URL/back-forward sync helper available for search-state restoration.
- `app/Http/Controllers/SearchController.php`: main `/search` read path that currently branches between all/movie/series behavior.
- `app/Data/SearchMediaData.php`: typed search query DTO carrying `q`, `page`, `per_page`, `media_type`, and `sort_by`.
- `app/Actions/SearchMovies.php` and `app/Actions/SearchSeries.php`: shared backend search primitives for typed result retrieval.
- `tests/Feature/Controllers/SearchControllerTest.php`: regression base for current search response contracts.

### Established Patterns
- URL state is already the source of truth for browse flows, and refresh/back-forward restoration is a locked product expectation from earlier discovery phases.
- One shared search contract must power `all`, `movies`, and `series` modes rather than separate implementations per mode.
- Existing search UI is Inertia-driven and already uses partial reload patterns for pagination and result updates.
- Search results already use shared card-grid, section, and empty-state primitives that can be adapted instead of replaced.

### Integration Points
- `routes/web.php` search routes plus `app/Http/Controllers/SearchController.php` and `app/Data/SearchMediaData.php` define the canonical full-search contract.
- `resources/js/pages/search.tsx` is the page-level integration point for tabs, URL-sync behavior, filtered layout, and summary/empty-state messaging.
- `resources/js/components/search-input.tsx` is the input-level integration point for keeping typed query behavior aligned with mode changes.
- `resources/js/components/ui/pagination.tsx` and `resources/js/hooks/use-query-params.tsx` are the main client-side integration points for page/history correctness.
- `tests/Feature/Controllers/SearchControllerTest.php` is the natural place to lock the backend URL/filter contract, with browser coverage added where history behavior needs end-to-end verification.

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 11-correct-search-mode-ux*
*Context gathered: 2026-03-21*
