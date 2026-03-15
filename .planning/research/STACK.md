# Stack Research

**Domain:** Per-user category personalization and search UX on an existing Laravel 12 + Inertia React streaming app
**Researched:** 2026-03-15
**Confidence:** HIGH

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel Eloquent + migrations | Laravel 12.x existing | Persist per-user category order, pins, hide, ignore state | No new backend package needed. Relational data fits current monolith, keeps ignore filters queryable, and matches existing Pest feature-test style. |
| Laravel Scout + current engine | laravel/scout 10.15.x installed, meilisearch/meilisearch-php 1.14.x installed when Meili is enabled | Filter ignored categories in full search and lightweight autocomplete | Already in stack. Needed change is payload/index alignment, not a new search package. |
| `@dnd-kit/core` + `@dnd-kit/sortable` + `@dnd-kit/modifiers` | 6.3.1 / 10.0.0 / 9.0.0 | Reorder and pin categories on web + mobile | Current official sortable stack. Supports pointer, touch, keyboard sensors plus axis/scroll constraints for sidebar and mobile sheet flows. |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `cmdk` | 1.1.1 recommended | Searchable category list / combobox UI | Reuse the existing `resources/js/components/ui/command.tsx` wrapper for sidebar/mobile category search. Upgrade from installed `1.0.0` because `1.1.1` peers React 18/19 while `1.0.0` peers React 18 only. |
| `nuqs` | 2.4.3 existing | Canonical URL state for `media_type`, `sort_by`, `category` filters | Use for page-level filter/query params. Do not use it for transient sidebar search text. |
| Existing Radix primitives (`Sheet`, `ScrollArea`, `Popover`, `Tooltip`, `Badge`) | current repo versions | Mobile drawer, searchable list container, category labels, action affordances | Enough for this milestone. No extra headless UI/search UI package needed. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Pest 4.4.x + pest-plugin-laravel 4.1.x | Feature/controller/query regression coverage | Add tests for preference persistence, ignored-category browse filters, ignored-category search filters, and search param normalization. |
| pest-plugin-browser 4.3.0 + Playwright 1.54.1 | End-to-end reorder/pin/hide/search UX on desktop and mobile viewport | Best fit for one or two high-value interaction tests. Do not add Vitest/RTL just for this milestone. |
| `php artisan scout:sync-index-settings` + `scout:import` | Re-sync Meilisearch after searchable payload changes | Required only if non-database Scout engine is used outside tests/local. |

## UI Primitives for Drag / Reorder

- **Desktop web:** `DndContext` + `SortableContext` + `useSortable` with `PointerSensor` and `KeyboardSensor`.
- **Mobile sheet:** same sortable item model inside existing `Sheet` + `ScrollArea`, but add `TouchSensor` with activation delay/tolerance so vertical scroll still works.
- **Constraints:** use `restrictToVerticalAxis` and `restrictToFirstScrollableAncestor` from `@dnd-kit/modifiers`.
- **Scrollable lists:** use `DragOverlay`; dnd-kit explicitly recommends it for scrollable sortable lists.
- **Pins/hide actions:** keep them as explicit buttons/toggles on each category row; do not overload drag gestures for hide/pin.
- **Category labels on detail pages:** reuse existing `Badge`; no new chip/tag library needed.

## Persistence Model Options

### Recommended: single relational `user_category_preferences` table

| Field | Type | Notes |
|------|------|-------|
| `user_id` | FK | scope to user |
| `category_id` | FK to `categories.id` | use FK, not provider string, to survive renames/data cleanup |
| `media_type` | enum/string | `movie` vs `series`; order/pins are per media type |
| `pin_rank` | nullable tinyint | explicit 1..5 cap for pins |
| `sort_order` | nullable int | ordering for non-pinned visible items |
| `is_hidden` | boolean | hides from nav only |
| `is_ignored` | boolean | excludes matching titles from listings/search |
| timestamps | timestamps | audit/debug |

Recommended constraints / indexes:
- unique: `user_id + media_type + category_id`
- indexes: `(user_id, media_type, is_hidden)`, `(user_id, media_type, is_ignored)`, `(user_id, media_type, pin_rank)`, `(user_id, media_type, sort_order)`

Why this is the right shape:
- one source of truth for sidebar order, pins, hide, ignore
- easy to join in browse queries
- easy to project into Inertia props
- easy to validate server-side max-5 pins rule

### Alternative: split ignore table + JSON/UI-only prefs

Use only if you want the smallest first migration and are willing to keep two persistence paths. Not recommended here; ignore filtering still wants relational data, so splitting adds complexity with little benefit.

### Alternative: JSON column on `users`

Only reasonable if the feature is purely cosmetic. Not recommended because ignored categories must affect browse queries and search/autocomplete consistently.

## Integration Points

| Area | File(s) | Stack implication |
|------|---------|-------------------|
| Sidebar/search UI | `resources/js/components/category-sidebar.tsx` | Add category search input and sortable row model once; share between desktop sidebar and mobile sheet. |
| Category payload composition | `app/Actions/BuildCategorySidebarItems.php` | Merge counts with user prefs; return ordered, pinned, hidden-aware items from server. |
| Browse filtering | `app/Http/Controllers/VodStream/VodStreamController.php`, `app/Http/Controllers/Series/SeriesController.php` | Apply reusable ignored-category scope before category filter/pagination. |
| Search filtering | `app/Actions/SearchMovies.php`, `app/Actions/SearchSeries.php`, `app/Http/Controllers/SearchController.php`, `app/Http/Controllers/LightweightSearchController.php` | Use explicit params and ignored-category constraints instead of encoding filters into `q`. |
| Search index payload | `app/Models/VodStream.php`, `app/Models/Series.php` | Extend `toSearchableArray()` to include every field you filter/sort on. |
| Detail labels | movie/series `show` pages + response DTOs | Add resolved category labels to props; existing `Badge` primitive is enough. |

## Installation

```bash
# Frontend additions / updates
npm install @dnd-kit/core@^6.3.1 @dnd-kit/sortable@^10.0.0 @dnd-kit/modifiers@^9.0.0 cmdk@^1.1.1

# No required Composer additions for this milestone

# If using Meilisearch-backed Scout after payload changes
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\VodStream"
php artisan scout:import "App\Models\Series"
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| `@dnd-kit/*` | `@hello-pangea/dnd@18.0.1` | If the UX is strictly list-only and you want a more opinionated drag/drop abstraction with less sensor wiring. |
| relational `user_category_preferences` | JSON on `users` | Only for non-querying cosmetic prefs. Not suitable for ignored-category filtering. |
| existing `cmdk` wrapper + simple substring matching | Fuse.js / match-sorter | Only if category counts grow into the thousands and ranking quality becomes a real problem. |
| existing Pest feature + browser stack | Vitest / React Testing Library | Only if you extract substantial pure client logic and need fast isolated component tests later. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `react-beautiful-dnd` 13.1.1 | npm marks it deprecated and its peer deps stop at React 18. Bad fit for this React 19 app. | `@dnd-kit/core` + `@dnd-kit/sortable` + `@dnd-kit/modifiers` |
| New global state store for category/search filters | This state is page-local and already fits Inertia props + local state + existing `nuqs`. | Local component state + explicit query params |
| Client-only ignore filtering | Server listings/autocomplete would still leak ignored titles. | Server-side Eloquent/Scout filtering |
| User JSON blob as the only source of ignore state | Hard to join/filter/index; drifts between browse and search behavior. | Relational preference table |

## Stack Patterns by Variant

**If `SCOUT_DRIVER=database` or `collection` in local/tests:**
- Use shared Eloquent scopes for `ignoredBy($user, $mediaType)` and selected-category normalization.
- Because current tests already exercise search under database driver and this keeps behavior deterministic.

**If `SCOUT_DRIVER=meilisearch` in staging/prod:**
- Add `category_id` plus every sorted field (`added`, `last_modified`, `rating`, `rating_5based`) to `toSearchableArray()`.
- Keep `config/scout.php` index settings aligned and re-import indexes.
- Because filterable/sortable settings alone are not enough; the fields must exist in indexed documents.

**If pinning remains a distinct UX bucket (recommended):**
- Use `pin_rank` for 1..5 and separate `sort_order` for the rest.
- Because the “max 5 pinned” rule stays explicit and enforceable.

**If pins are treated as “first 5 in custom order”:**
- Use only `sort_order` and derive pins from the first 5 visible categories.
- Because schema is smaller, but semantics become less explicit and future UI changes get harder.

## Query / Filtering Concerns

- **Hidden != ignored.** Hidden removes categories from navigation only. Ignored excludes matching titles from browse, full search, and lightweight autocomplete.
- **Do not keep filter state inside `q`.** The current `search.tsx` magic-word approach (`type:movie`, `sort:latest`) is the wrong abstraction for this milestone. Keep canonical state in explicit query params and let the input be plain search text.
- **Current Scout payloads are incomplete.** `config/scout.php` already marks `category_id` filterable for Meilisearch, but `VodStream::toSearchableArray()` and `Series::toSearchableArray()` currently omit `category_id` and some sort keys. Fix that first.
- **Use one reusable ignore constraint per surface.** One Eloquent scope for browse pages; one Scout builder helper for search/autocomplete.
- **Selected ignored category edge case:** if a user deep-links into a category they ignore, clear the selected category and show a flash/banner; do not return contradictory empty state while the same titles remain searchable elsewhere.

## Testing Additions

- Extend existing feature suites (`MoviesCategoryBrowseTest`, `SeriesCategoryBrowseTest`, `SearchControllerTest`) instead of introducing a new test harness.
- Add feature coverage for:
  - user-specific reorder/pin/hide persistence
  - max 5 pinned validation
  - hidden categories absent from nav but still visible in detail labels when applicable
  - ignored categories excluded from movie/series index queries
  - ignored categories excluded from full search and lightweight search
  - explicit query-param search filters (`media_type`, `sort_by`) with no magic-word coupling
- Add browser coverage for:
  - desktop reorder + pin/hide save
  - mobile sheet category search + drag interaction
  - search media-type filter regression on full-width filtered results

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| `cmdk@1.1.1` | React 19.1 / React DOM 19.1 | Latest line peers `^18 || ^19`; installed `cmdk@1.0.0` peers React 18 only. |
| `@dnd-kit/sortable@10.0.0` | `@dnd-kit/core@^6.3.0` | Keep these lines paired. |
| `pest-plugin-browser@4.3.0` | `pest@4.4.x`, Playwright 1.54.1 | Matches current repo well enough; no JS test runner addition needed. |
| Scout `whereNotIn` filtering | Meilisearch `filterableAttributes` + indexed field presence | `category_id` must exist in searchable payload, not only in `config/scout.php`. |

## Sources

- Local repo: `package.json`, `composer.json`, `config/scout.php`, `resources/js/components/category-sidebar.tsx`, `app/Models/VodStream.php`, `app/Models/Series.php`, `app/Http/Controllers/SearchController.php`, `app/Http/Controllers/LightweightSearchController.php`, `tests/Feature/Controllers/SearchControllerTest.php`, `tests/Feature/Discovery/MoviesCategoryBrowseTest.php`, `tests/Feature/Discovery/SeriesCategoryBrowseTest.php`
- https://docs.dndkit.com/presets/sortable — sortable preset, sensors, overlay guidance
- https://docs.dndkit.com/api-documentation/modifiers — vertical-axis and scroll-container modifiers
- https://laravel.com/docs/12.x/scout — `toSearchableArray`, `where`, `whereIn`, `whereNotIn`, Meilisearch index settings
- https://www.meilisearch.com/docs/learn/filtering_and_sorting/filter_search_results — `filterableAttributes` requirement
- https://github.com/pacocoursey/cmdk — accessible combobox/command API; manual/custom filtering support
- `npm view` on 2026-03-15 — verified versions for `@dnd-kit/core` 6.3.1, `@dnd-kit/sortable` 10.0.0, `@dnd-kit/modifiers` 9.0.0, `cmdk` 1.1.1, `@hello-pangea/dnd` 18.0.1, `react-beautiful-dnd` deprecation and peer range
- `composer show --all` on 2026-03-15 — verified installed/latest lines for `laravel/scout`, `meilisearch/meilisearch-php`, `pestphp/pest`, `pestphp/pest-plugin-browser`

---
*Stack research for: per-user category personalization and search UX on Laravel + Inertia streaming app*
*Researched: 2026-03-15*
