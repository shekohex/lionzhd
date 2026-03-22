# Phase 12: Detail Page Category Context - Research

**Researched:** 2026-03-22
**Domain:** Laravel/Inertia detail-page category context over Xtream-synced movie/series catalog
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
## Implementation Decisions

### Placement and prominence
- Category context lives in the hero area on both movie and series detail pages.
- Categories render on their own chip row, separate from the existing genre chips.
- The row stays unlabeled; no visible `Categories` heading or icon is needed.
- Movie and series pages use the same placement pattern.

### Click-through behavior
- Category chips are clickable, not read-only.
- Clicking a chip opens the matching same-media browse page with that category selected.
- Category navigation stays in the same tab and follows normal in-app navigation.
- Hidden or ignored categories still navigate when clicked; the existing browse recovery/banner behavior handles those states.

### Ordering and state treatment
- Category chips use canonical synced category order, not alphabetical order and not the user's personalized navigation order.
- Hidden and ignored categories render neutrally like normal category chips.
- All assigned categories stay in one list; hidden or ignored categories are not grouped separately.
- Detail pages do not add extra copy, notes, or tooltips explaining hidden or ignored state.

### Overflow handling
- Desktop shows all assigned categories by default; chips wrap across lines as needed.
- There is no expander or `+N` collapse pattern by default.
- Mobile may use a more compact layout than desktop, but must still preserve access to the full assigned category set.
- Long category names should fit in the UI rather than truncate with ellipsis.

### OpenCode's Discretion
- Exact chip styling, spacing, and hover/focus treatment.
- Exact compact mobile layout, as long as it remains more space-efficient than desktop while preserving full category visibility.
- Exact animation/responsive details for the added hero row.

### Deferred Ideas (OUT OF SCOPE)
## Deferred Ideas

None - discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CTXT-01 | User can see all assigned categories on movie detail pages | Add a server-owned movie detail category-context prop, render it in `MediaHeroSection`, and link chips to `route('movies', { category })` while keeping hidden/ignored neutral. |
| CTXT-02 | User can see all assigned categories on series detail pages | Mirror the same prop/hero pattern for series, link to `route('series', { category })`, and keep browse recovery behavior on the destination page. |
</phase_requirements>

## Summary

This phase is mostly a read-contract + hero-integration problem, not a new UI system. The repo already has the right presentation seam (`resources/js/components/media-hero-section.tsx`), route targets (`movies` / `series` with `category` query), chip primitive (`Badge` with `asChild`), and hidden/ignored browse recovery semantics. Movies and series detail pages already pass hero-first props through shared patterns, so planner scope should stay symmetric.

The main planning risk is data, not rendering. Current detail controllers only send Xtream info DTOs plus watchlist/monitoring props. Current local media models (`vod_streams`, `series`) still expose a single `category_id`, and there is no normalized local assignment model in the repo for “all assigned categories”. There is also no explicit persisted canonical sync-order field on `categories`; current category builders sort alphabetically and/or by user preference state. If Phase 12 truly requires multiple assigned categories in canonical synced order, planner must account for that gap first.

**Primary recommendation:** Plan this phase as a shared server-owned detail-category prop added to both show controllers and rendered in `MediaHeroSection`, but put an explicit first task on defining the authoritative category-assignment source and canonical-order source before UI work.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^12.0 | show controllers, models, Inertia responses | Existing app backbone; all browse/detail reads already flow through Laravel controllers. |
| inertiajs/inertia-laravel + @inertiajs/react | ^2.0 / ^2.0.9 | server props + SPA navigation | Existing detail/browse/search surfaces already use Inertia end-to-end. |
| react + typescript | ^19.1.0 / ^5.8.3 | hero row rendering and strict page props | Existing detail pages and hero component are React+TS. |
| spatie/laravel-data + spatie/laravel-typescript-transformer | ^4.14 / ^2.5 | DTO-first prop contracts + generated TS types | Repo standard whenever PHP payload shape changes. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| tightenco/ziggy | ^2.4 | `route()` URLs for browse chip targets | Use for movie/series category browse links. |
| tailwindcss + local `Badge` primitive | ^4.1.6 | chip layout/styling | Use for neutral wrapped category chips in hero. |
| framer-motion | ^12.11.3 | hero row entrance consistency | Use only if the added category row should follow existing hero animation timing. |
| pestphp/pest + pest-plugin-browser | ^4.4 / ^4.3 | feature + browser validation | Use for Inertia prop assertions and click-through smoke coverage. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Dedicated local detail-category prop | Mutate Xtream `VodInformation` / `SeriesInformation` DTOs | Wrong layer; these DTOs are external transport models and currently do not own local browse/personalization semantics. |
| Canonical synced order source | Personalized sidebar `visibleItems` order | Violates locked requirement; `visibleItems` is user-specific and ignore-aware. |
| `Badge asChild` + Inertia `Link` | button + `router.visit()` | Less semantic for GET navigation; worse accessibility and less reusable with existing chip primitive. |

**Installation:**
```bash
# no new packages required for the primary implementation path
php artisan typescript:transform
```

## Architecture Patterns

### Recommended Project Structure
```text
app/
├── Actions/ResolveDetailPageCategories.php   # shared movie/series category-context builder
├── Data/DetailPageCategoryChipData.php       # PHP -> TS contract for hero chips
├── Http/Controllers/VodStream/VodStreamController.php
└── Http/Controllers/Series/SeriesController.php
resources/js/
├── components/media-hero-section.tsx         # shared hero chip row
├── pages/movies/show.tsx                     # movie consumer
├── pages/series/show.tsx                     # series consumer
└── types/                                    # page-prop aliases / generated.d.ts refresh
```

### Pattern 1: Server-owned detail category context prop
**What:** Build detail-page category chips on the server from local category data, then pass one shared prop shape to both movie and series detail pages.

**When to use:** Always. Do not ask the hero component to infer category meaning from Xtream DTOs.

**Example:**
```php
// Source: app/Http/Controllers/VodStream/VodStreamController.php, app/Http/Controllers/Series/SeriesController.php
return Inertia::render('movies/show', [
    'info' => $vod->dtoOrFail(),
    'in_watchlist' => $inWatchlist,
    'category_context' => DetailPageCategoryChipData::collect(
        app(ResolveDetailPageCategories::class)->forMovie($model)
    ),
]);
```

### Pattern 2: Link-backed hero chips
**What:** Render each category chip as a normal Inertia GET link inside the existing badge primitive.

**When to use:** For every navigable category chip on movie/series detail pages.

**Example:**
```tsx
// Source: https://inertiajs.com/links, resources/js/components/ui/badge.tsx
<Badge asChild variant="outline" className="bg-background/50 whitespace-normal break-words backdrop-blur-sm">
    <Link href={route('movies', { category: category.id })} preserveState={false} preserveScroll={false}>
        {category.name}
    </Link>
</Badge>
```

### Anti-Patterns to Avoid
- **Using `info.genre` or `info.categoryId` as the full answer:** current external DTOs only expose genre text and a single category id.
- **Reusing sidebar display order:** `BuildPersonalizedCategorySidebar` is personalized and ignore-aware; detail chips must not inherit that ordering.
- **Filtering hidden/ignored chips out of detail UI:** locked scope says render them neutrally and let browse recovery handle state.
- **Hard truncation or `+N` collapse by default:** violates the always-visible full-context requirement.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Detail-page category payload typing | Hand-edited `generated.d.ts` or duplicate TS interfaces | `#[TypeScript]` DTO + `php artisan typescript:transform` | Repo already standardizes PHP DTO -> TS generation. |
| Clickable chips | Custom button/link badge variant from scratch | Existing `Badge` with `asChild` + Inertia `Link` | Existing primitive already supports link composition and shared styling. |
| Hidden/ignored destination handling | New detail-page warnings, tooltips, or recovery banners | Existing browse-page recovery/banner behavior | Keeps semantics consistent with Phases 8-9. |
| Multi-category sourcing | Comma-splitting strings or stuffing JSON into `category_id` | Local normalized assignment data or an explicit canonical mapping source | Custom parsing will drift and cannot satisfy “all assigned categories” reliably. |

**Key insight:** this phase should reuse existing browse/navigation semantics and hero presentation primitives; the only thing that must be new is a trustworthy detail-page category read model.

## Common Pitfalls

### Pitfall 1: No authoritative source for “all assigned categories”
**What goes wrong:** detail pages end up showing one category, stale data, or ad hoc parsed data while the requirement says “all assigned categories”.

**Why it happens:** current local `vod_streams` and `series` records only persist a single `category_id`, and current Xtream detail DTOs also expose only one category slot.

**How to avoid:** make the first planning task define the authoritative source. Prefer normalized local assignment storage if multiple categories are real product truth; otherwise explicitly rescope before implementation starts.

**Warning signs:** controller code only reads `$model->category_id` or `$info->categoryId`; no migration/action/model exists for detail-page category assignments.

### Pitfall 2: Canonical order is not actually defined in code today
**What goes wrong:** chips render alphabetically, by personalized order, or by whatever order happens to fall out of a collection.

**Why it happens:** `BuildCategorySidebarItems` / `BuildPersonalizedCategorySidebar` both sort alphabetically at the category baseline, and `categories` has no explicit `sync_order` / `canonical_order` column.

**How to avoid:** either add an explicit persisted canonical-order field during category sync, or formally adopt a stopgap order source and test it.

**Warning signs:** `strcasecmp($left->name, $right->name)` appears anywhere in the detail-chip ordering path; `visibleItems` order is reused.

### Pitfall 3: Hidden/ignored semantics leak into detail rendering
**What goes wrong:** hidden or ignored categories disappear, look disabled, get grouped separately, or gain extra warning copy.

**Why it happens:** browse-state semantics are being applied in the wrong layer.

**How to avoid:** render all assigned categories neutrally and keep hidden/ignored handling on the browse destination page only.

**Warning signs:** chip filtering on `isHidden` / `isIgnored`, muted styling, tooltips explaining ignored state, or split sections.

### Pitfall 4: Movie and series implementations drift
**What goes wrong:** one detail page gets chips, ordering, or links that differ from the other.

**Why it happens:** duplicated controller/page logic evolves separately.

**How to avoid:** shared resolver, shared DTO, shared hero prop, and identical route-building rules per media type.

**Warning signs:** different prop names, different chip markup, or one controller computing category context inline while the other uses a shared action.

## Code Examples

Verified patterns from official and repo sources:

### GET navigation chip inside existing badge primitive
```tsx
// Source: https://inertiajs.com/links + resources/js/components/ui/badge.tsx
<Badge asChild variant="outline" className="bg-background/50 whitespace-normal break-words backdrop-blur-sm">
    <Link href={route('series', { category: category.id })} preserveState={false} preserveScroll={false}>
        {category.name}
    </Link>
</Badge>
```

### Controller prop expansion using repo DTO-first pattern
```php
// Source: app/Http/Controllers/Series/SeriesController.php, composer.json, resources/js/types/generated.d.ts
return Inertia::render('series/show', [
    'info' => $series,
    'in_watchlist' => $inWatchlist,
    'monitor' => $monitor === null ? null : SeriesMonitorData::from($monitor),
    'category_context' => DetailPageCategoryChipData::collect(
        app(ResolveDetailPageCategories::class)->forSeries($model)
    ),
]);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Detail pages only receive external Xtream detail DTOs plus local watchlist/monitor props | Add explicit local detail props for local concerns | Established repo pattern by Phase 7+ DTO-first Inertia payload work | Category context should be a separate local prop, not an Xtream DTO mutation. |
| Browse category order was purely alphabetical | Browse now overlays user pin/hide/ignore state on top of categories | Phases 8-9 (Mar 2026) | Detail pages must not reuse personalized browse order when locked requirement says canonical synced order. |
| Hand-written TS payload interfaces can drift | PHP DTOs are transformed into `resources/js/types/generated.d.ts` | Existing repo standard | Any new detail-chip DTO should follow transform workflow. |

**Deprecated/outdated:**
- Mutating Xtream response classes to carry local presentation state.
- Using personalized sidebar order as a proxy for canonical synced order.
- Building GET navigation chips as plain buttons with manual router calls.

## Open Questions

1. **Where does “all assigned categories” come from in this repo?**
   - What we know: current local models and external detail DTOs only expose one `category_id`; no assignment table or relation exists in the checked-in app code.
   - What's unclear: whether there is an uninspected local mapping source outside current app code, or whether Phase 12 must create normalized assignments.
   - Recommendation: make this the first planning decision/task. If no existing source exists, include normalized assignment storage/backfill in Phase 12 scope or explicitly rescope the requirement.

2. **What is the canonical synced order source?**
   - What we know: `categories` has no explicit order column, and current builders sort alphabetically / by user preference.
   - What's unclear: whether the product team accepts a temporary inferred order (for example `categories.id ASC`) or expects explicit persisted sync order.
   - Recommendation: prefer adding `sync_order` / `canonical_order` during `SyncCategories`; use inferred order only as a temporary, documented fallback.

3. **How should uncategorized detail titles render?**
   - What we know: system uncategorized provider ids exist for both media types, and browse controllers already handle them as valid category destinations.
   - What's unclear: whether the detail-page category source should emit uncategorized as a normal chip when no concrete category assignments exist.
   - Recommendation: if the resolved source returns the uncategorized provider id, render it as a normal chip; if there are truly no assignments, omit the row entirely.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest ^4.4 + pest-plugin-laravel ^4.1 + pest-plugin-browser ^4.3 |
| Config file | `phpunit.xml` and `tests/Pest.php` |
| Quick run command | `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php -x` |
| Full suite command | `php artisan typescript:transform && ./vendor/bin/pest && bun run lint && bun run build` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CTXT-01 | Movie detail page exposes all assigned categories in canonical order and browse links | feature | `./vendor/bin/pest tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php -x` | ❌ Wave 0 |
| CTXT-02 | Series detail page exposes all assigned categories in canonical order and browse links | feature | `./vendor/bin/pest tests/Feature/Controllers/SeriesDetailCategoryContextTest.php -x` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php -x`
- **Per wave merge:** `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php -x && composer test:browser -- --filter=DetailPageCategoryContextTest`
- **Phase gate:** `php artisan typescript:transform && ./vendor/bin/pest && bun run lint && bun run build` before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php` — movie show prop shape, canonical ordering, neutral hidden/ignored rendering contract
- [ ] `tests/Feature/Controllers/SeriesDetailCategoryContextTest.php` — series show prop shape, canonical ordering, neutral hidden/ignored rendering contract
- [ ] `tests/Browser/DetailPageCategoryContextTest.php` — chip visibility, click-through to browse, and mobile wrap/no-truncation smoke coverage
- [ ] `app/Data/DetailPageCategoryChipData.php` + `resources/js/types/generated.d.ts` — if a new DTO is introduced, regenerate with `php artisan typescript:transform`
- [ ] Category-assignment data source task — if no existing local multi-category source is found, implementation needs a migration/model/read path before hero UI work

## Sources

### Primary (HIGH confidence)
- Internal scope docs: `.planning/phases/12-detail-page-category-context/12-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/STATE.md`
- Repo source: `app/Http/Controllers/VodStream/VodStreamController.php`, `app/Http/Controllers/Series/SeriesController.php` — current browse/show payloads and recovery semantics
- Repo source: `app/Actions/BuildPersonalizedCategorySidebar.php`, `app/Data/CategorySidebarData.php`, `app/Data/CategorySidebarItemData.php`, `app/Models/Category.php` — category ordering/personalization/system-category semantics
- Repo source: `resources/js/components/media-hero-section.tsx`, `resources/js/components/ui/badge.tsx`, `resources/js/pages/movies/show.tsx`, `resources/js/pages/series/show.tsx`, `resources/js/hooks/use-category-browser.ts` — hero seam, chip primitive, browse route targets
- Repo source: `composer.json`, `package.json`, `phpunit.xml`, `tests/Pest.php` — stack versions and validation tooling
- Official docs: https://inertiajs.com/links — Inertia `Link`, `preserveState`, `preserveScroll`, `only`
- Official docs: https://laravel.com/docs/12.x/http-tests — HTTP feature test patterns
- Official docs: https://pestphp.com/docs/browser-testing — browser test navigation/assertion patterns

### Secondary (MEDIUM confidence)
- Internal prior-research note: `.planning/research/PITFALLS.md` — observed repo-level risk that single `category_id` is insufficient for future full assignment reads

### Tertiary (LOW confidence)
- Inference only: `categories.id ASC` could act as a temporary “synced order” because `SyncCategories` inserts new categories in source iteration order, but this is not codified anywhere and should not be treated as settled product truth.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - package manifests, repo conventions, and existing detail/browse code are explicit.
- Architecture: MEDIUM - integration seams are clear, but the all-categories data source and canonical-order source are unresolved.
- Pitfalls: HIGH - the main risks are directly observable in current controllers, schema, and category builders.

**Research date:** 2026-03-22
**Valid until:** 2026-04-21
