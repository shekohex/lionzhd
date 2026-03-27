# Phase 16: Restore Search History State - Research

**Researched:** 2026-03-27
**Domain:** Laravel 12 + Inertia v2 `/search` history restoration and mixed-results pagination
**Confidence:** MEDIUM

<user_constraints>
## User Constraints

No `*-CONTEXT.md` exists for this phase. Use roadmap, requirements, state history, and current code as the source of truth.

### Locked Constraints
- Phase 16 goal: "Browser history rewind and forward navigation on `/search` restore the URL-authoritative mixed-results state, including pagination transitions."
- SRCH-04: "User can refresh, deep-link, and use back or forward navigation without losing correct search mode behavior."
- Out of scope milestone rule: "Separate search implementations for All, Movies, and Series | One shared search contract is required to avoid behavior drift."
- [Phase 11]: "SearchMediaData now preserves raw q for the UI while exposing normalized execution query and resolved media_type/sort_by helpers."
- [Phase 11]: "SearchController returns only the chosen media type in filtered mode and keeps explicit URL params authoritative over typed magic words."
- [Phase 11]: "Full-page /search now keeps draft query text local in the page while the URL and server props remain the committed source of truth."
- [Phase 11]: "Committed search actions build one canonical search URL so mode, sort, clear, submit, and history restoration move together."
- [Phase 11]: "All-mode search pagination now uses one shared committed URL so refresh and browser history replay both sections together."
- [Phase 13]: "Search follow-up visits and refreshes should stay on the authenticated page instance to keep live-auth history proof stable."
- [Phase 13]: "Search control helpers should target only tab/button controls and dispatch pointer events so Radix tabs commit URL state deterministically."

### OpenCode's Discretion
- Minimal code shape inside `resources/js/pages/search.tsx` and `tests/Browser/SearchModeUxTest.php`.
- Whether this phase is an app fix, a browser-proof hardening pass, or both, based on fresh reproduction.

### Deferred Ideas (OUT OF SCOPE)
- New search engines, new result layouts, or any redesign beyond restoring existing `/search` history correctness.
- Splitting mixed search into separate movie/series paginator query params.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SRCH-04 | User can refresh, deep-link, and use back or forward navigation without losing correct search mode behavior | Keep `/search` results fully prop-driven from Inertia history state, preserve one shared `page` URL in mixed mode, avoid remembered draft state, and verify with the existing browser scenario plus targeted reproduction of the audit gap. |
</phase_requirements>

## Summary

Current code already matches the intended Phase 16 architecture. `SearchController` has one canonical `/search` read contract, `SearchMediaData` normalizes execution state while preserving raw `q`, `SearchMovies` and `SearchSeries` emit paginator links with `withQueryString()`, and `resources/js/pages/search.tsx` renders mixed-mode results from page props with one shared paginator and no section-only partial reloads. Inertia v2 docs say browser history restores cached page props, while local component state is only restored when explicitly remembered. That means Phase 16 should keep results and pagination state in Inertia page props, not in custom client caches or `useRemember` state.

There is one important contradiction: the milestone audit recorded SRCH-04 as failing on 2026-03-27, but current local reproduction on this branch now passes both the exact targeted audit command and the broader browser audit command. That lowers confidence in the audit's failure as a currently reproducible app bug. The most likely remaining risk is either a stale audit result or a flaky browser assertion that waits for the URL change before the DOM fully rehydrates.

**Primary recommendation:** Plan this as a minimal hardening phase: re-prove the audit failure first, then keep `/search` result state fully Inertia-prop-driven and only change `search.tsx` or `SearchModeUxTest.php` where fresh reproduction shows real drift.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^12.0 | `/search` route, controller, paginator responses | Existing server contract and official paginator behavior. |
| inertiajs/inertia-laravel | ^2.0 | Server-side Inertia responses and history state | Official Laravel adapter already used by the app. |
| @inertiajs/react | ^2.0.9 | Client visits, links, browser-history replay | Existing client runtime for URL-authoritative search state. |
| react | ^19.1.0 | Search page local draft state and rendering | Existing frontend runtime. |
| pestphp/pest + pest-plugin-browser + playwright | ^4.4 / ^4.3 / 1.54.1 | Live browser proof for SRCH-04 | Existing E2E stack; already contains the failing-or-flaky scenario. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| spatie/laravel-data | ^4.14 | Typed `/search` filter DTO | Keep canonical parsing/normalization in one place. |
| laravel/scout | ^10.13 | Search backend abstraction | Keep current search execution; this phase is state restoration, not search engine work. |
| Laravel paginator | 12.x docs | Shared `page` query handling and link generation | Keep one mixed-mode `page` param and query-preserving pagination links. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Inertia GET visits and history cache | Custom `window.history` / `popstate` orchestration | Reimplements behavior Inertia already owns and makes history replay harder to trust. |
| One shared `page` param in mixed mode | Separate `movie_page` and `series_page` params | Laravel supports multiple paginator names, but that would violate the shipped "one shared search contract" decision for `/search`. |
| Plain local draft state synced from props | `useRemember` for full-page draft query | Would restore uncommitted draft text across history navigation and fight the URL-authoritative contract. |
| Existing `Pagination` + paginator links | Custom mixed-results pager | Unnecessary churn; current app already has the right primitive. |

**Installation:**
```bash
# No new packages required for Phase 16
```

## Architecture Patterns

### Recommended Project Structure
```text
app/
├── Data/SearchMediaData.php
├── Actions/SearchMovies.php
├── Actions/SearchSeries.php
└── Http/Controllers/SearchController.php

resources/js/
├── pages/search.tsx
└── components/ui/pagination.tsx

tests/
├── Browser/SearchModeUxTest.php
└── Feature/Controllers/SearchControllerTest.php
```

### Pattern 1: URL-backed committed `/search` state
**What:** The committed search state is the URL plus Inertia page props, not local result memory.

**When to use:** Submit, tab switch, sort change, clear, pagination, refresh, and back/forward.

**Example:**
```tsx
router.get(
    searchUrl,
    {},
    {
        preserveState: true,
        preserveScroll: false,
    },
)
```

Source: `resources/js/pages/search.tsx`, https://inertiajs.com/manual-visits

### Pattern 2: Mixed mode uses one shared page param
**What:** Movies and series move together on one committed `page` URL in `all` mode.

**When to use:** Mixed-results pagination and any history replay assertions.

**Example:**
```php
return Pipeline::send(VodStream::search($query))
    ->through($pipes)
    ->thenReturn()
    ->withQueryString();
```

Source: `app/Actions/SearchMovies.php`, `app/Actions/SearchSeries.php`, https://laravel.com/docs/12.x/pagination

### Pattern 3: Draft query is local, but re-seeded from committed props
**What:** Keep draft text local for typing, then resync it from committed `props.filters.q` after navigation/history replay.

**When to use:** Full-page `/search` only.

**Example:**
```tsx
const [draftQuery, setDraftQuery] = useState(props.filters.q ?? '')

useEffect(() => {
    setDraftQuery(props.filters.q ?? '')
}, [props.filters.q])
```

Source: `resources/js/pages/search.tsx`, https://inertiajs.com/remembering-state

### Pattern 4: Browser proof waits for state, not just URL
**What:** History assertions should verify both query-string rewind and restored visible result counts.

**When to use:** `SearchModeUxTest.php` after `history.back()` or `history.forward()`.

**Example:**
```php
searchModeUxHistoryBack($page);

expect(searchModeUxWaitForQueryParam($page, 'page', null))->toBeTrue();
expect(searchModeUxVisibleResultCount($page, 'Movies', '/movies/'))->toBe(5);
expect(searchModeUxVisibleResultCount($page, 'TV Series', '/series/'))->toBe(5);
```

Source: `tests/Browser/SearchModeUxTest.php`

### Anti-Patterns to Avoid
- **Custom history listeners on `/search`:** Inertia already restores cached page props on browser history navigation.
- **Separate movie and series pagers in mixed mode:** conflicts with the current single-URL search contract.
- **`useRemember` for full-page search draft state:** restores uncommitted local state that should stay out of history.
- **Mixed-mode partial reloads with `only: ['movies']` or `only: ['series']`:** Inertia merges partial props and can leave the other section stale.
- **Assuming the audit failure is still live without rerunning it:** current reproduction disagrees with the audit record.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Browser history replay | Custom `pushState` / `popstate` state store | Inertia history cache via `router.get` and `Link` | Officially supported; keeps props and URL aligned. |
| Pagination query preservation | Manual query-string concatenation | Laravel paginator `withQueryString()` | Preserves current filters without client-side string drift. |
| Mixed-results page coordination | Independent movie/series page params | One shared `page` contract | Matches roadmap and prior Phase 11 decision. |
| Draft-state restoration | Remembered local form state | Local `useState` + sync from committed props | Prevents unsubmitted query text from leaking into history replay. |
| New browser harness | New auth/bootstrap flow | Existing `browserLoginAndVisit` + same-page follow-up visits | Phase 13 already established the stable pattern. |

**Key insight:** This phase should lean harder on Inertia's built-in history semantics, not add another state layer beside them.

## Common Pitfalls

### Pitfall 1: URL rewinds before DOM replay finishes
**What goes wrong:** The browser helper sees `page` disappear from the URL, but the result-count assertion still reads the old paginated subset.

**Why it happens:** Browser history mutation and React/Inertia re-render completion are not the same checkpoint.

**How to avoid:** Poll for the restored visible counts or final body text after the URL change, not just the query param.

**Warning signs:** The audit fails at the count assertion, but reruns pass without app changes.

### Pitfall 2: Accidentally splitting mixed pagination state
**What goes wrong:** Movies and series stop replaying together on back/forward.

**Why it happens:** Laravel supports multiple paginator page names, which is useful generally but wrong for this product contract.

**How to avoid:** Keep one shared `page` param in mixed mode.

**Warning signs:** URLs start growing `movie_page` or `series_page` params.

### Pitfall 3: Remembering draft query state
**What goes wrong:** Back/forward restores unsubmitted search text that was never part of the URL.

**Why it happens:** Inertia only restores local state when you explicitly opt into remember/restore behavior.

**How to avoid:** Keep full-page draft query as ordinary local state synced from committed props.

**Warning signs:** Back button restores typed-but-unsubmitted text.

### Pitfall 4: Reintroducing mixed-mode partial reload drift
**What goes wrong:** One section updates while the other section stays stale after pagination or history movement.

**Why it happens:** Partial reloads merge returned props with cached props instead of replacing the whole page payload.

**How to avoid:** In mixed mode, always navigate the full `/search` page props together.

**Warning signs:** URL changes to `page=2` while one section still shows page 1 counts.

### Pitfall 5: Fixing the backend when the issue is only in the proof
**What goes wrong:** Planner schedules unnecessary controller/DTO work.

**Why it happens:** Audit evidence says "app regression", but current reproduction suggests the code path may already be correct.

**How to avoid:** Reproduce first; only widen scope beyond `search.tsx` and `SearchModeUxTest.php` if fresh evidence shows server props are wrong.

**Warning signs:** Proposed changes touch `SearchController.php` before a current failing reproduction exists.

## Code Examples

Verified patterns from official sources and current repo code:

### Commit full search state with an Inertia GET visit
```tsx
router.get(searchUrl, {}, {
    preserveState: true,
    preserveScroll: false,
    onStart: () => setIsCommitting(true),
    onFinish: () => setIsCommitting(false),
})
```

Source: `resources/js/pages/search.tsx`, https://inertiajs.com/manual-visits

### Preserve active filters in paginator links
```php
return Pipeline::send(Series::search($query))
    ->through($pipes)
    ->thenReturn()
    ->withQueryString();
```

Source: `app/Actions/SearchSeries.php`, https://laravel.com/docs/12.x/pagination

### Keep draft input aligned with committed history-restored props
```tsx
useEffect(() => {
    setDraftQuery(props.filters.q ?? '')
}, [props.filters.q])
```

Source: `resources/js/pages/search.tsx`, https://inertiajs.com/remembering-state

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Raw query text and magic words doing too much | Raw `q` stays visible, explicit params stay canonical, normalized query drives execution | Phase 11, 2026-03-22 | Deep links and history replay use one deterministic search contract. |
| Mixed results pretending each section can page independently | One shared mixed-mode `page` URL | Phase 11, 2026-03-22 | Refresh and browser history can replay both sections together. |
| Suite-local auth and generic click helpers | Shared `browserLoginAndVisit` and tab/button-targeted pointer helpers | Phase 13, 2026-03-25 | Search browser proof is stable enough to test history restoration on the live auth path. |

**Deprecated/outdated:**
- Custom query-param/history helpers for committed `/search` state.
- Separate paginator page names for mixed search results.
- Remembered full-page draft query state.

## Open Questions

1. **Is SRCH-04 currently broken on `main`, or is the audit evidence stale/flaky?**
   - What we know: `.planning/v1.1-MILESTONE-AUDIT.md` records a reproducible failure on 2026-03-27, but current reruns of the same targeted command and full browser audit pass locally.
   - What's unclear: whether the original failure was environment-specific, flaky, or already fixed after the audit snapshot.
   - Recommendation: make fresh reproduction the first planning task and treat wider code changes as conditional.

2. **If a failure reappears, is it app state drift or browser-proof timing?**
   - What we know: current `search.tsx` is prop-driven, and official Inertia docs say browser history restores cached page props.
   - What's unclear: whether the count assertion can race ahead of DOM hydration after URL rewind.
   - Recommendation: instrument the browser proof first by waiting on restored counts/body text before changing backend code.

3. **Should pagination `prefetch` stay enabled for this page if history flake returns?**
   - What we know: current paginator uses `prefetch={true}` and still passes in current reproduction.
   - What's unclear: whether prefetch interacts with any intermittent history-state issue.
   - Recommendation: keep it unless a reproduced failure ties directly to prefetch behavior.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest ^4.4 + pest-plugin-browser ^4.3 + Playwright 1.54.1 |
| Config file | `tests/Pest.php`, `phpunit.xml` |
| Quick run command | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" --stop-on-failure` |
| Full suite command | `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php --stop-on-failure` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SRCH-04 | Base mixed-results `/search?q=...` -> `page=2` -> back/forward/refresh restores the same movie and series counts for the URL | browser + feature smoke | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" --stop-on-failure` | ✅ |

### Sampling Rate
- **Per task commit:** `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" --stop-on-failure`
- **Per wave merge:** `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php --stop-on-failure`
- **Phase gate:** Full suite green, then rerun `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php tests/Browser/IgnoredDiscoveryFiltersTest.php tests/Browser/DetailPageCategoryContextTest.php --stop-on-failure` before `/gsd-verify-work`

### Wave 0 Gaps
None — existing feature and browser infrastructure already covers this phase. If flake persists, harden helpers inside `tests/Browser/SearchModeUxTest.php` rather than adding new framework files.

## Sources

### Primary (HIGH confidence)
- `app/Http/Controllers/SearchController.php` — current `/search` server contract
- `app/Data/SearchMediaData.php` — canonical search normalization and filter resolution
- `app/Actions/SearchMovies.php` — mixed-mode movie paginator contract with `withQueryString()`
- `app/Actions/SearchSeries.php` — mixed-mode series paginator contract with `withQueryString()`
- `resources/js/pages/search.tsx` — current client state, pagination, and history-sensitive rendering
- `resources/js/components/ui/pagination.tsx` — Inertia link behavior for pagination
- `tests/Browser/SearchModeUxTest.php` — current SRCH-04 browser proof
- `tests/Feature/Controllers/SearchControllerTest.php` — server-side search contract coverage
- https://inertiajs.com/manual-visits — Inertia v2 visit semantics, history entries, `preserveState`
- https://inertiajs.com/links — Inertia v2 `Link` history and state-preservation semantics
- https://inertiajs.com/partial-reloads — partial reload merge behavior and same-page limitation
- https://inertiajs.com/remembering-state — history restores cached props, local state is only restored when remembered
- https://laravel.com/docs/12.x/pagination — shared `page` behavior, multiple paginator page names, `withQueryString()`

### Secondary (MEDIUM confidence)
- `.planning/v1.1-MILESTONE-AUDIT.md` — audit evidence for the original SRCH-04 gap; contradicted by current reproduction, so treat as stale-or-flaky until reconfirmed

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - all recommended libraries and patterns are already in the repo and confirmed by official docs.
- Architecture: MEDIUM - current code strongly matches the intended design, but the audit/reproduction contradiction means the exact remaining fix scope is not fully settled.
- Pitfalls: MEDIUM - the likely failure mode is clear, but current evidence points to either stale audit data or timing-sensitive browser proof rather than a consistently live runtime defect.

**Research date:** 2026-03-27
**Valid until:** 2026-04-03
