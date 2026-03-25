# Phase 15: Refresh Detail Page Category Browser Proof - Research

**Researched:** 2026-03-25
**Domain:** Pest browser proof refresh for Laravel/Inertia detail-page category chips
**Confidence:** HIGH

<user_constraints>
## User Constraints

- No `CONTEXT.md` exists for this phase.
- Honor shipped Phase 12 detail-chip decisions already recorded in project state: chips stay in their own unlabeled wrapped row under genres, hidden/ignored categories render neutrally, and chip clicks hand off to the existing browse recovery flows.
- Reuse the shared live browser auth bootstrap introduced in Phase 13 instead of inventing another suite-local login helper.
- Must address `CTXT-01` and `CTXT-02` by refreshing browser proof only; do not redesign detail-page UI or category-context runtime behavior unless the refreshed suite proves a real regression.
- Focus on `tests/Browser/DetailPageCategoryContextTest.php` and the audit gap: current browser proof must match the live detail UI and stay deterministic end to end.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CTXT-01 | User can see all assigned categories on movie detail pages | Refresh movie detail browser proof to enter through live auth, assert the current detail title before chip checks, and verify chip click-through lands on the movie browse URL/recovery state. |
| CTXT-02 | User can see all assigned categories on series detail pages | Mirror the same live-auth browser proof for series detail pages, including current title waits, chip visibility, and ignored-category browse handoff. |
</phase_requirements>

## Summary

This is a browser-harness refresh phase, not a product-behavior phase. Phase 12 already shipped the runtime contract: `MediaHeroSection` renders a dedicated `data-slot="hero-category-context"` row, movie and series detail pages pass `category_context` into that shared hero, and the current browser suite already proves the intended destination states for hidden and ignored categories. The work left is aligning the suite with the current browser-testing standard from Phases 13-14.

I reproduced the current suite on 2026-03-25 with `./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --stop-on-failure`. The desktop/browser-flow test passed, but the mobile readability test failed with a timeout at `->resize(390, 844)` before the detail title became visible. The suite still uses `test()->actingAs($user)` with raw `visit(...)` entrypoints instead of the shared `browserLoginAndVisit()` helper. Adjacent refreshed suites already proved that live-auth entry and authenticated-session reuse are the stable pattern for browser proof on this branch.

**Primary recommendation:** keep all existing fixture seeding and detail-chip assertions, but refresh `DetailPageCategoryContextTest.php` around the shared live-auth bootstrap. Enter each browser proof through `browserLoginAndVisit()`, wait for the current movie/series detail title before chip assertions, and reuse the authenticated browser session for follow-up detail visits inside the same test instead of attempting a second login.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | `^12.0` | auth, routes, Inertia responses, browser test app runtime | Current app framework and auth source of truth. |
| `@inertiajs/react` | `^2.0.9` | detail-page rendering and browse navigation | Detail pages and category-chip navigation already use Inertia. |
| Pest | `^4.4` | test runner | Repo-standard PHP and browser test runner. |
| `pestphp/pest-plugin-browser` | `^4.3` | browser automation (`visit`, `waitForText`, `resize`, `script`) | Existing browser proof framework. |
| Playwright | `1.54.1` | browser engine used by Pest browser | Required runtime for the browser suite. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Shared browser auth helper | repo-local | canonical live login bootstrap | Use for all refreshed browser proof entrypoints. |
| Tailwind + local `Badge` | repo-local | hero chip styling and DOM hooks | Relevant when browser assertions depend on current chip placement and wrapping. |
| Spatie Laravel Data / generated TS types | repo-local | `category_context` contract | Relevant only if refreshed proof exposes a runtime prop regression. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `browserLoginAndVisit()` | `test()->actingAs($user)` only | Faster setup, but it no longer matches the repo browser-proof standard and is the most likely source of the current mobile/browser drift. |
| Reusing one authenticated page for follow-up detail visits | Calling `browserLoginAndVisit()` twice inside the same test | Adjacent suites showed repeat live-login inside one browser test can hit authenticated redirect paths and time out on login-copy assertions. |
| Refreshing the existing browser suite | Creating a new browser test file | The roadmap explicitly names `tests/Browser/DetailPageCategoryContextTest.php`; duplicating proof increases drift. |

## Architecture Patterns

### Recommended Project Structure
```text
tests/
├── Browser/
│   ├── DetailPageCategoryContextTest.php
│   └── Support/
│       └── browser-auth.php
resources/js/
├── components/
│   └── media-hero-section.tsx
└── pages/
    ├── movies/show.tsx
    └── series/show.tsx
```

### Pattern 1: Canonical browser entry goes through live auth
**What:** Start browser proof with `browserLoginAndVisit($user, route(...))`, then wait for the real detail page title before interacting with chips.
**When to use:** Every browser case in this phase.
**Example:**
```php
$page = browserLoginAndVisit($user, route('movies.show', ['model' => $movie->getKey()]))
    ->waitForText('Movie Detail Title')
    ->assertNoJavaScriptErrors();
```

### Pattern 2: Reuse the authenticated session for follow-up detail pages
**What:** After the first live login, navigate to the second detail page with a follow-up visit or `window.location.assign(...)` on the current authenticated page instead of logging in again.
**When to use:** Cross-media desktop proof and mobile movie→series readability proof inside the same test.
**Example:**
```php
$page->script(str_replace('__URL__', json_encode(route('series.show', ['model' => $series->getKey()]), JSON_THROW_ON_ERROR), <<<'JS'
    () => {
        window.location.assign(__URL__);

        return true;
    }
JS));

$page->waitForText('Series Detail Title');
```

### Pattern 3: Treat current detail titles as the first truthy assertion
**What:** Assert the current movie and series detail titles before chip visibility, click-through, or wrap/readability checks.
**When to use:** Both desktop navigation proof and mobile readability proof.
**Why:** Wrong-page failures then surface as navigation/auth drift instead of looking like stale chip-copy failures.

### Pattern 4: Keep chip assertions anchored to current DOM hooks and CSS behavior
**What:** Preserve `data-slot="hero-category-context"`, `data-slot="hero-category-chip"`, and computed-style checks for wrap/no-truncate behavior.
**When to use:** Readability and viewport assertions.
**Example:**
```php
expect(detailPageCategoryChipMetrics($page, $movieLongName))->toMatchArray([
    'found' => true,
    'textOverflow' => 'clip',
    'whiteSpace' => 'normal',
    'overflowWrap' => 'break-word',
]);
```

### Anti-Patterns to Avoid
- `actingAs`-only browser entry for final proof.
- A second live login inside the same browser test once the user is already authenticated.
- Editing runtime movie/series/detail components before proving the current failure is really in app behavior.
- Replacing DOM-based wrap assertions with screenshots or looser “page loaded” checks.
- Removing the current hidden/ignored browse handoff assertions; those are part of the shipped Phase 12 contract.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Browser auth bootstrap | Another file-local login helper | `tests/Browser/Support/browser-auth.php` | Phase 13 already established the canonical helper and current login copy. |
| Cross-page authenticated navigation | New browser support abstraction unless needed | existing page instance + `window.location.assign(...)` or equivalent | Lowest-churn way to keep one authenticated session alive inside the suite. |
| Chip selectors | Text-only broad selectors | existing `data-slot` hooks and helper functions | Current suite already has deterministic chip/row metrics helpers. |
| Runtime detail behavior changes | New hero or controller logic | existing shipped Phase 12 implementation | This phase is proof refresh, not product redesign. |

## Common Pitfalls

### Pitfall 1: Mobile timeout looks like a UI regression but starts at the browser entrypoint
**What goes wrong:** The mobile test times out before the detail title appears.
**Why it happens:** The suite bypasses the current live-auth entry standard, so the browser is not exercising the same stable path as other refreshed suites.
**How to avoid:** Use `browserLoginAndVisit()` and wait for the real detail title before resize-sensitive assertions.

### Pitfall 2: Second login in the same test hits the authenticated redirect path
**What goes wrong:** A follow-up series or movie step stalls on login-copy assertions even though the app is healthy.
**Why it happens:** The user is already authenticated, so re-entering the login helper can change the navigation path.
**How to avoid:** Reuse the authenticated page/session for follow-up detail visits in the same test.

### Pitfall 3: Wrong-page failures masquerade as stale chip or copy failures
**What goes wrong:** The test fails waiting for category-chip text, but the actual issue is auth or navigation drift.
**Why it happens:** Chip assertions run before the page identity is proven.
**How to avoid:** Always assert `Movie Detail Title` / `Series Detail Title` first, then run chip assertions.

### Pitfall 4: Mobile helpers accidentally target hidden desktop elements
**What goes wrong:** Wrap/readability or click checks inspect hidden elements rather than the visible mobile surface.
**Why it happens:** DOM lookup ignores visibility or viewport placement.
**How to avoid:** Keep the existing visibility-aware metrics helpers and only tighten waits/order around them.

## Code Examples

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

### Current hero chip DOM contract
```tsx
// Source: resources/js/components/media-hero-section.tsx
<motion.div data-slot="hero-category-context">
    {categoryContext.map((category) => (
        <Badge asChild>
            <Link data-slot="hero-category-chip" href={category.href} preserveScroll={false} preserveState={false}>
                {category.name}
            </Link>
        </Badge>
    ))}
</motion.div>
```

### Current detail-page consumers
```tsx
// Source: resources/js/pages/movies/show.tsx, resources/js/pages/series/show.tsx
<MediaHeroSection
    title={info.movie.name}
    categoryContext={category_context}
/>

<MediaHeroSection
    title={info.name}
    categoryContext={category_context}
/>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `actingAs()` browser entry without live auth proof | Shared `browserLoginAndVisit()` bootstrap for refreshed browser suites | Phase 13 / 2026-03-25 | Phase 15 should align to avoid harness drift. |
| Ad hoc suite-local auth handling | One Pest-loaded browser auth helper | Phase 13 / 2026-03-25 | Detail browser proof should reuse the same helper. |
| Shipped Phase 12 runtime plus stale proof harness | Shipped Phase 12 runtime plus refreshed proof harness | Phase 15 target | Only the proof needs alignment unless the refreshed suite exposes a real runtime bug. |

## Open Questions

1. **Should the refreshed suite split movie and series proof into separate tests, or keep combined cross-media flows?**
   - What we know: the current first test combines movie and series desktop proof; that is acceptable if it reuses one authenticated session.
   - Recommendation: keep the combined proof unless targeted stabilization demands a small split; prefer low-churn edits first.

2. **Should follow-up detail navigation use a helper or inline `window.location.assign(...)`?**
   - What we know: the shared auth helper already uses `window.location.assign(...)`, and adjacent suites reused the authenticated session successfully without creating more support code.
   - Recommendation: keep navigation inline unless duplication becomes clearly harmful during execution.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest `^4.4` + `pestphp/pest-plugin-browser` `^4.3` + Playwright `1.54.1` |
| Config file | `phpunit.xml`, `tests/Pest.php`, `tests/Browser/Support/browser-auth.php` |
| Quick run command | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="shows hero category chips and browse navigation for both media types" --stop-on-failure` |
| Full suite command | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php --stop-on-failure` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CTXT-01 | Movie detail browser proof reaches current title, visible chips, hidden-category browse handoff, and mobile wrap/readability assertions | browser + feature smoke | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="movie|hero category chips|mobile" --stop-on-failure` | ✅ |
| CTXT-02 | Series detail browser proof reaches current title, visible chips, ignored-category browse handoff, and mobile wrap/readability assertions | browser + feature smoke | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="series|hero category chips|mobile" --stop-on-failure` | ✅ |

### Sampling Rate
- **Per task commit:** run the matching single-test browser command for the touched proof.
- **Per wave merge:** `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --stop-on-failure`
- **Phase gate:** `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php --stop-on-failure`

### Wave 0 Gaps
None — the browser suite, auth helper, and supporting runtime coverage already exist.

## Sources

### Primary (HIGH confidence)
- `/home/coder/project/lionzhd/.planning/ROADMAP.md`
- `/home/coder/project/lionzhd/.planning/REQUIREMENTS.md`
- `/home/coder/project/lionzhd/.planning/STATE.md`
- `/home/coder/project/lionzhd/.planning/phases/12-detail-page-category-context/12-04-SUMMARY.md`
- `/home/coder/project/lionzhd/.planning/phases/13-refresh-search-and-navigation-browser-auth-proof/13-01-SUMMARY.md`
- `/home/coder/project/lionzhd/.planning/phases/14-refresh-ignored-discovery-browser-recovery-proof/14-01-SUMMARY.md`
- `/home/coder/project/lionzhd/tests/Browser/DetailPageCategoryContextTest.php`
- `/home/coder/project/lionzhd/tests/Browser/Support/browser-auth.php`
- `/home/coder/project/lionzhd/resources/js/components/media-hero-section.tsx`
- `/home/coder/project/lionzhd/resources/js/pages/movies/show.tsx`
- `/home/coder/project/lionzhd/resources/js/pages/series/show.tsx`
- Local reproduction on 2026-03-25: `./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --stop-on-failure`

### Secondary (MEDIUM confidence)
- `/home/coder/project/lionzhd/.planning/phases/12-detail-page-category-context/12-VERIFICATION.md`
- `/home/coder/project/lionzhd/tests/Pest.php`

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — same browser stack already used by adjacent refreshed suites.
- Architecture: HIGH — the target file, shared auth helper, and shipped runtime seams are explicit.
- Pitfalls: HIGH — the current mobile failure was reproduced locally and mapped to the existing suite entry pattern.

**Research date:** 2026-03-25
**Valid until:** 2026-04-08
