# Phase 11: Correct Search Mode UX - Research

**Researched:** 2026-03-21
**Domain:** Laravel + Inertia React search UX, URL state, and browser-history correctness
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

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

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SRCH-01 | User can switch search media type between all, movies, and series and see UI state stay in sync with the URL | Use explicit `media_type` query param, segmented tabs, and one committed Inertia GET contract for `q`, `media_type`, `sort_by`, `page`. |
| SRCH-02 | User sees only matching media-type results when search is filtered to movies or series | Normalize raw query once, drive backend filtering from explicit params, and render only the active media-type section in filtered mode. |
| SRCH-03 | User sees movie-only or series-only search results in a full-width result mode | Reuse `MediaSection`, `MediaCard`, `EmptyState`, and `Pagination`, but branch layout by mode so filtered modes render one roomier full-width grid. |
| SRCH-04 | User can refresh, deep-link, and use back or forward navigation without losing correct search mode behavior | Keep URL as the committed source of truth, reset `page` on query/mode changes, avoid section-only partial reloads on a shared `page`, and add browser coverage for refresh/history flows. |
</phase_requirements>

## Summary

Phase 11 should treat `/search` as a server-driven, URL-backed state machine with exactly four committed inputs: `q`, `media_type`, `sort_by`, and `page`. The current page does not meet that bar: `resources/js/pages/search.tsx` owns one form state, `resources/js/components/search-input.tsx` owns another, the full-page input auto-issues debounced GETs without carrying `media_type`, and filtered state is inferred partly from `type:` magic words. That is the main reason mode, results, URL, and refresh behavior can drift.

The safest implementation path is to keep Inertia GET visits as the only committed navigation mechanism for full search, not custom `pushState`, not the custom `useQueryParams` hook, and not `nuqs` shallow-false navigation for this page. Inertia already owns page props and browser history, and official docs confirm GET visits create history entries while partial reloads only work reliably on the same page component. For this phase, the planner should make the page own committed search state, keep the input text as a draft until submit or mode change, and normalize magic words once so the visible field can retain `type:` while backend queries still search the stripped base query.

Filtered mode should be a real mode, not a cosmetic badge. When `media_type=movie` or `media_type=series`, render one full-width section, one paginator, filtered summary copy, and filtered empty-state messaging. In `all`, keep the two-section layout, but treat `page` as one committed URL state; otherwise refresh/back-forward will not reproduce what the user saw.

**Primary recommendation:** Make Inertia GET + explicit query params the sole committed search contract, normalize magic words once, and branch search rendering into `all` vs single-media full-width modes.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^12.0 | Server route/controller contract for `/search` | Existing app foundation; current controller + paginator contract already lives here. |
| inertiajs/inertia-laravel + @inertiajs/react | ^2.0 / ^2.0.9 | URL-driven page visits, partial reloads, browser history, prop hydration | Officially supports GET visits, history entries, and same-page partial reloads; already used across the app. |
| react | ^19.1.0 | Search page UI state and rendering | Existing frontend runtime. |
| spatie/laravel-data | ^4.14 | Typed search filter DTO (`SearchMediaData`) | Existing typed contract for `q`, `page`, `per_page`, `media_type`, `sort_by`. |
| @radix-ui/react-tabs via `resources/js/components/ui/tabs.tsx` | ^1.1.11 | Accessible segmented tab control for mode switching | Already wrapped and already used by `resources/js/pages/watchlist.tsx`. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| laravel/scout | ^10.13 | Backend movie/series search execution | Keep current search actions; phase focuses on query normalization and UI contract, not search-engine replacement. |
| framer-motion | ^12.11.3 | Existing card-grid animation | Reuse existing motion wrappers if they do not fight filtered full-width layout. |
| pestphp/pest + pest-plugin-browser + playwright | ^4.4 / ^4.3 / 1.54.1 | Feature + browser regression coverage | Required to lock URL, refresh, and history behavior end-to-end. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Inertia GET visits for committed search state | `nuqs` with `shallow: false` | In this React SPA adapter, `shallow: false` triggers full-page navigation, not an Inertia partial visit; worse fit for this server-prop page. |
| Existing Tabs wrapper | Custom segmented control | Loses existing accessibility/keyboard behavior for no product gain. |
| Explicit `media_type` and `sort_by` params | Magic-word-only filtering | Breaks canonical deep links and keeps backend filtering coupled to raw text parsing. |
| One normalized search-state helper | Ad-hoc parsing in page/input/controller separately | Creates precedence drift between typed query, URL params, and backend execution. |

**Installation:**
```bash
# No new packages required for Phase 11
```

## Architecture Patterns

### Recommended Project Structure
```text
app/
├── Data/SearchMediaData.php                # canonical search filter DTO + normalization helpers
└── Http/Controllers/SearchController.php   # single `/search` read contract

resources/js/
├── pages/search.tsx                        # page-owned committed state, tabs, layout branching
├── components/search-input.tsx             # draft input/autocomplete surface adapted for full search
├── components/ui/tabs.tsx                  # segmented mode control primitive
└── components/ui/pagination.tsx            # URL-preserving paginator links

tests/
├── Feature/Controllers/SearchControllerTest.php  # canonical query/filter contract
└── Browser/SearchModeUxTest.php                  # refresh/back-forward/layout regressions
```

### Pattern 1: URL-backed committed search state
**What:** The committed state is the URL and server props, not local component memory. The page should commit via one Inertia GET carrying `q`, `media_type`, `sort_by`, and `page`.

**When to use:** Submit, mode change, sort change, clear, and pagination.

**Example:**
```tsx
import { router } from '@inertiajs/react'

router.get('/search', {
  q,
  media_type: nextMode,
  sort_by: nextSort,
  page: 1,
}, {
  preserveState: true,
  preserveScroll: false,
})
```

Source: https://inertiajs.com/manual-visits

### Pattern 2: Normalize raw query once, search with base query
**What:** Keep the visible raw query for UX, but derive a normalized base query and fallback parsed filters in one place. Explicit URL params win over parsed magic words; backend search should use the stripped base query.

**When to use:** Initial request, deep links, submit, mode switch, refresh.

**Example:**
```php
final class SearchMediaData extends Data
{
    public function normalizedQuery(): string
    {
        return trim(preg_replace('/\b(type|sort):[^\s]+/', '', $this->q ?? '') ?? '');
    }
}
```

Source basis: `app/Data/SearchMediaData.php`, `resources/js/pages/search.tsx`

### Pattern 3: Mode-aware composition, shared primitives
**What:** Keep existing primitives (`MediaSection`, `MediaCard`, `EmptyState`, `Pagination`), but branch rendering by mode: `all` = two sections, filtered = one section + roomier grid + filtered summary.

**When to use:** Every search render after the committed state resolves.

**Example:**
```tsx
<Tabs value={mode}>
  <TabsList>
    <TabsTrigger value="all">All</TabsTrigger>
    <TabsTrigger value="movie">Movies</TabsTrigger>
    <TabsTrigger value="series">TV Series</TabsTrigger>
  </TabsList>
</Tabs>
```

Source basis: `resources/js/components/ui/tabs.tsx`, `resources/js/pages/watchlist.tsx`

### Anti-Patterns to Avoid
- **Two independent full-search form states:** `search.tsx` and `search-input.tsx` currently drift; mode/search/sort must not be owned twice.
- **Section-only partial reloads with one shared `page`:** current search paginators only reload one section, but the backend contract has one `page`; refresh/back-forward can replay a different result set.
- **Magic words as the only filter contract:** keep them as power-user input, not the canonical state source.
- **History writes on every keystroke:** official Inertia docs warn about browser History API limits under frequent updates.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Browser history sync for committed search visits | Custom `window.history.pushState` / `popstate` orchestration | Inertia `router.get` / `Link` visits | Inertia already owns page props, browser history, and back-forward restoration for server-driven pages. |
| Segmented search-mode control | Custom pill/tab widget | Existing `Tabs`, `TabsList`, `TabsTrigger` wrapper | Accessibility and keyboard behavior already solved. |
| Query-string serialization for committed search state | Custom serializer hook for this page | Route helpers + existing paginator `withQueryString()` | Keeps server and client URLs aligned. |
| Full-width filtered result surface | New card/list system | Existing `MediaSection`, `MediaCard`, `EmptyState`, `Pagination` with mode-aware branching | Reuse avoids visual drift and unnecessary code churn. |
| Multi-place magic-word parsing | Separate regex logic in page, input, and controller | One normalization helper attached to the DTO or a dedicated search normalizer | Prevents precedence bugs and makes deep links deterministic. |

**Key insight:** The hard part here is not tabs or CSS; it is preventing three state systems (input draft, URL, server props) from diverging.

## Common Pitfalls

### Pitfall 1: Full-search input silently drops mode/sort
**What goes wrong:** `resources/js/components/search-input.tsx` initializes its own form with `q`, `page`, `per_page`, and hard-coded `sort_by: 'latest'`, but no `media_type`. Its debounced full-search GET can overwrite filtered state.

**Why it happens:** The page owns one form state while the shared input owns another.

**How to avoid:** Give the full search page one owner for committed state. The shared input can stay reusable, but its full-page usage must accept page-owned state or opt out of autonomous debounced GETs.

**Warning signs:** Tabs say `Movies`, URL says `media_type=movie`, but results or summary snap back to all/latest while typing.

### Pitfall 2: `type:` tokens leak into backend search terms
**What goes wrong:** Results become less trustworthy because `type:movie` or `sort:latest` is searched as literal text.

**Why it happens:** The current client parses magic words for UI only; the backend still searches raw `q`.

**How to avoid:** Strip magic words once for execution while preserving the raw field value for display.

**Warning signs:** Deep link with `q=alien%20type:movie` returns fewer/no results than the same query with `media_type=movie` alone.

### Pitfall 3: Shared page param, section-scoped partial reload
**What goes wrong:** User clicks the movie paginator in `all` mode, URL changes to `page=2`, movie results update, series results stay on page 1 until refresh/back-forward, then replay differently.

**Why it happens:** `SearchController` has one `page`, but current search page reloads only `movies` or only `series` props for pagination.

**How to avoid:** Treat `page` as one committed search state. In `all`, reload both result sets together; in filtered mode, render one paginator.

**Warning signs:** Refresh changes visible results without any other interaction.

### Pitfall 4: History pollution from draft typing
**What goes wrong:** Back button steps through half-typed searches or misses expected committed states.

**Why it happens:** Frequent URL/history writes during free typing.

**How to avoid:** Separate draft input from committed search; create history entries only for submit, mode/sort change, clear, and pagination.

**Warning signs:** Browser back requires many presses after one search session.

### Pitfall 5: Filtered mode still hints at the hidden media type
**What goes wrong:** User chooses `Movies` but still sees series counts/hints, reducing trust.

**Why it happens:** Existing `all`-mode summary and two-section layout are reused too literally.

**How to avoid:** Give filtered modes their own summary and empty-state copy, and hide the other media type completely.

**Warning signs:** Filtered pages still mention both media types above the fold.

## Code Examples

Verified patterns from official sources and existing repo code:

### Commit a search state with a GET visit
```tsx
import { router } from '@inertiajs/react'

router.get('/search', { q: 'alien', media_type: 'movie', sort_by: 'latest', page: 1 }, {
  preserveState: true,
  preserveScroll: false,
})
```

Source: https://inertiajs.com/manual-visits

### Reuse existing tab primitives for segmented mode UI
```tsx
<Tabs defaultValue={filter} className="w-full">
  <TabsList className="mb-8">
    <TabsTrigger value="all" asChild>
      <Link href={route('watchlist', { _query: { filter: 'all' } })} preserveState>
        All Items
      </Link>
    </TabsTrigger>
  </TabsList>
</Tabs>
```

Source: `resources/js/pages/watchlist.tsx#L113-L130`

### Same-page partial reloads are for same-component prop refreshes
```tsx
router.reload({ only: ['users'] })
```

Source: https://inertiajs.com/partial-reloads

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Popover filter + inferred mode badges | Explicit segmented tabs backed by `media_type` in the URL | Phase 11 decisions, 2026-03-21 | Mode becomes visible, shareable, and restorable. |
| Raw `q` doubles as both visible input and backend search term | Raw visible query + normalized execution query | Required for Phase 11 | Power-user tokens remain visible without corrupting search results. |
| Section-specific pagination on a shared `page` param | One committed page state per URL | Required for Phase 11 | Refresh/back-forward reproduce the same result set the user saw. |
| Autonomous full-page debounce navigation from `SearchInput` | Page-owned draft/commit split | Required for Phase 11 | Prevents history spam and state races. |

**Deprecated/outdated:**
- Magic words as the canonical filter contract.
- Full-search flows that let `SearchInput` issue GET requests without the current mode/sort.
- Filtered mode that still renders mixed-type framing.

## Open Questions

1. **Should full-page search keep autocomplete suggestions while typing?**
   - What we know: current autonomous debounce navigation is the main source of drift.
   - What's unclear: product expectation for live suggestions on the full page vs submit-only commit.
   - Recommendation: keep suggestions optional, but do not let suggestion fetches mutate committed URL/history until explicit submit or tab change.

2. **Should `all` mode keep two independent paginators visually?**
   - What we know: backend contract exposes one `page`, not one page per media type.
   - What's unclear: whether product wants one shared pager or two synchronized section pagers.
   - Recommendation: plan around one committed `page` and ensure both sections reload together; avoid pretending the sections page independently.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest ^4.4 + pest-plugin-browser ^4.3 + Playwright 1.54.1 |
| Config file | `tests/Pest.php`, `phpunit.xml` |
| Quick run command | `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php -x` |
| Full suite command | `./vendor/bin/pest && bun run test:browser` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SRCH-01 | Tabs switch between `all`, `movie`, `series`; URL and visible UI stay aligned | browser | `./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="syncs mode tabs with url" -x` | ❌ Wave 0 |
| SRCH-02 | Filtered mode returns only the chosen media type and hides the other type | feature + browser | `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php --filter="filtered search returns only chosen media type" -x` | ❌ Wave 0 |
| SRCH-03 | Filtered mode renders one full-width result surface with filtered summary/empty copy | browser | `./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="renders filtered full width layout" -x` | ❌ Wave 0 |
| SRCH-04 | Refresh, deep links, and back/forward restore `q`, `media_type`, `sort_by`, `page` exactly | browser | `./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" -x` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php -x`
- **Per wave merge:** `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php`
- **Phase gate:** `./vendor/bin/pest && bun run test:browser` green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Browser/SearchModeUxTest.php` — tabs, filtered layout, refresh, deep-link, and back/forward coverage for SRCH-01..04
- [ ] Extend `tests/Feature/Controllers/SearchControllerTest.php` — canonical `media_type`/`sort_by` params, filtered-only result assertions, and page-reset behavior
- [ ] Shared browser helpers for `window.history.back()`, `window.history.forward()`, and location polling on `/search`
- [ ] Explicit feature coverage for query normalization when raw `q` includes `type:` / `sort:` tokens

## Sources

### Primary (HIGH confidence)
- `resources/js/pages/search.tsx` — current full-search page structure, popover filters, mixed-section layout, and per-section pagination
- `resources/js/components/search-input.tsx` — current full-search input behavior and the second autonomous form state
- `app/Http/Controllers/SearchController.php` — current `/search` controller contract and single shared `page`
- `app/Data/SearchMediaData.php` — typed filter DTO and current canonical param names
- `resources/js/components/ui/tabs.tsx` — existing Radix tabs wrapper
- `resources/js/pages/watchlist.tsx#L113-L130` — existing tabs-as-navigation pattern
- `resources/js/app.tsx` — existing `NuqsAdapter` setup and adapter mode
- `tests/Feature/Controllers/SearchControllerTest.php` — current search response contract coverage
- https://inertiajs.com/manual-visits — GET visits, history behavior, `preserveState`, `replace`, and browser-history caveats
- https://inertiajs.com/partial-reloads — same-page partial reload contract
- https://nuqs.dev/docs/options — `history: 'push'`, `shallow`, and URL update semantics
- https://nuqs.dev/docs/adapters — React SPA adapter behavior; `shallow: false` can trigger full-page navigation

### Secondary (MEDIUM confidence)
- https://nuqs.dev/docs/batching — grouped query-param update semantics for keys that move together

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - all recommended libraries/primitives already exist in the repo and are documented.
- Architecture: MEDIUM - core direction is clear, but exact draft-vs-commit behavior still needs one implementation choice.
- Pitfalls: HIGH - directly visible in current code paths and controller/input interactions.

**Research date:** 2026-03-21
**Valid until:** 2026-04-20
