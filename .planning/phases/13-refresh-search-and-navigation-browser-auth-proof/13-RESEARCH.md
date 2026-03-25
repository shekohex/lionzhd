# Phase 13: Refresh Search and Navigation Browser Auth Proof - Research

**Researched:** 2026-03-25
**Domain:** Pest browser auth bootstrap for Laravel/Inertia search and category navigation flows
**Confidence:** MEDIUM

<user_constraints>
## User Constraints

- No `CONTEXT.md` exists for this phase.
- Plan against shipped Phase 10-11 behavior; do not redesign search or category navigation.
- Must address `NAVG-01`, `SRCH-01`, `SRCH-02`, `SRCH-03`, `SRCH-04`.
- Focus on stale browser auth bootstrap and end-to-end proof for `tests/Browser/SearchModeUxTest.php` and `tests/Browser/SearchableCategoryNavigationTest.php`.
- Out of scope: ignored discovery recovery and detail-page browser suites (Phases 14-15).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| NAVG-01 | User can search categories within sidebar or navigation on web and mobile | Current sidebar search lives in `resources/js/components/category-sidebar.tsx` + `search.tsx`; browser proof lives in `tests/Browser/SearchableCategoryNavigationTest.php`; auth bootstrap must land on live authenticated browse pages first. |
| SRCH-01 | User can switch search media type between all, movies, and series and see UI state stay in sync with the URL | `/search` mode state is URL-authoritative in `resources/js/pages/search.tsx` and `app/Data/SearchMediaData.php`; browser proof is `tests/Browser/SearchModeUxTest.php`. |
| SRCH-02 | User sees only matching media-type results when search is filtered to movies or series | `SearchController` returns only the chosen media collection in filtered mode; browser proof should reach current filtered assertions after auth succeeds. |
| SRCH-03 | User sees movie-only or series-only search results in a full-width result mode | Current page uses `data-search-layout="filtered"` and `Movies only` / `TV Series only` copy; browser proof already targets this layout. |
| SRCH-04 | User can refresh, deep-link, and use back or forward navigation without losing correct search mode behavior | Existing `SearchModeUxTest.php` already encodes refresh/history expectations; Phase 13 only needs the live auth path to stop blocking those assertions. |
</phase_requirements>

## Summary

This phase is a browser-harness alignment phase, not a product-behavior phase. Shipped Phase 10-11 behavior is already encoded in current app code and browser assertions. The gap is the auth bootstrap at the front of the suites. On 2026-03-25 I reproduced both failures with targeted runs: `SearchModeUxTest` and `SearchableCategoryNavigationTest` both die before their first flow assertion because `waitForText('Log in to your account')` never succeeds on `/login`; both screenshots are blank white pages.

Current live auth facts are clear in code: `/login` renders `auth/login`, the login page copy is `Log in to your account`, fields are `Email address` and `Password`, submit text is `Log in`, and successful login redirects to `route('discover')`, not `/dashboard`. Current search and navigation flows are also clear: `/search` keeps draft query local until a committed action updates the URL, filtered mode shows one full-width section, and category search uses the shared `cmdk` surface across desktop sidebar and mobile sheet.

**Primary recommendation:** treat Phase 13 as a shared live-login bootstrap refresh for both suites: assert `/login` -> submit current copy -> assert authenticated landing on `/discover` -> continue into existing search/navigation assertions without changing shipped Phase 10-11 behavior.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | `^12.0` | App, auth routes, redirects, Inertia responses | Current app framework; auth and route truth lives here. |
| `@inertiajs/react` | `^2.0.9` | Full-page search/auth/navigation UI | Current frontend contract for `/login`, `/search`, `/movies`, `/series`. |
| Pest | `^4.4` | Test runner | Project-standard PHP test runner. |
| `pestphp/pest-plugin-browser` | `^4.3` | Browser automation API (`visit`, `waitForText`, `press`) | Current browser proof framework in `tests/Pest.php`. |
| Playwright | `1.54.1` | Browser engine used by Pest browser tests | Required runtime under the Pest browser plugin. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Scout | `^10.13` | Search abstraction | Use `config()->set('scout.driver', 'database')` in browser fixtures to avoid Meilisearch dependence. |
| `cmdk` | `1.0.0` | Sidebar category search UI | Use existing ranked results surface; do not create suite-specific search logic. |
| Tighten Ziggy | `^2.4` | Route generation in React | Keeps browser assertions aligned with named Laravel routes. |
| `nuqs` | `^2.4.3` | URL-backed query state adapter | Already part of full-page search state flow. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Real login form bootstrap | `test()->actingAs($user)` only | Faster, but it does not prove current auth copy, form fields, submit action, or redirect. Not enough for this phase. |
| Pest URL/path assertions | custom JS polling for simple auth path checks | JS polling is only justified for history/cmdk internals; auth bootstrap should prefer built-ins where possible. |

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
│   ├── SearchModeUxTest.php
│   ├── SearchableCategoryNavigationTest.php
│   ├── CategorySidebarScrollTest.php
│   └── Support/              # only if one shared auth helper becomes worth extracting
├── Feature/Controllers/
│   └── SearchControllerTest.php
resources/js/
├── pages/auth/login.tsx
├── pages/search.tsx
└── components/category-sidebar/
```

### Likely File Touch Points
- `tests/Browser/SearchModeUxTest.php`
- `tests/Browser/SearchableCategoryNavigationTest.php`
- `tests/Browser/CategorySidebarScrollTest.php` if auth bootstrap is deduplicated there too
- `resources/js/pages/auth/login.tsx` for copy verification only; app changes likely unnecessary
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` for redirect truth only; app changes likely unnecessary

### Pattern 1: Canonical live auth bootstrap
**What:** Use the real login page with current copy, then assert the authenticated landing page before visiting suite-specific URLs.
**When to use:** Any browser suite whose purpose includes proving auth still works end to end.
**Example:**
```php
// Source: tests/Browser/SearchModeUxTest.php:215-225, app/Http/Controllers/Auth/AuthenticatedSessionController.php:40-47, https://pestphp.com/docs/browser-testing
$page = visit(route('login'))
    ->assertPathIs('/login')
    ->waitForText('Log in to your account')
    ->fill('Email address', $user->email)
    ->fill('Password', 'password')
    ->press('Log in')
    ->assertPathIs('/discover')
    ->waitForText('Discover')
    ->assertNoJavaScriptErrors();
```

### Pattern 2: `/search` URL is the committed source of truth
**What:** Keep draft query local; only committed actions update `q`, `media_type`, `sort_by`, and `page` in the URL.
**When to use:** Search mode, refresh, deep-link, and back/forward assertions.
**Example:**
```tsx
// Source: resources/js/pages/search.tsx:82-138
const performSearch = (overrides = {}) => {
    const searchUrl = route('search.full', {
        q: hasQueryOverride ? rawQuery : (rawQuery.trim() ? rawQuery : undefined),
        page: overrides.page ?? props.filters.page ?? 1,
        media_type: hasMediaTypeOverride ? overrides.media_type : props.filters.media_type,
        sort_by: hasSortByOverride ? overrides.sort_by : props.filters.sort_by,
    });

    router.get(searchUrl, {}, { preserveState: true, preserveScroll: false });
};
```

### Pattern 3: Category search uses one shared ranked `cmdk` surface
**What:** Desktop and mobile both reuse the same query state + result builder; mobile resets query on sheet close.
**When to use:** Any browser proof around category filtering, fuzzy ranking, or reopen/reset behavior.
**Example:**
```tsx
// Source: resources/js/components/category-sidebar.tsx:286-299, 386-425
const searchResults = useMemo(() => {
    if (query.trim() === '') return [];

    return buildCategorySearchResults({
        query: query.trim(),
        items: [...pinnedItems, ...visibleItems, ...ignoredVisibleItems],
        uncategorizedItem,
    });
}, [ignoredVisibleItems, pinnedItems, query, uncategorizedItem, visibleItems]);
```

### Anti-Patterns to Avoid
- **`actingAs` as the only proof:** acceptable for non-auth browser flows, but not for this gap-closure phase.
- **Dashboard-based login assertions:** login redirects to `/discover`; no named `dashboard` route exists in `routes/*.php`.
- **Text-only auth success checks:** pair copy assertions with path assertions so copy drift and redirect drift are both caught.
- **Extra browser-only search logic:** browser tests must consume shipped UI state, not a parallel matching contract.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Auth bypass for proof | direct `actingAs`-only bootstrap | real login form submission | Phase 13 must prove current auth copy + redirect, not only authenticated state. |
| Simple auth URL checks | custom `window.location` polling everywhere | Pest `assertPathIs`, `assertPathIsNot`, `assertQueryStringHas` | Built-ins are simpler and less brittle for login/bootstrap assertions. |
| Browser search backend | live Meilisearch dependency | `scout.driver=database` fixture setup | Search browser proof should stay hermetic and fast. |
| Category ranking logic | new browser-only fuzzy matcher | existing `buildCategorySearchResults()` | Ranking, ignored ordering, and uncategorized-last are already encoded there. |
| Preference persistence setup | ad-hoc DB writes for browser proof | existing `category-preferences.update` route helper | Keeps browser proof aligned with shipped mutation contract. |

**Key insight:** this phase should reuse the shipped auth, search, sidebar, and preference contracts exactly as they exist; the fix is harness alignment, not new runtime behavior.

## Common Pitfalls

### Pitfall 1: Duplicated auth helpers drift independently
**What goes wrong:** `SearchModeUxTest`, `SearchableCategoryNavigationTest`, and `CategorySidebarScrollTest` each define nearly identical file-local login helpers.
**Why it happens:** the project has no shared browser auth helper today.
**How to avoid:** update all duplicated helpers together or extract one shared helper if Phase 13 changes touch all three suites.
**Warning signs:** one suite passes login while another still waits for stale copy or stale landing text.

### Pitfall 2: Proof uses stale landing assumptions
**What goes wrong:** browser proof waits for `Dashboard` or `/dashboard` after login.
**Why it happens:** stale starter-kit leftovers still exist in `resources/js/components/app-header.tsx` and some auth controllers reference `route('dashboard')`, but login itself redirects to `route('discover')`.
**How to avoid:** treat `AuthenticatedSessionController::store()` as the source of truth for login bootstrap.
**Warning signs:** login succeeds server-side but browser proof stalls on the first authenticated assertion.

### Pitfall 3: Search tests confuse draft state with committed URL state
**What goes wrong:** typing into the search box is expected to mutate `q` immediately.
**Why it happens:** `/search` intentionally keeps draft text local until submit or another committed action.
**How to avoid:** assert local input value separately from URL query params.
**Warning signs:** assertions fail right after typing but pass after submit/tab/sort.

### Pitfall 4: Search fixtures accidentally depend on live Meilisearch
**What goes wrong:** search suites fail from missing index state instead of auth or UI behavior.
**Why it happens:** the app normally supports Meilisearch, but the browser tests are seeded for local database search.
**How to avoid:** keep `config()->set('scout.driver', 'database')` in search browser fixtures.
**Warning signs:** `localhost:7700` errors or empty results despite seeded records.

### Pitfall 5: Mobile/category-search helpers break on `cmdk` or sheet visibility
**What goes wrong:** helpers interact with hidden rows or fixed-position sheet content.
**Why it happens:** `cmdk` items and mobile sheets need visibility checks stronger than `offsetParent`.
**How to avoid:** keep bounding-box visibility checks for category-search helpers and only hand-roll JS around `cmdk` internals.
**Warning signs:** helpers click the wrong row, fail only on mobile, or report results that are visually hidden.

### Pitfall 6: Login bootstrap failure masks the real suite behavior
**What goes wrong:** the suite never reaches any search/navigation assertion.
**Why it happens:** the first `waitForText('Log in to your account')` is currently the failing checkpoint.
**How to avoid:** make auth bootstrap its own clearly asserted step with path + copy + screenshot diagnostics.
**Warning signs:** blank screenshot, zero assertions executed, failure line points at the login helper.

## Code Examples

Verified patterns from current sources:

### Live login bootstrap for proof suites
```php
// Source: resources/js/pages/auth/login.tsx:41-98, app/Http/Controllers/Auth/AuthenticatedSessionController.php:40-47
visit(route('login'))
    ->waitForText('Log in to your account')
    ->fill('Email address', $user->email)
    ->fill('Password', 'password')
    ->press('Log in')
    ->waitForText('Discover');
```

### Filtered search layout contract
```tsx
// Source: resources/js/pages/search.tsx:144-155, 292-397
const isFilteredMode = activeMode !== 'all';
const filteredModeLabel = activeMode === 'movie' ? 'Movies only' : 'TV Series only';

<div data-search-layout={isFilteredMode ? 'filtered' : 'all'}>
    {activeMode === 'movie' && props.movies?.total > 0 && <MediaSection title={filteredModeLabel} />}
    {activeMode === 'series' && props.series?.total > 0 && <MediaSection title={filteredModeLabel} />}
</div>
```

### Category search excludes `All categories` and sorts `Uncategorized` last
```tsx
// Source: resources/js/components/category-sidebar/search.tsx:83-110
const results = items
    .filter((item) => item.id !== CATEGORY_SIDEBAR_ALL_CATEGORIES_ID)
    .flatMap((item) => {
        const score = scoreCategorySearchResult(normalizedQuery, item.name);

        if (score === null) return [];

        return [{ item, score, segments: buildCategorySearchSegments(item.name, normalizedQuery) }];
    });

return results.sort((left, right) => {
    if (left.item.isUncategorized !== right.item.isUncategorized) {
        return left.item.isUncategorized ? 1 : -1;
    }

    return right.score - left.score;
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `actingAs` or stale auth assumptions at suite entry | explicit live login bootstrap for auth-proof suites | Phase 13 gap-closure target (roadmap updated 2026-03-25) | Browser proof must show login form + redirect still work. |
| Draft query and URL mutate together | draft query is local; URL updates only on committed actions | Phase 11 (2026-03-21) | Browser proof must separate local input assertions from URL assertions. |
| Mobile/desktop category search differ by surface | one shared sidebar search model across desktop + mobile sheet | Phase 10 (2026-03-20) | Browser proof should reuse the same copy, ranking, and reset rules across both. |

**Deprecated/outdated:**
- Waiting for `/dashboard` or `Dashboard` after login: outdated for this app; login redirects to `/discover`.
- Treating `SearchableCategoryNavigationTest` mobile flows as auth proof through `actingAs` alone: insufficient for this phase.

## Open Questions

1. **Why is `/login` blank in reproduced browser runs?**
   - What we know: both targeted runs failed at the first login-page text assertion on 2026-03-25; screenshots were blank white pages.
   - What's unclear: render timing vs JS bootstrap vs browser-plugin environment issue.
   - Recommendation: begin Phase 13 with a minimal login-only smoke path or stronger helper diagnostics before touching deeper assertions.

2. **Should mobile searchable-navigation flows also go through live login?**
   - What we know: desktop nav tests use live login; current mobile nav tests use `test()->actingAs($user)`.
   - What's unclear: whether acceptance requires every scenario or one suite-level auth proof per suite.
   - Recommendation: ensure at least one mobile navigation path is covered behind the refreshed live login bootstrap, or document why desktop-only auth proof is sufficient.

3. **Is a shared helper worth a new support file?**
   - What we know: three browser suites duplicate the same helper.
   - What's unclear: whether Phase 13 should prefer editing existing files only or centralizing the helper.
   - Recommendation: extract only if all three suites need the same change; otherwise update both target suites in place and keep scope tight.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest `^4.4` + pest-plugin-browser `^4.3` + Playwright `1.54.1` |
| Config file | `phpunit.xml` |
| Quick run command | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php -x` |
| Full suite command | `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php tests/Browser/CategorySidebarScrollTest.php` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| NAVG-01 | Searchable category navigation reaches desktop/mobile category-search assertions after live auth | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchableCategoryNavigationTest.php -x` | ✅ |
| SRCH-01 | Search mode tabs and URL stay in sync after live auth | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="syncs mode tabs with url" -x` | ✅ |
| SRCH-02 | Filtered search shows only the chosen media type after live auth | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="renders filtered full width layout" -x` | ✅ |
| SRCH-03 | Filtered search renders focused full-width layout | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="renders filtered full width layout" -x` | ✅ |
| SRCH-04 | Refresh, deep-link, and history restoration stay correct after live auth | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" -x` | ✅ |

### Sampling Rate
- **Per task commit:** `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php -x`
- **Per wave merge:** `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php`
- **Phase gate:** Full targeted suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Browser/SearchModeUxTest.php` — refresh login bootstrap so the suite reaches current search assertions.
- [ ] `tests/Browser/SearchableCategoryNavigationTest.php` — same auth bootstrap refresh for navigation proof.
- [ ] Optional shared helper target (`tests/Browser/CategorySidebarScrollTest.php` or `tests/Browser/Support/*`) — only if the login bootstrap is centralized.
- [ ] Minimal login-only smoke/assert diagnostic step — needed because current reproduced failures die before any search/navigation assertion runs.

## Sources

### Primary (HIGH confidence)
- `tests/Browser/SearchModeUxTest.php` - current auth helper, URL/history helpers, and `/search` browser assertions
- `tests/Browser/SearchableCategoryNavigationTest.php` - current auth helper plus desktop/mobile category-search assertions
- `resources/js/pages/auth/login.tsx` - live login copy and field labels
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` - login redirect target (`discover`)
- `resources/js/pages/search.tsx` - committed URL search contract and filtered-layout copy
- `resources/js/components/category-sidebar.tsx` - shared desktop/mobile category-search shell and mobile reset-on-close behavior
- `resources/js/components/category-sidebar/search.tsx` - ranked category-search contract and empty-state copy
- `tests/Pest.php`, `composer.json`, `package.json`, `phpunit.xml` - actual browser test framework and versions
- https://pestphp.com/docs/browser-testing - browser API, URL assertions, and browser-testing guidance
- https://playwright.dev/docs/intro - Playwright runtime/install guidance

### Secondary (MEDIUM confidence)
- Targeted browser reproductions on 2026-03-25:
  - `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="syncs mode tabs with url" --stop-on-failure`
  - `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchableCategoryNavigationTest.php --filter="desktop movie search ranks fuzzy hits" --stop-on-failure`
- `tests/Browser/Screenshots/it_syncs_mode_tabs_with_url.png` - reproduced blank login-page screenshot
- `tests/Browser/Screenshots/it_desktop_movie_search_ranks_fuzzy_hits__hides_all_categories__and_keeps_uncategorized_last.png` - same reproduced failure for navigation suite

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - versions come from local manifests and official docs.
- Architecture: HIGH - current behavior is explicit in shipped code and browser suites.
- Pitfalls: MEDIUM - duplicated helpers and reproduced login failures are real, but the exact cause of the blank `/login` render is still unresolved.

**Research date:** 2026-03-25
**Valid until:** 2026-04-01
