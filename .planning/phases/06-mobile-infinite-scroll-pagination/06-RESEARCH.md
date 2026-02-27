# Phase 06: Mobile Infinite-Scroll Pagination - Research

**Researched:** 2026-02-27
**Domain:** Laravel (offset pagination correctness) + Inertia (React) mobile infinite scroll state/URL/restore
**Confidence:** HIGH

## Summary

This phase is mostly about **pagination determinism** (server) and **state + history correctness** (Inertia) under a mobile infinite-scroll UX. The current implementation is not aligned with the locked behavior: the hook resets accumulated items after loading a new page (dropping prior pages), load-more error handling doesn’t auto-retry/pause correctly, and “Try again” currently clears the error without retrying. On the server, both Movies and Series order by a single timestamp column (stored as a string), which is not a stable total ordering; combined with offset-based pagination, this can produce **duplicates/skips across page boundaries** when timestamps tie and/or when new rows appear between requests.

To plan/implement Phase 06 well:
1) **Make the server pagination contract stable and snapshot-consistent** (stable sort + session “as-of” cutoff appended into paginator URLs).
2) **Rewrite the mobile infinite-scroll hook as a small state machine** (one in-flight request, no chain-loading, auto-retry once, pause until explicit retry, per-category error state).
3) **Implement per-category restore** (loaded items + last reached page + exact scroll position) within the SPA session using Inertia’s remember API (debounced / on navigation), plus `replace: true` to keep URL updated without polluting history.
4) **Add regression feature tests** that deterministically detect boundary skips/duplicates using controlled inserts between page requests.

**Primary recommendation:** Freeze the result-set for a scroll session using an `as_of` query param (appended via paginator URLs) + stable tie-break ordering, and back it with Pest tests that simulate inserts between page=1 and page=2.

## Standard Stack

No new deps.

### Core
| Library/Tool | Version (repo) | Purpose | Why Standard |
|---|---:|---|---|
| Laravel Framework | ^12.0 (composer.json) | Offset pagination (`paginate`) + query building | Built-in paginator, generates `next_page_url` + `links` |
| Inertia (Laravel adapter) | inertiajs/inertia-laravel ^2.0 | Inertia responses + partial reload support | Standard for Laravel + Inertia |
| Inertia React | @inertiajs/react ^2.0.9 | `router.visit`, `Link`, `useRemember` | Standard client routing + state remembering |
| React | ^19.1.0 | UI + hook state machine | Existing stack |
| Pest + PHPUnit | pestphp/pest ^3.8 | Feature regression tests | Existing test stack |

### Supporting
| Library/Tool | Version | Purpose | When to Use |
|---|---:|---|---|
| Browser history + session APIs | n/a | Per-category restore within session | Cache scrollY + loaded items |
| Existing scroll utils | resources/js/lib/scroll-utils.ts | Near-bottom detection + debounced scroll | Keep if not switching to IntersectionObserver |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|---|---|---|
| Offset pagination (`paginate`) | Cursor pagination (`cursorPaginate`) | Cursor pagination avoids skip/dup under writes (Laravel docs) but conflicts with “page=3 deep links” requirement and numbered pages. |

## Architecture Patterns

### Recommended Project Structure (Phase 06 touchpoints)
```
app/Http/Controllers/
  VodStream/VodStreamController.php
  Series/SeriesController.php

resources/js/hooks/
  use-infinite-scroll.ts

resources/js/components/ui/
  enhanced-pagination.tsx

resources/js/pages/
  movies/index.tsx
  series/index.tsx

tests/Feature/Discovery/
  MoviesCategoryBrowseTest.php
  SeriesCategoryBrowseTest.php
```

### Pattern 1: Stable, snapshot-consistent pagination contract (server)

**What:** Offset pagination must have a **total ordering** and must not “shift” mid-scroll. Achieve this with:
- A stable sort: `(timestamp DESC, primary_key DESC)`.
- A snapshot cutoff (`as_of`) appended into paginator URLs so subsequent pages use the same cutoff.

**When to use:** Always for endpoints that are infinite-scrolled (Movies + Series browse), even if desktop still shows traditional pagination.

**Example (movies):**
```php
$asOf = $request->query('as_of'); // if empty, generate server-side

$query = VodStream::query()
    ->when($asOf, fn ($q) => $q->where('added', '<=', $asOf))
    ->orderByDesc('added')
    ->orderByDesc('stream_id');

$movies = $query
    ->paginate(20)
    ->appends(['as_of' => $asOf])
    ->withQueryString();
```

**Rationale:**
- Stable tie-break prevents reordering when many rows share the same `added` / `last_modified`.
- `as_of` prevents offset-pagination skips/dups when new rows appear between page requests.

**Source:** Laravel pagination docs: `paginate`, `appends`, `withQueryString` (https://laravel.com/docs/11.x/pagination).

### Pattern 2: Mobile infinite-scroll hook as a state machine (client)

**What:** Treat infinite scroll as a small state machine with explicit gating.

**Required behaviors from 06-CONTEXT.md:**
- One in-flight request.
- Auto-load near bottom.
- No chain-loading (require a “near-bottom transition” before the next load).
- Auto-retry once.
- After an error, pause auto-loading until the user explicitly retries.
- URL updates to current highest page while scrolling.
- Per-category restore of items + scroll position.

**Inertia mechanics to rely on:**
- `replace: true` to update URL without adding many history entries.
- `preserveState: true` to keep local component state across page=2/page=3 visits.
- `preserveScroll: true` to avoid scroll jumps during load-more visits.
- `onCancelToken` to cancel in-flight visits when category changes / unmounts.

**Source:** Inertia manual visits options (`replace`, `preserveState`, `preserveScroll`, `onCancelToken`) https://inertiajs.com/manual-visits.

### Pattern 3: Per-category state restore using Inertia remembering

**What:** Persist local state (loaded items + reached page + error/paused flags + scrollY) keyed per category.

**How:** Use `useRemember` with a unique key per resource + category (e.g. `Movies/InfiniteScroll:${categoryId ?? 'all'}`). Save scroll position on navigation away (or debounced), restore on mount.

**Source:** Inertia remembering state (`useRemember`, `router.remember`, `router.restore`) https://inertiajs.com/remembering-state.

## Likely Root Causes (current code)

### Root cause A (HIGH): Unstable server ordering + offset pagination shifts
- Movies: `->orderByDesc('added')->paginate(20)` (app/Http/Controllers/VodStream/VodStreamController.php)
- Series: `->orderByDesc('last_modified')->paginate(20)` (app/Http/Controllers/Series/SeriesController.php)

Both order by a single (string) timestamp column; ties + concurrent inserts can cause duplicates/skips across pages.

### Root cause B (HIGH): Infinite-scroll hook resets accumulated items after loading a page
In `resources/js/hooks/use-infinite-scroll.ts`, the “reset accumulated data” effect resets `allData` whenever `data.length !== allData.length`, which becomes true immediately after appending (dropping previously loaded items).

### Root cause C (HIGH): Error/retry UX does not match locked decisions
- No auto-retry-once.
- After load-more error, auto-loading is not paused; near-bottom scroll events can spam retries.
- “Try Again” currently calls `clearError()` only (EnhancedPagination), but decision requires “Try again retries load immediately.”

### Root cause D (MEDIUM): Missing scroll/state preservation when navigating to details (Movies)
Series detail links use `<Link preserveState preserveScroll ...>` but Movies detail links do not, making exact back restore less reliable.

## Don't Hand-Roll

| Problem | Don’t Build | Use Instead | Why |
|---|---|---|---|
| Next-page detection | Parsing pagination `links[].label` for “Next” | Use paginator’s `next_page_url` / `current_page` / `last_page` from Laravel paginator JSON | Link labels can vary; URLs are authoritative.
| History stack mgmt | Pushing a new history entry per auto-load | Inertia `replace: true` for load-more | Keeps back navigation sane + reduces history bloat.
| Canceling in-flight loads | Ad-hoc “isStale” booleans only | Inertia `onCancelToken` + cancel on category change/unmount | Prevents late responses mutating wrong category state.
| Infinite scroll correctness under writes | “Hope offset pagination won’t shift” | `as_of` snapshot cutoff (or cursor paginate where allowed) | Offset pagination can skip/dup when rows are added/deleted (Laravel docs mention this). |

## Common Pitfalls

### Pitfall 1: “Deterministic ordering” without a tie-breaker
**What goes wrong:** Items with identical timestamps can appear on either side of a page boundary, causing duplicates/skips or React key collisions.
**How to avoid:** Always add a unique secondary order (e.g. primary key DESC).

### Pitfall 2: Offset pagination under concurrent inserts
**What goes wrong:** New rows inserted between page=1 and page=2 shift the offset, skipping the “last item” of the original page window.
**How to avoid:** Freeze the session result-set with `as_of` and include it in generated next-page URLs.

### Pitfall 3: Chain-loading caused by “near bottom” staying true
**What goes wrong:** Scroll momentum on mobile keeps firing near-bottom triggers; once a load finishes, another load starts immediately.
**How to avoid:** Trigger loadMore only on a **false→true** near-bottom transition (or sentinel exit/enter), plus one-in-flight gating.

### Pitfall 4: Excessive `router.remember()` calls
**What goes wrong:** Browsers can limit frequent `history.replaceState()` calls; Inertia warns remembered state updates can be dropped.
**How to avoid:** Remember only on meaningful state changes (after loadMore, on navigation away) and debounce scroll position persistence.
**Source:** Inertia remembering-state warning https://inertiajs.com/remembering-state.

## What to Test (MOBL-01..03)

### MOBL-01: No missing last item across page boundaries
**Automated (Pest feature test):**
1. Seed 41 Movies (or Series) with a strictly ordered timestamp sequence.
2. Request page=1 and record the first 20 IDs.
3. Insert a new “newest” row (timestamp greater than all existing) between requests.
4. Request page=2 using the next-page URL (or route with page=2).
5. Assert the union of IDs from page1+page2 contains the expected original 40 unique IDs (i.e., no missing “boundary” item).

This test is deterministic and will fail unless a snapshot cutoff (`as_of`) is used consistently.

### MOBL-02: Deterministic ordering across infinite-scroll pagination
**Automated (Pest feature test):**
- Seed 40 rows with identical timestamps and known IDs.
- Request page=1 and assert the IDs are in the expected deterministic order (requires explicit tie-break ordering).
- Request page=2 and assert the next contiguous slice.

### MOBL-03: Boundary behavior regression-tested
**Automated coverage checklist:**
- `next_page_url` preserves `category` (already covered) and additionally preserves `as_of`.
- Movies + Series both have boundary tests (MOBL-01) and tie-order tests (MOBL-02).

## Code Examples

### Inertia load-more visit (replace URL, preserve state/scroll, cancelable)
```ts
import { router } from '@inertiajs/react';

let cancelToken: { cancel: () => void } | null = null;

router.visit(nextPageUrl, {
  method: 'get',
  only: ['movies'],
  preserveState: true,
  preserveScroll: true,
  replace: true,
  onCancelToken: (token) => (cancelToken = token),
  onSuccess: (page) => {
    // append new page props into accumulated list
  },
});

// on category switch / unmount
cancelToken?.cancel();
```
**Source:** Inertia manual visits options https://inertiajs.com/manual-visits.

### Inertia per-category remember key
```ts
import { useRemember } from '@inertiajs/react';

const [state, setState] = useRemember(
  { items: [], reachedPage: 1, scrollY: 0, error: null },
  `Movies/InfiniteScroll:${categoryId ?? 'all'}`,
);
```
**Source:** https://inertiajs.com/remembering-state.

### Server: append query values into paginator URLs
```php
$paginator = $query->paginate(20)
    ->appends(['as_of' => $asOf])
    ->withQueryString();
```
**Source:** https://laravel.com/docs/11.x/pagination#appending-query-string-values.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|---|---|---|---|
| Offset pagination without snapshot | Cursor pagination for infinite scroll | Longstanding | Avoids skip/dup under frequent writes, but uses `cursor=` not `page=`. |
| Offset pagination without snapshot | Offset pagination + `as_of` cutoff + stable tie-break | Common workaround | Preserves page-number deep links while preventing shifting during a session. |

## Open Questions

1. **Deep link behavior for `page>1` on mobile:** load only that page (no chain-load) vs. reconstruct pages 1..N.
   - What we know: chain-loading multiple pages automatically is disallowed; deep links with `page=3` must “work”.
   - Recommendation: treat deep link as “start at that page”, but still apply deterministic ordering + `as_of` for subsequent load-more.

2. **How much to persist per-category (memory limits):** storing full item objects for many pages can bloat history state.
   - Recommendation: cap remembered pages/items per category (planner decision), or store in an in-memory module cache and remember only minimal metadata.

## Sources

### Primary (HIGH confidence)
- Inertia (manual visits / router options): https://inertiajs.com/manual-visits
- Inertia (remembering state / useRemember): https://inertiajs.com/remembering-state
- Inertia (scroll management): https://inertiajs.com/scroll-management
- Laravel (pagination / appends / withQueryString / cursor paginate caveats): https://laravel.com/docs/11.x/pagination

### Project Sources (HIGH confidence)
- Current hook: resources/js/hooks/use-infinite-scroll.ts
- Mobile loader UI: resources/js/components/ui/enhanced-pagination.tsx
- Movies page: resources/js/pages/movies/index.tsx
- Series page: resources/js/pages/series/index.tsx
- Movies controller: app/Http/Controllers/VodStream/VodStreamController.php
- Series controller: app/Http/Controllers/Series/SeriesController.php
- Existing feature tests: tests/Feature/Discovery/MoviesCategoryBrowseTest.php, SeriesCategoryBrowseTest.php

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH (repo lockfiles + package manifests)
- Architecture: HIGH (directly supported by Inertia + Laravel docs)
- Pitfalls: HIGH (observed code issues + documented offset pagination behavior)

**Valid until:** 2026-03-27
