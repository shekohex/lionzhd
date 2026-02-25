# Phase 04: Category Browse/Filter UX - Research

**Researched:** 2026-02-25
**Domain:** Inertia (Laravel 12 + React) browse/filter UX with server-side pagination
**Confidence:** HIGH

## Summary

This phase is primarily an Inertia-driven “server renders filtered paginated list” problem with a React sidebar UI that drives a single query param (category provider ID). The backend must (1) validate the requested category ID against the correct category dataset (movies vs series), (2) filter the paginator by `category_id` (including explicit system “Uncategorized” IDs), and (3) provide a categories payload that supports A–Z ordering + “disabled if zero items”.

On the frontend, the page must stop using the current artificial `setTimeout` loading and instead show skeletons specifically during category switches. Use Inertia `preserveState` for mobile (keep slide-over open), `preserveScroll: false` + dropping `page` to reset to page 1, and `only: ['movies']` / `only: ['series']` partial reloads for fast category switching.

**Primary recommendation:** Implement category switching as an Inertia partial reload (`only`) with server-side lazy props (closures), and validate `?category=` in controllers with redirect+flash warning when missing.

## Repo-Aware Deltas (DISC-01..04)

### Backend deltas
- **Controllers**
  - `app/Http/Controllers/VodStream/VodStreamController.php@index`
    - Accept `Request $request` (or use helper) and read `?category=`.
    - Validate requested category against `Category::where('in_vod', true)` provider IDs.
    - Invalid/missing category ID in URL => **redirect to `route('movies')`** and `with('warning', ...)` (toast via `AppShell`).
    - Apply filter to `VodStream` query (`where('category_id', $categoryId)`), including explicit uncategorized (`Category::UNCATEGORIZED_VOD_PROVIDER_ID`).
    - Add `->withQueryString()` on paginator.
    - Add `categories` + `filters` props (ideally closures for Inertia partial reload efficiency).
  - `app/Http/Controllers/Series/SeriesController.php@index` (same pattern)
    - Use `Category::where('in_series', true)` and `Category::UNCATEGORIZED_SERIES_PROVIDER_ID`.
- **Category sidebar data**
  - Query categories from `categories` table (per-media flag).
  - Compute a `disabled` flag from a single aggregate count query per media type (no N+1).
  - Sort A–Z by name, force uncategorized to bottom (system provider IDs).
- **(Optional but recommended) DB indexes**
  - Add indexes on `vod_streams.category_id` and `series.category_id` to keep filtering and counting responsive at scale.

### Frontend deltas
- **Pages**
  - `resources/js/pages/movies/index.tsx`
    - Add a left sidebar (desktop) and a `Sheet` slide-over (mobile) for category selection.
    - Remove the simulated `setTimeout` loading; replace with navigation-driven skeletons on category switch.
    - Category click:
      - If clicked category is already selected => navigate to “All” (remove `category` param).
      - Otherwise navigate to `?category=<id>`.
      - Reset page to 1 (drop `page`).
      - Scroll top.
      - Use Inertia `preserveState: true` so mobile sheet stays open.
      - Use Inertia `only: ['movies', 'filters']` (categories don’t need reload on every click).
    - Empty state copy differs for All vs category vs Uncategorized (per locked context).
  - `resources/js/pages/series/index.tsx` mirrors Movies.
- **Shared component (recommended)**
  - Add a reusable `CategorySidebar` component used by both pages to keep ordering/UX identical.
- **Types**
  - Extend `resources/js/types/movies.ts` and `resources/js/types/series.ts` page props to include `categories` + `filters`.
  - Run `php artisan typescript:transform` to fix `generated.d.ts` drift before relying on DTO types.

### Data contracts (page props)
Recommend adding these props to both pages:
```ts
type CategorySidebarItem = { id: string; name: string; disabled: boolean; isUncategorized: boolean }

type CategoryBrowseFilters = { category: string | null }

type MoviesIndexProps = MoviesPageProps & {
  categories: CategorySidebarItem[]
  filters: CategoryBrowseFilters
}
```

## Route / Query Semantics (locked behavior)

- **Route:** `GET /movies` and `GET /series` (existing routes: `routes/web.php`).
- **Query param:** `category` (string provider ID).
  - **All categories (default):** no `category` param.
  - **Specific category:** `?category=<categories.provider_id>`.
- **Pagination:** Laravel uses `page` query param.
  - Category change must reset to page 1 by removing `page`.
  - Use `->withQueryString()` so pagination links keep `category`.
- **Edge cases**
  - `category` missing in DB (or wrong dataset) => redirect to base route + flash warning.
  - Category exists but has zero items => allow selection and show empty results.
  - Uncategorized uses different stable IDs for movies vs series (system rows).

## Test Strategy (planning-grade)

### Backend feature tests (Pest)
- Add new feature tests for `GET /movies` and `GET /series` using Inertia headers (pattern in `tests/Feature/Settings/SyncCategoriesControllerTest.php`).
- Seed via model `::query()->create([...])` (no factories exist for VodStream/Series/Category yet).
- Assertions:
  - Component is `movies/index` / `series/index`.
  - Filtering:
    - With `?category=<valid>` returns only items with that `category_id`.
    - With uncategorized ID returns only uncategorized items.
  - Invalid category redirects + session warning.
  - Existing zero-item category does **not** redirect; paginator total is 0.
  - `movies.links[*].url` (or `next_page_url`) contains `category=` when filter is active (proves `withQueryString`).
  - Categories ordering: A–Z, uncategorized last; disabled true when count 0.

### UI/integration verification (no JS test harness in repo)
- Rely on feature tests for contracts + manual smoke:
  - Desktop: category click updates list, page resets to 1.
  - Mobile: category sheet stays open across selections.

## Sequencing Recommendations (planner waves)

1. **Wave 1 (backend correctness):** controller query semantics + filtering + `withQueryString` + feature tests.
2. **Wave 2 (UI skeleton + sidebar):** shared sidebar component + movies + series pages wired to backend props; immediate apply, toggle-to-All, scroll reset.
3. **Wave 3 (polish):** empty/error/disabled-tooltip UX, type regeneration (`typescript:transform`), optional DB indexes.

## Standard Stack

### Core
| Library | Version (repo) | Purpose | Why Standard (here) |
|---|---:|---|---|
| Laravel | ^12.0 (`composer.json`) | Backend routing/controllers/pagination | Existing app + paginator JSON contract |
| inertiajs/inertia-laravel | ^2.0 (`composer.json`) | Server adapter for Inertia props | Existing controllers/pages are Inertia |
| @inertiajs/react | ^2.0.9 (`package.json`) | Client adapter: `<Link>`, `router.visit/get/reload` | Existing pagination/infinite scroll uses it |
| React | ^19.1 (`package.json`) | UI | Existing pages |
| Tailwind CSS | ^4.1.6 (`package.json`) | Styling/truncation/layout | Existing UI |

### Supporting
| Library | Version (repo) | Purpose | When to Use |
|---|---:|---|---|
| Radix UI | various (`package.json`) | Tooltip, ScrollArea, Dialog/Sheet | Sidebar list UX + mobile panel |
| Sonner | ^2.0.3 (`package.json`) | Toasts | Missing category / warnings (already wired via flash) |
| Ziggy | ^2.4 (`composer.json`) | JS `route()` URL generation | Build links with query params |
| Pest | ^3.8 (`composer.json`) | PHP feature tests | Verify filtering + query semantics |
| spatie/laravel-typescript-transformer | ^2.5 (`composer.json`) | Generate `resources/js/types/generated.d.ts` | After adding new DTOs/filters |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|---|---|---|
| Inertia partial reloads (`only`) | Full page prop reload every click | Simpler but slower + harder to preserve mobile panel UI |

**Installation:** already present.

## Architecture Patterns

### Recommended Project Structure (additions)
```
app/
  Http/Controllers/
    VodStream/VodStreamController.php   # movies index filter + categories prop
    Series/SeriesController.php         # series index filter + categories prop
  Data/
    (optional) CategoryBrowseFiltersData.php
    (optional) CategorySidebarItemData.php
resources/js/
  pages/
    movies/index.tsx                    # render category sidebar + filtered list
    series/index.tsx
  components/
    (new) category-sidebar.tsx          # shared sidebar UI (movies/series)
```

### Pattern 1: Controller validates `?category=` then filters paginator

**What:** Index controllers own the source-of-truth for selected category (from query param) and apply DB filtering. Invalid category IDs redirect to “All” and flash a warning (toast handled globally).

**When to use:** Always (URL source-of-truth; back/forward & refresh restore state).

**Implementation notes (repo-aware):**
- Categories are persisted in `categories.provider_id` with per-media flags (`in_vod`, `in_series`) and system uncategorized IDs (see `Category::UNCATEGORIZED_*`).
- Paginators must call `->withQueryString()` so pagination/infinite-scroll links preserve `category` (Laravel docs).

**Example:**
```php
// Source: app/Http/Controllers/VodStream/VodStreamController.php (existing index)
//         app/Models/Category.php (UNCATEGORIZED consts)
//         https://laravel.com/docs/12.x/pagination#appending-query-string-values

public function index(Request $request, #[CurrentUser] User $user): Response|RedirectResponse
{
    $requested = trim((string) $request->query('category', ''));
    $categoryId = $requested !== '' ? $requested : null;

    $categoryIds = Category::query()
        ->where('in_vod', true)
        ->pluck('provider_id')
        ->all();

    if ($categoryId !== null && ! in_array($categoryId, $categoryIds, true)) {
        return to_route('movies')->with('warning', 'Category not found. Showing all categories.');
    }

    $moviesQuery = VodStream::query()
        ->withExists(['watchlists as in_watchlist' => fn ($q) => $q->where('user_id', $user->id)])
        ->when($categoryId !== null, function ($q) use ($categoryId) {
            if ($categoryId === Category::UNCATEGORIZED_VOD_PROVIDER_ID) {
                $q->where(function ($inner) use ($categoryId) {
                    $inner->where('category_id', $categoryId)
                          ->orWhereNull('category_id')
                          ->orWhere('category_id', '');
                });
                return;
            }
            $q->where('category_id', $categoryId);
        })
        ->orderByDesc('added');

    $movies = $moviesQuery->paginate(20)->withQueryString();

    return Inertia::render('movies/index', [
        'movies' => fn () => $movies,
        'filters' => fn () => ['category' => $categoryId],
        'categories' => fn () => /* sidebar items incl disabled */,
    ]);
}
```

### Pattern 2: Category sidebar switching via Inertia partial reload

**What:** Clicking a category triggers an Inertia `router.visit()` / `<Link>` with `only` (reload list only), `preserveState: true` (mobile panel stays open), and scroll reset.

**When to use:** Any category change; also reuse for Retry actions via `router.reload()`.

**Example:**
```ts
// Source: resources/js/hooks/use-infinite-scroll.ts (router.visit + only)
//         https://inertiajs.com/manual-visits
//         https://inertiajs.com/partial-reloads

import { router } from '@inertiajs/react'
import { scrollToTop } from '@/lib/scroll-utils'

function selectCategory(category: string | null) {
  scrollToTop('instant')

  const href = category
    ? route('movies', { category })
    : route('movies')

  router.visit(href, {
    method: 'get',
    only: ['movies', 'filters'],
    preserveState: true,
    preserveScroll: false,
    onStart: () => setIsSwitching(true),
    onFinish: () => setIsSwitching(false),
  })
}
```

### Anti-Patterns to Avoid
- **Forgetting `withQueryString()` on paginator:** pagination links lose `category`, breaking back/forward and infinite scroll.
- **Client-only filtering:** violates “URL is source of truth” and breaks refresh/back.
- **Hard-coding uncategorized ID:** must use the correct per-media constant; movies/series differ.
- **Disabling the active (selected) zero-item category:** prevents “tap selected toggles to All”. Prefer: disable only when `count==0 && !selected`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---|---|---|---|
| Slide-over mobile category panel | custom overlay implementation | `Sheet` (`resources/js/components/ui/sheet.tsx`) | A11y + consistent styling |
| Tooltips for disabled categories | custom hover logic | `Tooltip` (`resources/js/components/ui/sidebar.tsx` usage) | A11y + consistent behavior |
| Pagination / infinite scroll | custom pagination UI | `DualPagination` + `useInfiniteScroll` | Already supports `only`, preserve state/scroll |
| Toast plumbing | new toast wrapper | session flash + `AppShell` (`resources/js/components/app-shell.tsx`) | Already standardized |
| TS type generation | manual `generated.d.ts` edits | `php artisan typescript:transform` | Avoid drift |

**Key insight:** Inertia partial reloads (`only`) + server lazy props (closures) are the simplest way to keep the category panel UI stable while refreshing just the list.

## Common Pitfalls

### Pitfall 1: Type drift for `category_id`
**What goes wrong:** `resources/js/types/generated.d.ts` currently shows `VodStreamData.category_id?: number` and `SeriesData` missing `category_id`, while the PHP DTOs define `?string $category_id`.
**Why it happens:** transformer output is stale (not re-run after Phase 3 changes).
**How to avoid:** run `php artisan typescript:transform` in this phase (and treat it as required for planner tasks).
**Warning signs:** TS compile errors when introducing `filters.category` types, or accidental numeric comparisons.

### Pitfall 2: Invalid category URL handling creates bad history UX
**What goes wrong:** redirect loops or URL keeps invalid `category` while showing “All”.
**How to avoid:** on invalid `category`, redirect to the same route without the param and `with('warning', ...)` (toast already wired).

### Pitfall 3: Counts/disabled-state performance
**What goes wrong:** computing per-category counts naively becomes N+1 queries.
**How to avoid:** one aggregate query (`GROUP BY category_id`) and map results into the categories list.
**Repo note:** `vod_streams.category_id` and `series.category_id` have no indexes (migrations), so consider adding indexes if dataset is large.

### Pitfall 4: Losing mobile “panel stays open” requirement
**What goes wrong:** category click remounts page component and closes the sheet.
**How to avoid:** for category-change visits, set `preserveState: true` (Inertia docs) and ensure the category panel open state lives in component state.

## Code Examples

### Sidebar item contract (recommended)
```ts
export type CategorySidebarItem = {
  id: string // provider_id
  name: string
  disabled: boolean
  isUncategorized: boolean
}

export type CategoryBrowseFilters = {
  category: string | null
}
```

### Computing disabled categories (single query)
```php
// Source: categories schema (database/migrations/2026_02_25_000001_create_categories_table.php)

$categories = Category::query()
    ->where('in_vod', true)
    ->get(['provider_id', 'name']);

$counts = VodStream::query()
    ->selectRaw('category_id, COUNT(*) as c')
    ->groupBy('category_id')
    ->pluck('c', 'category_id');

$items = $categories
    ->map(function (Category $cat) use ($counts, $selectedId) {
        $count = (int) ($counts[$cat->provider_id] ?? 0);
        $selected = $selectedId === $cat->provider_id;

        return [
            'id' => $cat->provider_id,
            'name' => $cat->name,
            'isUncategorized' => $cat->provider_id === Category::UNCATEGORIZED_VOD_PROVIDER_ID,
            'disabled' => $count === 0 && ! $selected,
        ];
    })
    ->sortBy(fn ($row) => $row['isUncategorized'] ? '~~~~' : mb_strtolower($row['name']))
    ->values();
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|---|---|---|---|
| Full prop reload on every click | Inertia partial reloads (`only`) + lazy props (closures) | Inertia v1→v2 era | Faster UX, easier “keep panel open” |

**Outdated in repo today:** Movies/Series/Discover pages simulate loading via `setTimeout` (see `resources/js/pages/movies/index.tsx`, `series/index.tsx`, `discover.tsx`). For this phase, replace with navigation-driven skeletons.

## Open Questions

1. **Carry category selection between Movies ↔ Series navigation?**
   - What we know: provider IDs differ for uncategorized and datasets are independent.
   - Recommendation: do **not** carry; keep `/movies` and `/series` links without `category` to avoid spurious “missing category” warnings.

2. **Query param name: `category` vs `category_id`**
   - Recommendation: use `category` in URL (short, stable provider ID), map to DB `category_id` internally.

## Sources

### Primary (HIGH confidence)
- `.planning/phases/04-category-browse-filter-ux/04-CONTEXT.md` (locked decisions)
- `app/Models/Category.php` (system provider IDs)
- `database/migrations/2026_02_25_000001_create_categories_table.php` (system Uncategorized rows)
- `app/Http/Controllers/VodStream/VodStreamController.php` / `app/Http/Controllers/Series/SeriesController.php` (current index endpoints)
- `resources/js/hooks/use-infinite-scroll.ts` (Inertia partial reload usage)
- `resources/js/components/app-shell.tsx` + `app/Http/Middleware/HandleInertiaRequests.php` (flash → toast pipeline)
- `package.json` / `composer.json` (stack versions)

### Secondary (MEDIUM confidence)
- Laravel docs: pagination query string persistence (`withQueryString`) https://laravel.com/docs/12.x/pagination#appending-query-string-values
- Inertia docs: manual visits / state preservation / partial reloads https://inertiajs.com/manual-visits https://inertiajs.com/partial-reloads

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — verified via `composer.json` / `package.json`
- Architecture: HIGH — matches existing Inertia patterns in repo + official Inertia docs
- Pitfalls: HIGH — observed in repo (type drift, simulated loading) + known Inertia/Laravel behaviors

**Research date:** 2026-02-25
**Valid until:** 2026-03-25
