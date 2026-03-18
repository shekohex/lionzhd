# Phase 9: Ignored Discovery Filters - Research

**Researched:** 2026-03-18
**Domain:** Laravel + Inertia per-user discovery filtering and recovery UX
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Ignore controls
- Ignore stays browse-attached, not in a separate settings flow.
- Desktop browse rows expose ignore as a quick row action alongside the existing pin/hide actions.
- Mobile keeps ignore inside the existing category-sheet manage view rather than adding more controls to the browse picker.
- Hide and ignore remain separate controls with separate meanings.

#### Ignored navigation treatment
- Ignored categories remain visible in navigation rather than moving to a hidden-only recovery surface.
- Ignored categories stay selectable from the main browse list.
- Ignored categories use a muted visual treatment, not badge-heavy styling.
- Ignored categories sort to the bottom of the main visible list instead of preserving their prior position.

#### Ignored direct URL behavior
- If a user lands on an ignored category URL, keep them on that category instead of redirecting to `All categories`.
- Ignored-category URLs show a recovery state on that category rather than showing ignored titles.
- The ignored category remains selected in navigation while that recovery state is shown.
- If the user unignores from that recovery state, the same category page should immediately restore its normal results.

#### Empty-state recovery
- On an ignored category page with no results, the primary recovery action is to unignore that category.
- If `All categories` is empty because ignores filtered everything out, the main recovery action is to open/manage ignored categories.
- When hidden and ignored preferences both contribute to emptiness, the message should explicitly mention both causes.
- Full reset is available as a secondary escape hatch, not the primary recovery action.

### OpenCode's Discretion
- Exact copy for ignore actions, recovery messaging, and mixed hidden/ignored empty states.
- Exact muted styling and iconography for ignored rows as long as they remain visibly distinct from normal and hidden categories.
- Exact placement/presentation of the secondary full-reset action within browse/manage recovery surfaces.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| IGNR-01 | User can ignore a category for a media type so matching titles are excluded from catalog listings for that user | Extend `UserCategoryPreference` + snapshot request/save flow with `ignored_ids` / `is_ignored`, then apply ignored-category exclusion inside `VodStreamController@index` and `SeriesController@index` browse queries only |
| IGNR-02 | User gets a recovery path when hidden or ignored preferences leave no visible categories or results | Extend sidebar/page DTOs with ignored-state metadata, keep ignored rows visible + muted, and drive recovery through existing browse-attached sidebar/manage surfaces plus `EmptyState` actions |
</phase_requirements>

## Summary

Phase 9 should be implemented as an extension of the Phase 8 personalization overlay, not as a new subsystem. The repo already has the right seams: `UserCategoryPreference` stores per-user, per-media-type category state; `SaveUserCategoryPreferences` persists a full snapshot transactionally; `BuildPersonalizedCategorySidebar` shapes browse/manage UI state; movie and series browse controllers already centralize category filtering; and the React browse pages already render empty/recovery states from server props.

The clean plan is to add ignored state to that same preference snapshot, keep ignored categories visible in navigation, and apply exclusion only in discovery browse queries. Do not use global model scopes, do not reuse `is_hidden` to mean ignore, and do not redirect ignored direct URLs back to all categories. Recovery should stay in-context: ignored category pages should offer unignore as the primary action, and empty `All categories` should point users back into the existing manage surface with reset as a secondary escape hatch.

**Primary recommendation:** Add `is_ignored` to `user_category_preferences`, thread `ignored_ids` through the existing snapshot/update/sidebar/read pipeline, and drive recovery from server-side browse metadata plus the existing `EmptyState` and category manage UI.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | ^8.4 | Backend runtime | Repo baseline; all discovery controllers/actions/tests already target it |
| laravel/framework | ^12.0 | Controllers, FormRequests, Eloquent, migrations, pagination | Existing discovery flow and preference persistence already live here |
| inertiajs/inertia-laravel | ^2.0 | Server-side Inertia responses and partial props | Current browse pages already depend on lazy props + partial reloads |
| @inertiajs/react | ^2.0.9 | Client navigation/mutation flow | `use-category-browser.ts` already uses `router.visit`, `patch`, `delete`, `reload` |
| spatie/laravel-data | ^4.14 | DTO contract for sidebar/filter props | Existing `CategorySidebarData` / `CategoryBrowseFiltersData` already define the frontend contract |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| spatie/laravel-typescript-transformer | ^2.5 | TS types from PHP DTOs | When DTO shape changes for ignored-state metadata |
| React | ^19.1.0 | Browse/sidebar UI | For page empty states and browse/manage interactions |
| lucide-react | ^0.475.0 | Row-action and recovery icons | For muted ignore affordance consistent with existing pin/hide actions |
| @dnd-kit/core / sortable | ^6.3.1 / ^10.0.0 | Existing manage-mode reordering | Reuse in manage mode; do not replace just to add ignored state |
| Pest | ^4.4 | Feature/browser test runner | Existing discovery and sidebar tests already cover the relevant seams |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Extend `user_category_preferences` | New `user_ignored_categories` table | New table duplicates existing per-user/media/category keying and complicates reset/snapshot semantics |
| Server-side browse exclusion in controllers | Client-only filtering after response | Client filtering breaks pagination counts, empty-state truth, and direct URL recovery |
| Extend `category-preferences.update` snapshot | Dedicated ignore/unignore endpoint | Separate endpoint creates two persistence contracts for one preference model |

**Installation:** No new packages required.

## Architecture Patterns

### Recommended Project Structure
```text
app/
├── Actions/
│   ├── BuildPersonalizedCategorySidebar.php
│   └── SaveUserCategoryPreferences.php
├── Data/
│   ├── CategoryBrowseFiltersData.php
│   ├── CategorySidebarData.php
│   └── CategorySidebarItemData.php
├── Http/
│   ├── Controllers/
│   │   ├── Preferences/CategoryPreferenceController.php
│   │   ├── Series/SeriesController.php
│   │   └── VodStream/VodStreamController.php
│   └── Requests/Preferences/UpdateCategoryPreferencesRequest.php
resources/js/
├── components/category-sidebar/
│   ├── browse.tsx
│   └── manage.tsx
├── hooks/use-category-browser.ts
└── pages/
    ├── movies/index.tsx
    └── series/index.tsx
tests/Feature/
├── Actions/BuildPersonalizedCategorySidebarTest.php
├── Controllers/CategoryPreferenceControllerTest.php
└── Discovery/
    ├── MoviesPersonalCategoryControlsTest.php
    └── SeriesPersonalCategoryControlsTest.php
```

### Pattern 1: Extend the existing preference snapshot
**What:** Treat ignore as another field on the same per-user/media/category preference row and the same PATCH snapshot contract.
**When to use:** Any browse-attached mutation that changes category personalization state.

**Source:** `app/Actions/SaveUserCategoryPreferences.php`, `app/Http/Requests/Preferences/UpdateCategoryPreferencesRequest.php`
```php
$snapshotIds = array_values(array_unique(array_merge($visibleIds, $hiddenIds)));

foreach ($snapshotIds as $categoryProviderId) {
    $rows[] = [
        'user_id' => $user->id,
        'media_type' => $mediaType->value,
        'category_provider_id' => $categoryProviderId,
        'pin_rank' => ($rank = array_search($categoryProviderId, $pinnedIds, true)) === false ? null : $rank + 1,
        'sort_order' => $sortOrderMap[$categoryProviderId],
        'is_hidden' => array_key_exists($categoryProviderId, $hiddenLookup),
        'created_at' => $existingPreference?->created_at ?? $timestamp,
        'updated_at' => $timestamp,
    ];
}

UserCategoryPreference::query()->upsert(
    $rows,
    ['user_id', 'media_type', 'category_provider_id'],
    ['pin_rank', 'sort_order', 'is_hidden', 'updated_at'],
);
```

### Pattern 2: Apply ignore in browse queries, not model globals
**What:** Keep ignore logic inside `movies` / `series` index actions so the filter only affects discovery listings.
**When to use:** Catalog browse reads for movies and series.

**Source:** `app/Http/Controllers/VodStream/VodStreamController.php`, `app/Http/Controllers/Series/SeriesController.php`
```php
$moviesQuery = VodStream::query()
    ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
        $query->where('user_id', $user->id);
    }])
    ->when($categoryId !== null, function (Builder $query) use ($categoryId): void {
        if ($categoryId === Category::UNCATEGORIZED_VOD_PROVIDER_ID) {
            $query->where(static function (Builder $innerQuery) use ($categoryId): void {
                $innerQuery
                    ->whereNull('category_id')
                    ->orWhere('category_id', '')
                    ->orWhere('category_id', $categoryId);
            });

            return;
        }

        $query->where('category_id', $categoryId);
    });
```

### Pattern 3: Keep recovery browse-attached with partial reloads
**What:** Reuse the existing sidebar/manage and page empty-state flow; mutate preferences through Inertia partial reloads instead of navigating away.
**When to use:** Ignore/unignore actions, manage-surface recovery, reset fallback.

**Source:** `resources/js/hooks/use-category-browser.ts`, `https://inertiajs.com/partial-reloads`, `https://inertiajs.com/manual-visits`
```ts
router.patch(
    route('category-preferences.update', { mediaType }),
    {
        pinned_ids: payload.pinnedIds,
        visible_ids: payload.visibleIds,
        hidden_ids: payload.hiddenIds,
    },
    {
        only: ['categories', 'filters'],
        preserveState: true,
        preserveScroll: true,
    },
)
```

### Anti-Patterns to Avoid
- **Reusing `is_hidden` as ignore:** Hidden removes rows from navigation; ignored must remain visible and selectable.
- **Global model scopes for ignore:** Would incorrectly affect search, detail pages, watchlist, and admin reads.
- **Client-only ignore filtering:** Breaks paginator totals, empty-state truth, and direct ignored-category URL behavior.
- **Redirecting ignored direct URLs to `All categories`:** Violates locked phase decisions.
- **Clearing sort/pin metadata on ignore:** Makes unignore recovery feel destructive and inconsistent.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Per-user ignore persistence | A parallel ignored-categories subsystem | Extend `UserCategoryPreference` | Existing row already scopes by user + media type + category and already resets transactionally |
| Frontend prop contracts | Ad hoc arrays assembled in controllers and TS files | Extend `CategorySidebarData` / `CategorySidebarItemData` / `CategoryBrowseFiltersData` | Repo already uses Spatie data + TS transform for typed backend/frontend parity |
| Recovery UI shell | New settings page or modal flow | Existing sidebar browse/manage surfaces + `EmptyState` | Locked decisions require browse-attached recovery |
| Catalog filtering | Manual JS post-filtering | SQL/Eloquent query constraints in browse controllers | Server-side filtering preserves pagination, counts, and empty-state correctness |

**Key insight:** Ignore is a new state on the existing personalization overlay, not a new domain model.

## Common Pitfalls

### Pitfall 1: Conflating hidden and ignored
**What goes wrong:** Ignored categories disappear from navigation or act like hidden ones.
**Why it happens:** Current code only knows visible vs hidden, so it is tempting to overload `is_hidden`.
**How to avoid:** Add a distinct ignored flag and keep ignored items in `visibleItems` with a separate display sort/treatment.
**Warning signs:** Selected ignored category redirects away, disappears from sidebar, or loses direct recovery.

### Pitfall 2: Filtering too broadly
**What goes wrong:** Ignore leaks into search, detail pages, watchlist, or admin views.
**Why it happens:** Model scopes feel convenient.
**How to avoid:** Apply ignored-category exclusion only in `VodStreamController@index` and `SeriesController@index`.
**Warning signs:** Direct detail routes or future search endpoints stop showing ignored-category media.

### Pitfall 3: Losing recovery on empty results
**What goes wrong:** Users hit a blank grid with only a generic “no items” message.
**Why it happens:** Current empty state only differentiates selected category vs all categories, not hidden/ignored causes.
**How to avoid:** Ship explicit ignored/hidden recovery metadata from the server and render primary/secondary actions intentionally.
**Warning signs:** Empty `All categories` page still offers only “Show all categories” even when everything is ignored.

### Pitfall 4: Destroying stored order or pin semantics
**What goes wrong:** Unignoring a category returns it in the wrong place or loses its prior pinned state.
**Why it happens:** Ignore toggles mutate only the displayed groups and discard existing preference metadata.
**How to avoid:** Preserve `sort_order` and, if currently stored, `pin_rank`; let display logic override placement while ignored.
**Warning signs:** A previously pinned category comes back as an ordinary visible item after unignore.

### Pitfall 5: Under-validating snapshot overlaps
**What goes wrong:** A category ends up both hidden and ignored, or fixed rows become customizable.
**Why it happens:** Current request validation only reasons about pinned/visible/hidden lists.
**How to avoid:** Extend request validation to reject overlaps and continue disallowing `all-categories` / uncategorized ids.
**Warning signs:** Contradictory state arrives in the database or the UI shows impossible combinations.

## Code Examples

Verified patterns from current code and official docs:

### Snapshot persistence with transactional upsert
**Source:** `app/Actions/SaveUserCategoryPreferences.php`, `https://laravel.com/docs/12.x/eloquent#upserts`
```php
DB::transaction(function () use ($user, $mediaType, $snapshotIds, $rows): void {
    $query = UserCategoryPreference::query()
        ->where('user_id', $user->id)
        ->where('media_type', $mediaType->value);

    if ($snapshotIds === []) {
        $query->delete();

        return;
    }

    $query
        ->whereNotIn('category_provider_id', $snapshotIds)
        ->delete();

    UserCategoryPreference::query()->upsert(
        $rows,
        ['user_id', 'media_type', 'category_provider_id'],
        ['pin_rank', 'sort_order', 'is_hidden', 'updated_at'],
    );
}, attempts: 5);
```

### FormRequest normalization and extra validation
**Source:** `app/Http/Requests/Preferences/UpdateCategoryPreferencesRequest.php`, `https://laravel.com/docs/12.x/validation#form-request-validation`
```php
protected function prepareForValidation(): void
{
    $this->merge([
        'pinned_ids' => $this->input('pinned_ids', []),
        'visible_ids' => $this->input('visible_ids', []),
        'hidden_ids' => $this->input('hidden_ids', []),
    ]);
}

public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator): void {
        $overlap = array_values(array_intersect($visibleIds, $hiddenIds));

        if ($overlap !== []) {
            $validator->errors()->add('visible_ids', 'A category cannot be both visible and hidden in the same snapshot.');
            $validator->errors()->add('hidden_ids', 'A category cannot be both visible and hidden in the same snapshot.');
        }
    });
}
```

### Inertia partial reloads for browse-attached mutations
**Source:** `resources/js/hooks/use-category-browser.ts`, `https://inertiajs.com/partial-reloads`, `https://inertiajs.com/manual-visits`
```ts
router.delete(route('category-preferences.reset', { mediaType }), {
    only: ['categories', 'filters'],
    preserveState: true,
    preserveScroll: true,
    onError: () => {
        options?.onError?.(CATEGORY_PREFERENCE_ERROR_MESSAGE)
    },
    onFinish: () => {
        options?.onFinish?.()
    },
})
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Shared category taxonomy only | Per-user overlay preferences on shared taxonomy | Phase 08 / 2026-03-18 | Ignore should extend the overlay, not mutate `categories` |
| Hidden selected category keeps showing results with a banner | Ignored selected category should keep URL + selection but show recovery state with no results | Locked for Phase 09 | Hidden and ignored must diverge intentionally in server props and page UX |
| Sidebar state models visible + hidden only | Sidebar state now needs normal visible + ignored-visible + hidden semantics | Phase 09 | DTOs, snapshot payload, builder sorting, and page recovery metadata all expand |

**Deprecated/outdated:**
- Reusing hidden as the only “remove from browse” control: Phase 9 requires a second, distinct semantic.
- Redirecting any non-default restricted category back to `All categories`: ignored direct URLs must stay on-category.
- Treating recovery as reset-first: reset is explicitly secondary here.

## Open Questions

1. **Should ignored categories preserve their prior pin metadata while muted at the bottom?**
   - What we know: Current schema already preserves `pin_rank` and `sort_order`; direct unignore recovery should feel lossless.
   - What's unclear: Whether ignored rows should visibly expose remembered pin state in manage mode.
   - Recommendation: Preserve stored `pin_rank`/`sort_order`, but exclude ignored rows from the active pinned display and restore placement on unignore.

2. **Where should mixed hidden/ignored recovery metadata live?**
   - What we know: Pages already consume `filters`, `categories`, and `EmptyState`; hidden state already uses explicit top-level metadata.
   - What's unclear: Whether to derive mixed causes client-side from arrays or ship explicit booleans/counts.
   - Recommendation: Add explicit server props for ignored recovery state instead of duplicating inference logic in both movies and series pages.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest ^4.4 on PHPUnit (`pestphp/pest`, `phpunit.xml`) |
| Config file | `phpunit.xml`, `tests/Pest.php` |
| Quick run command | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Feature/Controllers/CategoryPreferenceControllerTest.php tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php` |
| Full suite command | `./vendor/bin/pest` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| IGNR-01 | Movie browse excludes ignored categories per user and keeps media-type isolation | feature + action | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php --filter=ignored` | ✅ |
| IGNR-01 | Series browse excludes ignored categories per user and keeps media-type isolation | feature + action | `./vendor/bin/pest tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php --filter=ignored` | ✅ |
| IGNR-02 | Ignored selected-category URL shows recovery state with primary unignore action | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=recovery` | ✅ |
| IGNR-02 | Empty `All categories` and mixed hidden/ignored states expose correct recovery affordances on desktop/mobile | browser/manual | `manual until a dedicated browser test is added` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Feature/Controllers/CategoryPreferenceControllerTest.php tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php`
- **Per wave merge:** `./vendor/bin/pest`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Browser/IgnoredDiscoveryFiltersTest.php` — covers desktop/mobile ignore affordances and in-context recovery CTA visibility

## Sources

### Primary (HIGH confidence)
- `/home/coder/project/lionzhd/.planning/phases/09-ignored-discovery-filters/09-CONTEXT.md` - locked product decisions and existing-code guidance
- `/home/coder/project/lionzhd/composer.json` - backend/test package versions
- `/home/coder/project/lionzhd/package.json` - frontend package versions
- `/home/coder/project/lionzhd/phpunit.xml` and `/home/coder/project/lionzhd/tests/Pest.php` - test framework and config
- `/home/coder/project/lionzhd/app/Actions/BuildPersonalizedCategorySidebar.php` - current sidebar assembly, ordering, hidden metadata
- `/home/coder/project/lionzhd/app/Actions/SaveUserCategoryPreferences.php` - transactional snapshot persistence pattern
- `/home/coder/project/lionzhd/app/Http/Requests/Preferences/UpdateCategoryPreferencesRequest.php` - current snapshot validation pattern
- `/home/coder/project/lionzhd/app/Http/Controllers/VodStream/VodStreamController.php` - movie browse read path
- `/home/coder/project/lionzhd/app/Http/Controllers/Series/SeriesController.php` - series browse read path
- `/home/coder/project/lionzhd/app/Data/CategorySidebarData.php`, `/home/coder/project/lionzhd/app/Data/CategorySidebarItemData.php`, `/home/coder/project/lionzhd/app/Data/CategoryBrowseFiltersData.php` - frontend data contract seams
- `/home/coder/project/lionzhd/resources/js/components/category-sidebar.tsx` - browse/manage state shell
- `/home/coder/project/lionzhd/resources/js/components/category-sidebar/browse.tsx` - desktop quick-row pattern
- `/home/coder/project/lionzhd/resources/js/components/category-sidebar/manage.tsx` - existing manage surface
- `/home/coder/project/lionzhd/resources/js/hooks/use-category-browser.ts` - Inertia mutation and partial reload pattern
- `/home/coder/project/lionzhd/resources/js/pages/movies/index.tsx` and `/home/coder/project/lionzhd/resources/js/pages/series/index.tsx` - existing hidden banner and empty-state recovery wiring
- `/home/coder/project/lionzhd/tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php` and `/home/coder/project/lionzhd/tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php` - current regression base for personalized browse behavior
- `/home/coder/project/lionzhd/tests/Feature/Controllers/CategoryPreferenceControllerTest.php` - current preference write/reset validation base
- `/home/coder/project/lionzhd/tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php` - current sidebar builder invariants
- `https://inertiajs.com/partial-reloads` - partial reload behavior and lazy server props
- `https://inertiajs.com/manual-visits` - router visit/reload state preservation behavior
- `https://laravel.com/docs/12.x/validation#form-request-validation` - form request normalization and extra validation hooks
- `https://laravel.com/docs/12.x/eloquent#upserts` - official upsert pattern referenced by existing save action
- `https://spatie.be/docs/laravel-data/v4/introduction` - DTO + TS-transform usage model

### Secondary (MEDIUM confidence)
- None.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - direct repo manifests plus official docs
- Architecture: HIGH - current Phase 8 code paths already implement almost all required seams
- Pitfalls: HIGH - derived from locked phase decisions, existing browse behavior, and verified current tests

**Research date:** 2026-03-18
**Valid until:** 2026-04-17
