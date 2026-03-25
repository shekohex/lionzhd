# Phase 14: Refresh Ignored Discovery Browser Recovery Proof - Research

**Researched:** 2026-03-25
**Domain:** Pest browser recovery proof for Laravel/Inertia ignored discovery flows
**Confidence:** MEDIUM

<user_constraints>
## User Constraints

- No `CONTEXT.md` exists for this phase.
- Plan against shipped Phase 9 ignored-discovery behavior; do not redesign ignore UX, recovery copy intent, or browse/manage flow shape.
- Must address `IGNR-01` and `IGNR-02`.
- Focus on `tests/Browser/IgnoredDiscoveryFiltersTest.php` and the audit gap: current browser proof must match the live movies/series browse UI.
- Out of scope: search/browser auth refresh beyond reuse of the already-shipped shared helper, and detail-page browser proof (Phase 15).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| IGNR-01 | User can ignore a category for a media type so matching titles are excluded from catalog listings for that user | Reuse existing Phase 9 read/write path (`UserCategoryPreference` -> controllers -> sidebar/page props) and refresh browser proof around ignored rows, desktop/mobile ignore persistence, and targeted restore behavior. |
| IGNR-02 | User gets a recovery path when hidden or ignored preferences leave no visible categories or results | Assert current ignored-category empty state, all-categories manage-first recovery, same-URL unignore restore, and desktop/mobile ignored-state affordances against live UI copy. |
</phase_requirements>

## Summary

This is now a browser-harness alignment phase, not a product-behavior phase. The ignored-discovery runtime is already present in app code and feature coverage: controllers still emit `selectedCategoryIsIgnored` plus `filters.recovery`, the sidebar still renders ignored rows with muted treatment, and movie/series pages still render the current ignored-category and empty-all-categories recovery copy. The main planning risk is the browser suite itself.

I reproduced the current browser state on 2026-03-25. Targeted single ignored-recovery tests pass, but the full `IgnoredDiscoveryFiltersTest.php` run is not deterministic: `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php` failed on `waitForText('Movie Categories')` in a movie empty-state case, while the same scenarios pass in isolation. That strongly suggests harness/session leakage, not missing runtime behavior. The biggest code smell is that this suite still uses `test()->actingAs($user)` plus a local `updateCategoryPreferences()` helper that never calls `app('auth')->forgetGuards()`, while the adjacent Phase 13 refreshed browser suite already standardized on `browserLoginAndVisit()` and explicit guard cleanup after seeded PATCH requests.

**Primary recommendation:** refresh `IgnoredDiscoveryFiltersTest.php` around the Phase 13 browser standard: seed prefs through the existing PATCH helper, clear auth guard side effects after seeding, enter every browser proof through `browserLoginAndVisit()`, and keep assertions anchored to current movies/series recovery copy plus URL invariants.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | `^12.0` | App, auth, routes, Inertia responses | Current app framework; ignored discovery truth lives in controllers, routes, and auth flow. |
| `@inertiajs/react` | `^2.0.9` | Browse pages and sidebar/manage UI | Current movies/series browse contract and recovery actions are Inertia-driven. |
| Pest | `^4.4` | Test runner | Repo-standard PHP/browser runner. |
| `pestphp/pest-plugin-browser` | `^4.3` | Browser automation API (`visit`, `waitForText`, `resize`, `script`) | Current browser proof framework. |
| Playwright | `1.54.1` | Browser engine used by Pest browser tests | Required runtime for the browser suite. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHP | `^8.4` | Runtime for Laravel and Pest | Use current repo baseline only; no compatibility work needed. |
| `spatie/laravel-data` | `^4.14` | Typed DTO contract for `categories` and `filters` props | Use when browser assertions must stay aligned with backend prop shape. |
| `spatie/laravel-typescript-transformer` | `^2.5` | Generated TS types | Relevant if page prop shape changes while refreshing proof. |
| Shared browser auth helper | repo-local | Canonical live auth bootstrap | Use `tests/Browser/Support/browser-auth.php` instead of file-local auth assumptions. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `browserLoginAndVisit()` | `test()->actingAs($user)` only | Faster, but it does not prove live auth and is already the most likely source of full-suite flake here. |
| Existing PATCH route helper for preference seeding | Direct DB writes to `user_category_preferences` | Faster setup, but it stops exercising the shipped snapshot contract that Phase 9 deliberately locked. |
| Pest URL assertions | Custom `parse_url()` / query helpers everywhere | Custom helpers still work, but built-ins are simpler for path/query assertions and reduce stale test glue. |

**Installation:**
```bash
composer install
bun install
bun run test:browser:prepare
```

## Architecture Patterns

### Recommended Project Structure
```text
tests/
├── Browser/
│   ├── IgnoredDiscoveryFiltersTest.php
│   └── Support/
│       └── browser-auth.php
├── Feature/Discovery/
│   ├── MoviesPersonalCategoryControlsTest.php
│   └── SeriesPersonalCategoryControlsTest.php
resources/js/
├── components/
│   └── category-sidebar/
│       ├── browse.tsx
│       └── manage.tsx
├── hooks/
│   └── use-category-browser.ts
└── pages/
    ├── movies/index.tsx
    └── series/index.tsx
```

### Pattern 1: Canonical browser entry goes through live auth
**What:** Seed fixtures and preference state first, clear guard side effects, then enter the actual browser flow through the shared login helper.
**When to use:** Every end-to-end ignored-discovery proof in this phase.
**Example:**
```php
// Source: tests/Browser/Support/browser-auth.php, tests/Browser/SearchableCategoryNavigationTest.php
$page = browserLoginAndVisit($user, route('movies', ['category' => 'movie-action']))
    ->waitForText('Movie Categories')
    ->waitForText('This category is ignored')
    ->assertNoJavaScriptErrors();

app('auth')->forgetGuards();
```

### Pattern 2: Seed ignored state through the shipped PATCH contract
**What:** Use the existing `category-preferences.update` route helper to seed `ignored_ids`, not direct DB writes.
**When to use:** Any browser case that needs ignored/hidden/pinned snapshot state before opening the browser.
**Example:**
```php
// Source: tests/Browser/IgnoredDiscoveryFiltersTest.php:759-765
test()->actingAs($user)
    ->from(route('movies'))
    ->patch(route('category-preferences.update', ['mediaType' => $mediaType->value]), [
        'pinned_ids' => [],
        'visible_ids' => ['movie-drama'],
        'hidden_ids' => [],
        'ignored_ids' => ['movie-action', 'movie-comedy'],
    ])
    ->assertRedirect(route('movies'))
    ->assertSessionHasNoErrors();
```

### Pattern 3: Assert live recovery copy from the page component, not legacy wording
**What:** Keep browser assertions anchored to current `movies/index.tsx` and `series/index.tsx` copy.
**When to use:** Selected ignored-category recovery and empty all-categories recovery.
**Example:**
```tsx
// Source: resources/js/pages/movies/index.tsx, resources/js/pages/series/index.tsx
title="This category is ignored"
<Button type="button" onClick={onUnignoreSelectedCategory}>
    Unignore and restore results
</Button>

title="Your movie view is empty"
description="Ignored categories are filtering every movie out. Manage categories to restore your view, or reset preferences as a fallback."
```

### Pattern 4: Keep DOM helpers visibility-aware for desktop and mobile
**What:** Use helper scripts that check actual visibility and DOM placement for row metrics, sheet buttons, and mobile actions.
**When to use:** Ignored-row muted-state assertions and mobile manage-mode flows.
**Example:**
```php
// Source: tests/Browser/IgnoredDiscoveryFiltersTest.php:783-800
return $page->script(str_replace('__LABEL__', $labelJson, <<<'JS'
    () => {
        const label = __LABEL__;
        const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
            candidate.textContent?.trim() === label && candidate.offsetParent !== null
        );

        return {
            found: Boolean(button),
            className: button?.className ?? '',
            rowText: button?.closest('[class*="group/row"]')?.textContent?.replace(/\s+/g, ' ').trim() ?? '',
        };
    }
JS));
```

### Anti-Patterns to Avoid
- **`actingAs`-only browser proof:** acceptable for feature setup, not for final browser recovery proof on this branch.
- **Leaving guard state dirty after setup PATCHes:** adjacent browser refresh work already proved this can block live-login flows.
- **Asserting legacy copy:** current titles are `This category is ignored`, `Your movie view is empty`, and `Your series view is empty`.
- **Reinventing ignore fixtures in new files:** extend `IgnoredDiscoveryFiltersTest.php`; the roadmap explicitly names it.
- **Using non-visibility-aware button lookups on mobile:** sheet content and hidden rows can produce false positives.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Browser auth bootstrap | Another file-local login helper | `tests/Browser/Support/browser-auth.php` | Phase 13 already established the repo standard and current live login copy. |
| Ignored preference seeding | Direct inserts/updates to preference rows | Existing PATCH helper through `category-preferences.update` | Keeps browser proof aligned with shipped snapshot validation and redirect behavior. |
| URL parsing everywhere | More custom path/query helpers | Pest `assertPathIs` / `assertQueryStringHas` where possible | Built-ins reduce brittle glue; keep custom JS only where DOM inspection is needed. |
| Desktop/mobile muted-row checks | Screenshot-only assertions | Existing `ignoredRowMetrics()` style DOM metrics | Faster, deterministic, and already aligned with the current class-based affordance. |

**Key insight:** Phase 14 should refresh one existing browser suite to the current browser-testing standard, not invent a new ignored-discovery proof path.

## Common Pitfalls

### Pitfall 1: Full-suite flake hidden by single-test success
**What goes wrong:** Individual ignored browser tests pass, but the full file fails on the first visible-page assertion.
**Why it happens:** The suite still mixes server-side `actingAs()` setup with browser `visit()` entrypoints and never clears guard state after setup PATCHes.
**How to avoid:** Standardize on `browserLoginAndVisit()` for every test entrypoint and clear auth guards after seeded PATCH helpers.
**Warning signs:** `waitForText('Movie Categories')` or `waitForText('Series Categories')` times out only in full-file runs.

### Pitfall 2: Wrong-page failures look like stale copy failures
**What goes wrong:** A browser test times out waiting for recovery copy, but the real issue is redirect/auth/session state.
**Why it happens:** Text-only assertions cannot distinguish a broken page contract from a wrong location.
**How to avoid:** Pair copy checks with path/query assertions before and after recovery actions.
**Warning signs:** Blank page, login page, or unexpected path when the text wait fails.

### Pitfall 3: Recovery proof drifts from live page copy
**What goes wrong:** Tests assert wording that no longer matches `movies/index.tsx` or `series/index.tsx`.
**Why it happens:** Recovery copy lives in page components, not only in test fixtures.
**How to avoid:** Treat page components as the source of truth for ignored and empty-state copy.
**Warning signs:** Feature tests still pass but browser suite fails on `waitForText()`/`assertSee()` for recovery messaging.

### Pitfall 4: Mobile helpers click hidden controls
**What goes wrong:** Mobile tests target a hidden button in the underlying page instead of the open sheet.
**Why it happens:** naive DOM selection ignores viewport and visibility state.
**How to avoid:** Keep visibility-aware script helpers and wait for sheet-specific text like `Manage` or `Preferences` before acting.
**Warning signs:** Intermittent mobile-only failures or clicks that do nothing.

## Code Examples

Verified patterns from current sources:

### Shared live-login helper
```php
// Source: tests/Browser/Support/browser-auth.php
function browserLoginAndVisit(User $user, string $url): object
{
    $page = browserLogin($user);

    $page->script(str_replace('__URL__', json_encode($url, JSON_THROW_ON_ERROR), <<<'JS'
        () => {
            window.location.assign(__URL__);

            return true;
        }
    JS));

    return $page;
}
```

### Current ignored recovery copy contract
```tsx
// Source: resources/js/pages/movies/index.tsx:128-181, resources/js/pages/series/index.tsx:128-181
if (selectedCategory !== null && selectedCategoryIsIgnored) {
    return (
        <EmptyState
            title="This category is ignored"
            action={
                <Button type="button" onClick={onUnignoreSelectedCategory}>
                    Unignore and restore results
                </Button>
            }
        />
    );
}

if (recovery?.allCategoriesEmptyDueToIgnored || recovery?.allCategoriesEmptyDueToHidden) {
    return (
        <EmptyState
            title="Your movie view is empty"
            action={
                <div>
                    <Button type="button" onClick={onManageCategories}>Manage categories</Button>
                    <Button type="button" variant="outline" onClick={onResetPreferences}>Reset preferences</Button>
                </div>
            }
        />
    );
}
```

### Current ignored-row muted treatment
```tsx
// Source: resources/js/components/category-sidebar/browse.tsx:50-57
const buttonClassName = cn(
    'group flex w-full items-start gap-2 rounded-md border px-3 py-2 text-left text-sm transition-all duration-200',
    isSelected
        ? 'border-primary/50 bg-primary/5 text-primary ring-1 ring-primary/20'
        : item.isIgnored
            ? 'border-border/70 bg-muted/25 text-muted-foreground hover:border-border hover:bg-muted/40'
            : 'border-transparent hover:border-border hover:bg-muted/80',
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| File-local `actingAs()` browser entry | Shared `browserLoginAndVisit()` auth bootstrap for refreshed browser suites | Phase 13 / 2026-03-25 | Phase 14 should align with the same standard to avoid harness drift. |
| Legacy ignored/empty-state wording in stale assertions | Live copy in `movies/index.tsx` and `series/index.tsx` | Current branch | Browser proof must assert current strings, not historical text. |
| Implicit session assumptions between setup and browser visits | Explicit guard cleanup after setup helpers | Phase 13 summary / 2026-03-25 | Prevents full-suite-only auth/session flakes. |

**Deprecated/outdated:**
- `test()->actingAs($user)` as the only browser suite entrypoint for refreshed proof phases.
- Assuming browser failures on recovery text always mean product regressions; on this branch they can be harness/session issues.

## Open Questions

1. **Should Phase 14 fully migrate this suite to shared live auth, or only patch the local helper state leak?**
   - What we know: the shared helper already exists, adjacent suites use it, and the ignored suite still uses `actingAs()` everywhere.
   - What's unclear: whether a minimal `forgetGuards()` fix alone would make the suite fully deterministic.
   - Recommendation: migrate all browser entrypoints to `browserLoginAndVisit()` and keep guard cleanup in setup helpers; do not stop at a partial local fix.

2. **Should the suite keep custom URL helpers or adopt Pest URL assertions during refresh?**
   - What we know: Pest browser docs expose `assertPathIs` and `assertQueryStringHas`; the current suite still uses `parse_url()` helpers.
   - What's unclear: how much churn is worth taking in one phase.
   - Recommendation: prefer built-in path/query assertions when touching a test anyway, but keep existing DOM-inspection helpers for row metrics and mobile sheet interactions.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest `^4.4` + `pestphp/pest-plugin-browser` `^4.3` + Playwright `1.54.1` |
| Config file | `phpunit.xml`, `tests/Pest.php`, `tests/Browser/Support/browser-auth.php` |
| Quick run command | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php --stop-on-failure` |
| Full suite command | `bun run test:browser:prepare && ./vendor/bin/pest --stop-on-failure` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| IGNR-01 | Ignored movie/series categories stay excluded per user, ignored rows remain visible/muted, and targeted restore leaves other ignored categories untouched | feature + browser | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure` | ✅ |
| IGNR-02 | Selected ignored category recovers in place on the same browse URL; empty all-categories state stays manage-first with reset secondary on desktop/mobile | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php --stop-on-failure` | ✅ |

### Sampling Rate
- **Per task commit:** `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php --stop-on-failure`
- **Per wave merge:** `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --stop-on-failure`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
None — the browser and feature infrastructure already exists. The work is to refresh and stabilize the existing suite, not to introduce new test files or framework setup.

## Sources

### Primary (HIGH confidence)
- `/home/coder/project/lionzhd/.planning/ROADMAP.md` - Phase 14 goal, gap closure, and success criteria.
- `/home/coder/project/lionzhd/.planning/v1.1-MILESTONE-AUDIT.md` - exact audit gap and broken-flow evidence.
- `/home/coder/project/lionzhd/composer.json` - Laravel/Pest/browser package versions.
- `/home/coder/project/lionzhd/package.json` - Playwright/frontend package versions.
- `/home/coder/project/lionzhd/phpunit.xml` and `/home/coder/project/lionzhd/tests/Pest.php` - browser test configuration.
- `/home/coder/project/lionzhd/tests/Browser/IgnoredDiscoveryFiltersTest.php` - current suite shape, helper patterns, and stale/flaky entrypoints.
- `/home/coder/project/lionzhd/tests/Browser/Support/browser-auth.php` - canonical shared live auth helper.
- `/home/coder/project/lionzhd/tests/Browser/SearchableCategoryNavigationTest.php` - current browser-suite standard including `browserLoginAndVisit()` and `app('auth')->forgetGuards()`.
- `/home/coder/project/lionzhd/resources/js/pages/movies/index.tsx` and `/home/coder/project/lionzhd/resources/js/pages/series/index.tsx` - live ignored and empty-state copy.
- `/home/coder/project/lionzhd/resources/js/components/category-sidebar/browse.tsx` and `/home/coder/project/lionzhd/resources/js/components/category-sidebar/manage.tsx` - current ignored-row treatment and manage-mode controls.
- `/home/coder/project/lionzhd/resources/js/hooks/use-category-browser.ts` - shared recovery and preference mutation contract.
- `/home/coder/project/lionzhd/app/Http/Controllers/VodStream/VodStreamController.php` and `/home/coder/project/lionzhd/app/Http/Controllers/Series/SeriesController.php` - current ignored browse filtering and recovery metadata.
- `/home/coder/project/lionzhd/app/Actions/BuildPersonalizedCategorySidebar.php` - selected ignored metadata and visible/ignored ordering.
- `/home/coder/project/lionzhd/tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php` and `/home/coder/project/lionzhd/tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php` - current feature-level ignored recovery contract.
- `https://pestphp.com/docs/browser-testing` - current Pest browser assertions/interactions.
- `https://inertiajs.com/manual-visits` - router visit/reload and state preservation behavior.
- `https://inertiajs.com/partial-reloads` - partial reload contract used by category preference mutations.
- `https://spatie.be/docs/laravel-data/v4/introduction` - DTO/type-generation model used by sidebar/filter props.

### Secondary (MEDIUM confidence)
- `/home/coder/project/lionzhd/.planning/phases/13-refresh-search-and-navigation-browser-auth-proof/13-RESEARCH.md` - adjacent browser-refresh planning pattern.
- `/home/coder/project/lionzhd/.planning/phases/13-refresh-search-and-navigation-browser-auth-proof/13-03-SUMMARY.md` - evidence that guard cleanup was required in the adjacent refreshed suite.
- Local reproduction on 2026-03-25: `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php` - current full-file flake evidence.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - direct manifests plus official docs.
- Architecture: MEDIUM - shared auth-helper adoption is strongly indicated by adjacent work and reproduced flake, but the minimal deterministic fix path was not exhaustively isolated.
- Pitfalls: HIGH - current full-suite failure was reproduced locally and mapped to specific test-helper differences.

**Research date:** 2026-03-25
**Valid until:** 2026-04-08
