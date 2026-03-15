# Phase 8: Personal Category Controls - Research

**Researched:** 2026-03-15
**Domain:** Per-user category personalization in a Laravel 12 + Inertia React browse flow
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Management surface
- Personalization uses a browse-attached hybrid surface, not a settings-only flow.
- Daily editing happens in the existing category browse UI, with full controls available there.
- Category labels continue to navigate; edit actions use separate controls on each row.
- Row controls are revealed on hover/tap rather than always visible at full density.
- A browse-attached manage surface exists for heavier bulk management sessions.
- Reset-to-default lives inside that manage surface, per media type.

#### Ordering and pinning rules
- Movies and series keep fully separate preference sets.
- Pinned categories stay above non-pinned categories.
- Users can manually order categories within the pinned group.
- Users can manually order categories within the non-pinned group.
- `All categories` stays fixed at the top and is not user-customizable.
- `Uncategorized` stays fixed at the bottom and is not user-customizable.
- Zero-item categories remain editable in place even if they stay disabled for navigation.
- Trying to pin a sixth category is blocked with a clear explanation; no automatic replacement.
- When a pinned category is unpinned, it returns to the user's manual non-pinned order.
- When sync introduces a new category later, it appears at the end of the non-pinned list by default.

#### Hidden category recovery
- Hidden categories are removed from the main visible navigation list but remain available in a dedicated hidden section.
- If a user hides every visible category for a media type, show both a reset action and the hidden section so they can selectively recover.
- Hiding the category currently being viewed does not immediately force navigation away from the current results.
- Hidden-category direct URLs and history entries still render; the page should show a banner explaining that the category is hidden for this user.

#### Mobile editing flow
- Mobile editing stays inside the existing category sheet rather than moving to a separate page.
- The mobile sheet includes a dedicated manage view for editing.
- Reordering on mobile uses drag handles in that manage view.
- Hidden categories appear in the same sheet inside a collapsed section.
- Mobile changes save instantly; closing the sheet is not the save step.

### OpenCode's Discretion
- Exact copy for pin-limit feedback, hidden-category banner text, and reset/recovery messaging.
- Exact visual treatment for hover/tap-revealed row controls on desktop.
- Exact presentation of the browse-attached bulk-management surface as long as it stays attached to the browse flow.

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PERS-01 | User can keep separate category preferences for movies and series | Use `media_type` in the preference key and scope every read/write/reset by media type. |
| PERS-02 | User can reorder visible categories for a media type and see that order persist across sessions | Persist per-user ranks in DB, not client state; use drag reorder UI backed by server writes and Inertia partial reloads. |
| PERS-03 | User can pin up to 5 categories per media type and pinned categories stay above non-pinned categories | Store separate pin rank, enforce max-5 in request/action layer, render pinned and non-pinned groups separately. |
| PERS-04 | User can hide a category from sidebar or navigation for a media type without affecting other users | Keep hide state in a user overlay table and build visible/hidden sections per user without touching shared `categories`. |
| PERS-05 | User can reset category preferences for a media type back to the default synced order and visibility | Implement media-type-scoped reset by deleting that user/media-type overlay set and rebuilding from canonical category order. |
</phase_requirements>

## Summary

Phase 8 should be planned as a server-authoritative overlay on top of the existing shared `categories` taxonomy. Do not mutate `categories` for per-user behavior. Add a separate `user_category_preferences` persistence layer keyed by `{user_id, media_type, category_provider_id}` and keep the existing browse pages, controllers, and `BuildCategorySidebarItems` read path as the integration seam. The safest shape is separate ranks for pinned vs non-pinned ordering plus a hidden flag; a single shared sort column is not enough to satisfy “unpinned returns to prior non-pinned order”.

Frontend scope should stay inside `resources/js/components/category-sidebar.tsx` and the existing mobile sheet. Use a browse-attached manage mode, not a new settings page. For reordering, the current stack does not include drag-and-drop primitives, so the standard addition is `@dnd-kit/*`; its official docs cover keyboard/pointer sensors, vertical sortable lists, and drag-overlay pitfalls. Keep navigation clicks and edit controls separate because zero-item categories must remain editable even when navigation is disabled.

Testing can stay mostly at Pest feature level for persistence, ordering, isolation, hidden-category recovery, and reset semantics. Browser coverage for drag behavior is optional and likely secondary; current infra exists, but drag E2E will be slower and more brittle than feature tests. Existing browse foundation tests already pass and confirm the baseline ordering/query behavior this phase must preserve.

**Primary recommendation:** Implement a user-scoped overlay table with `pin_rank`, `sort_order`, and `is_hidden`, then replace/wrap `BuildCategorySidebarItems` with a personalized builder consumed through existing Inertia partial reload patterns.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | `^12.0` | Migrations, validation, transactions, Eloquent persistence | Already the app backend; official `upsert` + `DB::transaction()` cover the write path cleanly. |
| inertiajs/inertia-laravel + `@inertiajs/react` | `^2.0` / `^2.0.9` | Page props, partial reloads, mutation visits | Existing browse pages already use `only`, `preserveState`, cancel tokens, and lazy prop closures. |
| React | `^19.1.0` | Browse-attached manage UI | Current frontend runtime. |
| Spatie Laravel Data + TS transformer | `^4.14` / `^2.5` | DTO contracts and generated TS types | Existing app convention; avoids prop/type drift as sidebar payload grows. |
| `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` | add current compatible versions | Drag reorder for pinned and non-pinned lists | Official sortable preset is the current standard for React list reordering with keyboard + pointer support. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@radix-ui/react-dialog` via local `Sheet` wrapper | `^1.1.13` | Existing mobile category sheet/manage surface | Reuse for mobile manage mode; do not fork to a new page. |
| `@radix-ui/react-collapsible` via local wrapper | `^1.1.10` | Hidden category recovery section | Use for collapsed hidden section in desktop/mobile manage views. |
| lucide-react | `^0.475.0` | Pin/hide/reset/drag affordance icons | Use for row action affordances; already standard in repo. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Relational `user_category_preferences` overlay | JSON on `users` | Simpler write path, but worse queryability, harder merge logic, and easy drift from server truth. |
| `@dnd-kit/*` | Native HTML5 drag/drop | Less dependency surface, but much worse touch/keyboard behavior and more custom edge-case code. |
| Generated DTO types | Manual TS interface mirroring | Faster initial edits, but current repo already has generated type infrastructure and manual duplication will drift. |

**Installation:**
```bash
bun add @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
```

## Architecture Patterns

### Recommended Project Structure
```text
app/
├── Actions/
│   ├── BuildPersonalizedCategorySidebar.php
│   └── SaveUserCategoryPreferences.php
├── Data/
│   ├── CategorySidebarData.php
│   └── CategorySidebarItemData.php
├── Http/
│   ├── Controllers/Preferences/CategoryPreferenceController.php
│   └── Requests/Preferences/UpdateCategoryPreferencesRequest.php
├── Models/UserCategoryPreference.php
database/migrations/
└── xxxx_xx_xx_xxxxxx_create_user_category_preferences_table.php
resources/js/
├── components/category-sidebar.tsx
└── types/generated.d.ts
tests/Feature/Discovery/
├── MoviesPersonalCategoryControlsTest.php
└── SeriesPersonalCategoryControlsTest.php
```

### Pattern 1: User-scoped overlay on shared categories
**What:** keep `categories` canonical; store only per-user overrides in a separate table keyed by user + media type + provider id.

**When to use:** every reorder/pin/hide/reset write and every personalized sidebar read.

**Example:**
```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($rows): void {
    UserCategoryPreference::query()->upsert(
        $rows,
        ['user_id', 'media_type', 'category_provider_id'],
        ['pin_rank', 'sort_order', 'is_hidden', 'updated_at'],
    );
}, attempts: 5);
```
Source: https://laravel.com/docs/12.x/database#database-transactions and https://laravel.com/docs/12.x/queries#upserts

### Pattern 2: Separate pinned and non-pinned ranks
**What:** persist `pin_rank` and `sort_order` separately. Keep `sort_order` stable even while a category is pinned so unpin can restore its previous non-pinned position.

**When to use:** whenever UI allows pin/unpin plus reordering inside each group.

**Example:**
```php
$visibleItems = $items
    ->reject(fn ($item) => $item->isHidden)
    ->partition(fn ($item) => $item->isPinned);

$pinned = $visibleItems[0]->sortBy('pinRank')->values();
$unpinned = $visibleItems[1]->sortBy('sortOrder')->values();

$ordered = $pinned->concat($unpinned);
```
Source: repo constraints from `.planning/phases/08-personal-category-controls/08-CONTEXT.md`

### Pattern 3: Server-authoritative sidebar + Inertia partial reloads
**What:** write preferences via PATCH/DELETE endpoints, then refresh only sidebar-related props through Inertia.

**When to use:** pin, unpin, hide, unhide, reorder, reset, and recovery flows inside browse pages.

**Example:**
```typescript
router.patch(route('category-preferences.update', { mediaType }), payload, {
    only: ['categories', 'filters'],
    preserveState: true,
    preserveScroll: true,
});
```
Source: https://inertiajs.com/manual-visits and https://inertiajs.com/partial-reloads

### Pattern 4: Two sortable contexts, not one flat list
**What:** pinned and non-pinned groups should be independently reorderable. Hidden categories belong in a separate recovery section, not in the same sortable context.

**When to use:** manage mode on desktop and mobile.

**Example:**
```tsx
<DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
    <SortableContext items={pinnedIds} strategy={verticalListSortingStrategy}>
        {renderPinnedRows()}
    </SortableContext>
    <SortableContext items={unpinnedIds} strategy={verticalListSortingStrategy}>
        {renderUnpinnedRows()}
    </SortableContext>
</DndContext>
```
Source: https://docs.dndkit.com/presets/sortable

### Anti-Patterns to Avoid
- **Per-user fields on `categories`:** leaks state across users and risks sync overwrites.
- **LocalStorage/session as source of truth:** fails PERS-02/PERS-05 and breaks multi-device persistence.
- **One flat sortable list including `All categories` / `Uncategorized`:** violates locked fixed-position behavior.
- **Using current `disabled` flag to disable the whole row:** zero-item categories must still expose edit controls.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Drag reorder | Native HTML5 drag/drop + custom touch/keyboard handling | `@dnd-kit/*` | Official sortable preset already solves sensors, keyboard coordinates, transforms, and overlay behavior. |
| Hidden recovery section | Custom disclosure state machine | Existing Radix `Collapsible` wrapper | Already in repo, accessible, low-risk. |
| Client-side page prop merging | Manual `history.pushState`/local cache sync | Inertia `router.patch`, `router.reload`, `only`, lazy closures | Existing app already uses this pattern; keeps server truth canonical. |
| TS contract sync | Hand-edited `generated.d.ts` or duplicate interfaces | `php artisan typescript:transform` | Repo already standardizes PHP DTO → TS generation. |

**Key insight:** this phase looks UI-heavy, but the real risk is persistence and merge semantics. Reuse battle-tested primitives for drag, disclosure, and prop reloads; spend custom logic only on the preference overlay rules.

## Common Pitfalls

### Pitfall 1: Missing the composite unique index required for safe upserts
**What goes wrong:** writes duplicate preference rows or update the wrong row.

**Why it happens:** Laravel `upsert` relies on primary/unique indexes, and MySQL/MariaDB ignore the `uniqueBy` argument unless an actual unique index exists.

**How to avoid:** create a unique index on `['user_id', 'media_type', 'category_provider_id']` before using `upsert`.

**Warning signs:** duplicate rows after repeated pin/hide operations; MySQL behavior differing from SQLite tests.

### Pitfall 2: Single-rank ordering breaks unpin restore behavior
**What goes wrong:** unpinned categories jump to the wrong place or lose their prior non-pinned order.

**Why it happens:** pinning and non-pinned ordering are distinct concerns; one `sort_order` cannot model both safely.

**How to avoid:** store `pin_rank` separately from non-pinned `sort_order`; never overwrite non-pinned order when pinning.

**Warning signs:** “unpin” moves a category to the bottom or to its last pinned position.

### Pitfall 3: Reusing `disabled` as “not editable”
**What goes wrong:** zero-item categories cannot be pinned/hidden/recovered even though the decision says they remain editable.

**Why it happens:** current sidebar treats `disabled` as a disabled button state.

**How to avoid:** split row semantics into at least `canNavigate` and `canEdit`; keep controls active even when navigation is blocked.

**Warning signs:** edit icons disappear for zero-item categories or become inert.

### Pitfall 4: Including fixed pseudo-categories in persistence
**What goes wrong:** `All categories` or `Uncategorized` become hideable/reorderable.

**Why it happens:** the current UI prepends/appends them at render time, so it is easy to accidentally serialize them with normal categories.

**How to avoid:** exclude `All categories` and system uncategorized ids from write payloads and preference rows.

**Warning signs:** reset payloads contain `all-categories` or `__uncategorized_*__` ids.

### Pitfall 5: Drag overlay / scroll bugs in sortable manage views
**What goes wrong:** dragged rows jitter, clip, or collide with their own ids in scrollable sheets.

**Why it happens:** sortable lists inside scrollable containers often need overlay handling; dnd-kit documents id-collision pitfalls when reusing the sortable component inside `DragOverlay`.

**How to avoid:** use dnd-kit’s recommended `DragOverlay` pattern if the manage surface scrolls; render a presentational row inside the overlay, not the sortable hook component itself.

**Warning signs:** duplicate active ids, clipped drag preview, or broken drag in long mobile sheets.

## Code Examples

Verified patterns from official sources:

### Vertical sortable list with keyboard + pointer support
```tsx
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';

const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
        coordinateGetter: sortableKeyboardCoordinates,
    }),
);

<DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
    <SortableContext items={items} strategy={verticalListSortingStrategy}>
        {items.map((id) => (
            <SortableRow key={id} id={id} />
        ))}
    </SortableContext>
</DndContext>;
```
Source: https://docs.dndkit.com/presets/sortable

### Inertia partial mutation + same-page reload semantics
```tsx
router.patch(route('category-preferences.update', { mediaType }), payload, {
    only: ['categories', 'filters'],
    preserveState: true,
    preserveScroll: true,
    onFinish: () => setSaving(false),
});

router.reload({ only: ['categories', 'filters'] });
```
Source: https://inertiajs.com/manual-visits and https://inertiajs.com/partial-reloads

### Transactional upsert for preference rows
```php
DB::transaction(function () use ($rows): void {
    UserCategoryPreference::query()->upsert(
        $rows,
        ['user_id', 'media_type', 'category_provider_id'],
        ['pin_rank', 'sort_order', 'is_hidden', 'updated_at'],
    );
}, attempts: 5);
```
Source: https://laravel.com/docs/12.x/database#database-transactions and https://laravel.com/docs/12.x/queries#upserts

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Shared global A–Z sidebar only | Server-built per-user overlay on shared taxonomy | This phase / v1.1 | Personalization without mutating canonical category data. |
| Ad hoc drag/drop or no drag support | `@dnd-kit/*` sortable contexts with keyboard + pointer sensors | Current React ecosystem | Accessible reorder support in desktop + mobile manage surfaces. |
| Manual TS sidebar interfaces | Generated DTO contracts via `typescript:transform` | Already established in repo | Safer payload expansion for hidden/banner/manage metadata. |

**Deprecated/outdated:**
- Storing personalization on `categories`.
- Treating client-only state as persistence.
- Serializing fixed rows (`All categories`, `Uncategorized`) as normal preferences.

## Open Questions

1. **Exact write contract: snapshot vs semantic action payloads**
   - What we know: server must preserve separate pinned and non-pinned ranks.
   - What's unclear: whether one PATCH payload should send full state or discrete actions.
   - Recommendation: decide this first in planning; either is viable, but the action/service layer must remain semantic and media-type scoped.

2. **Sidebar prop shape: extend array or introduce wrapper DTO**
   - What we know: hidden section, reset affordance, and hidden-selected banner metadata exceed the current flat array.
   - What's unclear: whether to keep `categories` plus extra props or replace it with a wrapper DTO.
   - Recommendation: prefer a wrapper DTO if more than two new sibling props are needed.

3. **How much browser drag coverage is worth it**
   - What we know: Pest Browser exists, but drag tests are typically slower and more brittle than feature tests.
   - What's unclear: whether the team wants automated drag E2E now or manual smoke plus feature tests.
   - Recommendation: make feature tests the gate; add browser smoke only if implementation risk stays high after feature coverage.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest `^4.4` + PHPUnit + pest-plugin-browser `^4.3` |
| Config file | `phpunit.xml` |
| Quick run command | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php -x` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PERS-01 | Separate movie vs series preference sets per user | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-01 -x` | ❌ Wave 0 |
| PERS-02 | Reorder visible categories and persist across refresh/session | feature + manual drag smoke | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-02 -x` | ❌ Wave 0 |
| PERS-03 | Pin max 5 and keep pinned group above non-pinned | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-03 -x` | ❌ Wave 0 |
| PERS-04 | Hide category per user/media type without affecting others | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-04 -x` | ❌ Wave 0 |
| PERS-05 | Reset one media type to default order/visibility only | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-05 -x` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php -x`
- **Per wave merge:** `php artisan test`
- **Phase gate:** `php artisan typescript:transform && php artisan test && bun run lint && bun run build`

### Wave 0 Gaps
- [ ] `tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php` — movie personalization persistence, ordering, hide, reset
- [ ] `tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php` — series personalization persistence, ordering, hide, reset
- [ ] `app/Models/UserCategoryPreference.php` — persistence model for test factories/helpers
- [ ] `database/migrations/*create_user_category_preferences_table.php` — schema with composite unique index for upsert path
- [ ] `php artisan typescript:transform` in verification steps whenever DTO shape changes

## Sources

### Primary (HIGH confidence)
- Repo inspection: `app/Actions/BuildCategorySidebarItems.php`, `app/Http/Controllers/VodStream/VodStreamController.php`, `app/Http/Controllers/Series/SeriesController.php`, `resources/js/components/category-sidebar.tsx`, `resources/js/pages/movies/index.tsx`, `resources/js/pages/series/index.tsx` — current browse foundation and integration points
- Repo inspection: `app/Data/CategorySidebarItemData.php`, `config/typescript-transformer.php`, `resources/js/types/generated.d.ts` — DTO/type generation conventions
- Repo inspection: `resources/js/components/ui/sheet.tsx`, `resources/js/components/ui/collapsible.tsx`, `resources/js/components/ui/command.tsx` — reusable mobile/manage/recovery primitives
- Repo inspection: `tests/Pest.php`, `phpunit.xml`, `tests/Feature/Discovery/MoviesCategoryBrowseTest.php`, `tests/Feature/Discovery/SeriesCategoryBrowseTest.php` — existing test stack and browse guarantees
- `./vendor/bin/pest tests/Feature/Discovery/MoviesCategoryBrowseTest.php tests/Feature/Discovery/SeriesCategoryBrowseTest.php` executed 2026-03-15 — 14 passing tests, baseline confirmed
- https://inertiajs.com/manual-visits — mutation methods, preserveState defaults, cancel tokens, reload semantics
- https://inertiajs.com/partial-reloads — `only`, same-page partial reloads, lazy closure props
- https://laravel.com/docs/12.x/database#database-transactions — transactional write pattern and deadlock retry support
- https://laravel.com/docs/12.x/queries#upserts — `upsert` behavior and unique-index requirement/MySQL caveat
- https://docs.dndkit.com/presets/sortable — sortable contexts, sensors, strategies, drag overlay pitfalls
- https://www.radix-ui.com/primitives/docs/components/collapsible — accessible hidden-section primitive

### Secondary (MEDIUM confidence)
- `.planning/research/STACK.md` and `.planning/research/ARCHITECTURE.md` — prior internal synthesis aligned with current codebase direction

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - mostly repo-confirmed plus official Inertia/Laravel/dnd-kit/Radix docs
- Architecture: MEDIUM - schema shape and endpoint contract are inferred from requirements, though the constraints are clear
- Pitfalls: HIGH - derived from locked decisions, current code seams, and official library caveats

**Research date:** 2026-03-15
**Valid until:** 2026-04-14
