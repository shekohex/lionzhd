# Phase 6: Mobile Infinite-Scroll Pagination - Context

**Gathered:** 2026-02-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Make mobile infinite-scroll pagination on Movies + Series list pages correct and deterministic (no skipped/duplicated items across page boundaries), with regression tests covering the boundary behavior. Desktop keeps traditional pagination.

</domain>

<decisions>
## Implementation Decisions

### Load trigger + fallback
- Use hybrid infinite scroll on mobile: auto-load normally, with a "Load More" button only as a fallback after a load-more error.
- Auto-load triggers near bottom (early enough to hide latency, not only at the last pixel).
- Do not chain-load multiple pages automatically; load one page at a time.
- Idle bottom state (has more, not loading): show subtle hint text (e.g., "Scroll down for more...").
- On load-more failure: auto-retry once.
- After a load-more error: pause auto-loading until user explicitly retries.
- While a load-more request is in flight: ignore additional triggers (one request at a time).

### Back nav + scroll restore
- Opening a Movie/Series detail and navigating back restores exact scroll position and already-loaded items.
- Restoration persists within the session (not required across full reload).
- Restoration is per-category (each category, including "All", has its own remembered loaded state + position).
- Leaving the list via top navigation and returning later in the same session still restores.
- URL should update to reflect the latest reached `page` while infinite-scrolling.
- Shared/deep links like `/movies?category=X&page=3` should be respected on mobile.

### Category switch behavior
- Selecting a different category scrolls results to top smoothly.
- Category change always starts that category at page 1 (do not carry over `page=` from prior category).
- Switching back to a previously visited category restores exact scroll position and loaded items.
- "All categories" behaves like any other category for restore.
- Rapid switching: latest selection wins; ignore earlier in-flight loads.
- If loading a newly selected category fails: keep old results visible (do not switch to a broken/empty view).
- Load-more error state is tracked per-category.
- URL updates immediately to reflect the selected category.

### End + error states
- End of list: show an explicit end message.
- Load-more error (after one auto-retry): show inline at bottom.
- "Try again" retries load-more immediately.
- Already-loaded items remain visible and usable when load-more fails.

### OpenCode's Discretion
- Exact copy/wording for hint, end message, and error text (keep consistent with existing tone).

</decisions>

<specifics>
## Specific Ideas

- URL updates as the user infinite-scrolls; refresh/share should land at the same category + page.

</specifics>

<deferred>
## Deferred Ideas

None - discussion stayed within phase scope.

</deferred>

---

*Phase: 06-mobile-infinite-scroll-pagination*
*Context gathered: 2026-02-27*
