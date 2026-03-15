# Architecture Research

**Domain:** Per-user category personalization inside the existing Laravel 12 + Inertia React monolith
**Researched:** 2026-03-15
**Confidence:** HIGH

## Standard Architecture

### System Overview

```text
┌──────────────────────────────────────────────────────────────────────────┐
│                             Inertia React UI                             │
├──────────────────────────────────────────────────────────────────────────┤
│  movies/index.tsx   series/index.tsx   search.tsx   movies/show.tsx     │
│  category-sidebar.tsx (search + pin/reorder/hide/ignore controls)       │
└───────────────────────────────┬──────────────────────────────────────────┘
                                │ Inertia visits + partial reloads
┌───────────────────────────────┴──────────────────────────────────────────┐
│                            Laravel HTTP layer                             │
├──────────────────────────────────────────────────────────────────────────┤
│  Existing: VodStreamController, SeriesController, SearchController,      │
│            LightweightSearchController                                   │
│  New:      UserCategoryPreferenceController                              │
└───────────────────────────────┬──────────────────────────────────────────┘
                                │ delegates
┌───────────────────────────────┴──────────────────────────────────────────┐
│                        Action / query composition                         │
├──────────────────────────────────────────────────────────────────────────┤
│  BuildPersonalizedCategorySidebar                                        │
│  UpsertUserCategoryPreferences                                           │
│  ApplyIgnoredCategoryFilters                                             │
│  ResolveMediaCategoryLabels                                              │
│  SearchCatalog                                                           │
└───────────────┬───────────────────────────────┬──────────────────────────┘
                │                               │
                │ SQL                           │ Xtream detail DTOs
┌───────────────┴──────────────┐     ┌──────────┴──────────────────────────┐
│      Local catalog DB         │     │        Existing Saloon layer        │
├───────────────────────────────┤     ├─────────────────────────────────────┤
│ categories                    │     │ GetVodInfoRequest / GetSeriesInfo   │
│ vod_streams / series          │     │                                     │
│ user_category_preferences     │     │                                     │
└───────────────────────────────┘     └─────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| `categories` | Global provider taxonomy; source for labels and valid filter ids | Existing `Category` model keyed by `provider_id` |
| `user_category_preferences` | User-only overlay for pin/order/hide/ignore state | New table + `UserCategoryPreference` model keyed by `(user_id, media_type, category_provider_id)` |
| Browse controllers | Build user-scoped catalog lists and sidebar props | Modify `VodStreamController@index` and `SeriesController@index` |
| Search controllers | Apply ignored-category filters and fix page/filter semantics | Modify `SearchController@show` and `LightweightSearchController@show` |
| Detail controllers | Merge local category labels into existing Xtream detail props | Modify `VodStreamController@show` and `SeriesController@show` |
| Sidebar UI | Render visible categories, client-side search over all categories, and mutation controls | Expand `resources/js/components/category-sidebar.tsx` |

## Recommended Project Structure

```text
app/
├── Actions/
│   └── Categories/
│       ├── ApplyIgnoredCategoryFilters.php
│       ├── BuildPersonalizedCategorySidebar.php
│       ├── ResolveMediaCategoryLabels.php
│       └── UpsertUserCategoryPreferences.php
├── Data/
│   ├── CategoryBrowseFiltersData.php                # add richer user filter fields if needed
│   ├── CategorySidebarItemData.php                  # add pinned/hidden/ignored/order/count
│   ├── SearchMediaData.php                          # split page params, explicit filters
│   └── UserCategoryPreferenceData.php              # snapshot payload for writes
├── Http/
│   ├── Controllers/
│   │   ├── VodStream/VodStreamController.php       # modified
│   │   ├── Series/SeriesController.php             # modified
│   │   ├── SearchController.php                    # modified
│   │   ├── LightweightSearchController.php         # modified
│   │   └── CategoryPreferences/UserCategoryPreferenceController.php
│   └── Requests/
│       └── CategoryPreferences/UpdateUserCategoryPreferencesRequest.php
├── Models/
│   └── UserCategoryPreference.php
└── database/migrations/
    └── create_user_category_preferences_table.php

resources/js/
├── components/
│   └── category-sidebar.tsx                        # search + controls + optimistic state
├── pages/
│   ├── movies/index.tsx                            # modified
│   ├── series/index.tsx                            # modified
│   ├── movies/show.tsx                             # modified
│   ├── series/show.tsx                             # modified
│   └── search.tsx                                  # modified
└── types/
    ├── movies.ts
    ├── series.ts
    └── search.ts
```

### Structure Rationale

- **Keep preferences as an overlay, not a category mutation.** `categories` stays global; user state lives separately.
- **Keep query rules in actions.** Ignore/hide/pin logic will touch multiple controllers; do not duplicate it inline.
- **Keep Xtream DTOs external-facing.** Detail-page category labels should be separate props from `info`, not new fields jammed into Saloon response classes.

## New vs Modified Components

| Status | Component | Integration point |
|--------|-----------|-------------------|
| New | `user_category_preferences` table | Persists per-user per-media-type state without changing global categories |
| New | `UserCategoryPreference` model | Thin persistence boundary for overlay rows |
| New | `UpsertUserCategoryPreferences` action | Validates max 5 pins, normalizes order, upserts in one transaction |
| New | `BuildPersonalizedCategorySidebar` action | Merges global categories, counts, and user prefs into one Inertia prop |
| New | `ApplyIgnoredCategoryFilters` action | Shared SQL/Scout constraint for browse + search |
| New | `ResolveMediaCategoryLabels` action | Maps local `category_id` values to display labels for detail pages |
| New | `UserCategoryPreferenceController@update` | Write endpoint for sidebar mutations |
| Modified | `BuildCategorySidebarItems` | Replace or wrap with personalized builder |
| Modified | `VodStreamController@index`, `SeriesController@index` | Apply ignored filters; consume personalized sidebar builder |
| Modified | `VodStreamController@show`, `SeriesController@show` | Add `category_labels` prop from local DB |
| Modified | `SearchController`, `LightweightSearchController` | Current user injection, ignored-category filtering, explicit page params |
| Modified | `category-sidebar.tsx` | Client-side search, drag/drop reorder, pin/hide/ignore actions |
| Modified | `movies/index.tsx`, `series/index.tsx`, `search.tsx` | Wire mutation visits + filtered/full-width rendering |

## Architectural Patterns

### Pattern 1: User-scoped overlay on global taxonomy

**What:** leave `categories` authoritative for provider ids and names; add a user overlay keyed by `provider_id`.

**When to use:** any preference that must not affect other users.

**Trade-offs:** one extra join/merge step per request, but avoids corrupting shared category semantics.

**Example:**

```php
$preferences = UserCategoryPreference::query()
    ->where('user_id', $user->id)
    ->where('media_type', $mediaType->value)
    ->get()
    ->keyBy('category_provider_id');

$items = BuildPersonalizedCategorySidebar::run($user, $mediaType, $selectedCategoryId);
```

### Pattern 2: Shared ignored-category constraint for every discovery query

**What:** one reusable action/scope applies ignored-category rules to catalog SQL and Scout hydration.

**When to use:** movies index, series index, search, lightweight search, and optionally discover.

**Trade-offs:** slightly more abstraction, but prevents drift between browse and search behavior.

**Example:**

```php
$query = VodStream::query()->withExists(['watchlists as in_watchlist' => fn ($q) => $q->where('user_id', $user->id)]);

$query = ApplyIgnoredCategoryFilters::run($query, $user, MediaType::Movie);
```

### Pattern 3: Local metadata augmentation for detail pages

**What:** keep remote Xtream detail DTOs unchanged; send separate local props for category labels.

**When to use:** pages that already hydrate `info` from Xtream but need local catalog metadata.

**Trade-offs:** one extra local lookup, but no coupling between external DTO parsing and local personalization.

**Example:**

```php
return Inertia::render('movies/show', [
    'info' => $vod->dtoOrFail(),
    'in_watchlist' => $inWatchlist,
    'category_labels' => ResolveMediaCategoryLabels::run($model->category_id, MediaType::Movie),
]);
```

### Pattern 4: Explicit search filters, not magic words in `q`

**What:** `q` remains user text; `media_type`, `sort_by`, `movie_page`, and `series_page` live in query params/data DTOs.

**When to use:** full search page and lightweight search requests.

**Trade-offs:** slightly larger DTO, much less brittle UI state.

## Data Flow

### Request Flow

```text
Sidebar render / page load
    ↓
Index controller receives current user + selected category
    ↓
ApplyIgnoredCategoryFilters to base catalog query
    ↓
Paginate media rows
    ↓
BuildPersonalizedCategorySidebar merges:
    categories + counts + user prefs + selected category
    ↓
Inertia partial props: { movies|series, filters, categories }
```

### Preference Mutation Flow

```text
User drags / pins / hides / ignores category
    ↓
category-sidebar.tsx updates local state optimistically
    ↓
PUT/PATCH UserCategoryPreferenceController
    ↓
UpsertUserCategoryPreferences validates:
    max 5 pins, unique category ids, normalized order
    ↓
transactional upsert
    ↓
reload affected list props only
```

### Key Data Flows

1. **Browse pages:** `movies/index.tsx` and `series/index.tsx` keep current partial reload shape; only sidebar builder and catalog query become user-aware.
2. **Sidebar search:** search is client-side over the already-loaded categories prop; no new server search endpoint needed.
3. **Detail pages:** existing Xtream request stays; controller adds local `category_labels` prop.
4. **Search:** full and lightweight search route through one shared query strategy so ignored categories and media-type behavior stay aligned.

## Query and Filtering Impacts

### Browse queries

- Current category filter validation should remain against global valid category ids, not visible sidebar items.
- Hidden categories affect sidebar visibility only.
- Ignored categories affect result sets.
- If the uncategorized bucket is ignored, the shared filter must exclude `NULL`, `''`, and the system uncategorized provider id, matching existing browse semantics.
- Selected hidden category should not 302 as invalid; it should remain a valid filter if the category exists globally.

### Sidebar data

- `CategorySidebarItemData` should grow to include at least: `count`, `isPinned`, `isHidden`, `isIgnored`, `position`.
- Return all categories to the client, not only visible ones; client search needs access to hidden/ignored items for undo flows.
- Sort order should be: pinned by `position`, then remaining visible by `position` fallback alphabetic, then hidden/ignored only in search/manage views.

### Search queries

- Current `SearchController` uses one shared `page`; that causes coupled pagination between movies and series. Split page params.
- Current frontend encodes filters into `q` (`type:movie`, `sort:latest`); make explicit query params the source of truth.
- Ignored-category filtering should happen after search matching but before pagination/hydration is returned to Inertia.
- When `media_type` is set, render one full-width result section and paginate only that section.

### Detail-page metadata

- Do not derive visible category labels from Xtream response text alone.
- Resolve labels from local `categories` using the media row’s `category_id`.
- If provider payloads ever contain comma-delimited category ids, normalize in `ResolveMediaCategoryLabels`; do not introduce a many-to-many catalog schema in this milestone.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-1k users | Current monolith is enough; preference reads are cheap if keyed by `(user_id, media_type)` |
| 1k-100k users | Cache per-user sidebar payload briefly; keep ignored-category ids in memory for the request |
| 100k+ users | Revisit search-side filtering strategy; likely push category fields deeper into the search engine query path |

### Scaling Priorities

1. **First bottleneck:** repeated sidebar merge/count queries on every browse request. Fix with a dedicated builder and short-lived caching.
2. **Second bottleneck:** search hydration under large ignored-category sets. Fix by keeping ignore ids compact and applying them once in shared query code.

## Anti-Patterns

### Anti-Pattern 1: Store preferences as JSON on `users`

**What people do:** put all category state in one blob column or browser localStorage.

**Why it's wrong:** hard to query, hard to validate pin limits, impossible to reuse cleanly in SQL filtering.

**Do this instead:** row-per-category overlay with composite uniqueness.

### Anti-Pattern 2: Duplicate ignore logic in each controller

**What people do:** hand-write `whereNotIn` branches in movies, series, and search independently.

**Why it's wrong:** uncategorized handling and future fixes drift immediately.

**Do this instead:** one shared action/scope used everywhere discovery results are built.

### Anti-Pattern 3: Mutate global categories for user personalization

**What people do:** add `hidden`, `position`, or `pinned` directly to `categories`.

**Why it's wrong:** one user’s preferences leak into every other user’s discovery UI.

**Do this instead:** keep `categories` global and preferences user-scoped.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Xtream detail endpoints | Existing Saloon requests stay unchanged | Add local category label props in controllers, not in response DTOs |
| Laravel Scout / Meilisearch / database driver | Existing search actions, plus shared ignored-category constraint | Do not encode per-user preferences into the index |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| `Category` ↔ `UserCategoryPreference` | provider id overlay | Use `provider_id`, not category table numeric id, because media tables already store provider ids |
| Browse controllers ↔ category actions | direct action calls | Keep controller surface small; actions own merge/filter rules |
| Search controllers ↔ search actions | direct action calls | Same ignored-category logic for full and lightweight search |
| Detail controllers ↔ Xtream DTOs | controller composition | Remote DTO provides media info; local DB provides category labels |
| Sidebar UI ↔ preference controller | Inertia form/visit | Full snapshot update is simpler than many tiny mutation endpoints |

## Suggested Build Order

1. **Preference persistence contract**
   - Add `user_category_preferences` migration/model.
   - Add request/data objects and `UpsertUserCategoryPreferences` transaction logic.
   - Lock down invariants: unique per category, max 5 pins, provider-id keyed rows.

2. **Shared read-path integration**
   - Replace `BuildCategorySidebarItems` with user-aware builder.
   - Introduce `ApplyIgnoredCategoryFilters` and wire it into `VodStreamController@index` and `SeriesController@index`.
   - Keep current page props shape stable so UI can evolve incrementally.

3. **Sidebar UX + write path**
   - Expand `category-sidebar.tsx` to support client-side search, pin/reorder/hide/ignore state, and optimistic saves.
   - Update `movies/index.tsx` and `series/index.tsx` to call the new preference endpoint and partial-reload list props.

4. **Detail pages + search fixes**
   - Add `category_labels` to movie/series show controllers/pages.
   - Refactor `SearchMediaData`, `SearchController`, `LightweightSearchController`, and `search.tsx` to use explicit filters and separate page params.
   - Apply ignored-category filtering to search/autocomplete.

5. **Regression coverage and polish**
   - Extend browse controller tests for hidden/ignored/pinned ordering.
   - Add controller tests for preference updates and search pagination/filter correctness.
   - Add browser coverage for drag/reorder/search/sidebar flows if the team wants end-to-end confidence.

## Sources

- `/home/coder/project/lionzhd/.planning/PROJECT.md`
- `/home/coder/project/lionzhd/app/Http/Controllers/VodStream/VodStreamController.php`
- `/home/coder/project/lionzhd/app/Http/Controllers/Series/SeriesController.php`
- `/home/coder/project/lionzhd/app/Http/Controllers/SearchController.php`
- `/home/coder/project/lionzhd/app/Http/Controllers/LightweightSearchController.php`
- `/home/coder/project/lionzhd/app/Actions/BuildCategorySidebarItems.php`
- `/home/coder/project/lionzhd/app/Models/Category.php`
- `/home/coder/project/lionzhd/database/migrations/2026_02_25_000001_create_categories_table.php`
- `/home/coder/project/lionzhd/tests/Feature/Discovery/MoviesCategoryBrowseTest.php`
- `/home/coder/project/lionzhd/tests/Feature/Discovery/SeriesCategoryBrowseTest.php`
- `/home/coder/project/lionzhd/tests/Feature/Controllers/SearchControllerTest.php`
- Xtream API reference: https://raw.githubusercontent.com/gtaman92/XtreamCodesExtendAPI/refs/heads/master/player_api.php

---
*Architecture research for: per-user category controls and ignored-category filtering*
*Researched: 2026-03-15*
