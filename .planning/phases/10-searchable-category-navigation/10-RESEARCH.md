# Phase 10: Searchable Category Navigation - Research

**Researched:** 2026-03-19
**Domain:** Inline category search inside existing Laravel + Inertia + React navigation surfaces
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

### Search scope
- Search includes normal visible categories and ignored-visible categories.
- Hidden categories stay out of search results because hidden remains a recovery-only surface.
- `All categories` is fixed only for normal browse and should be hidden while a search query is active.
- `Uncategorized` remains part of search results and should stay anchored at the bottom when it matches.
- Search should feel fuzzy rather than strict exact/prefix-only matching.

### Search interaction
- Search is inline inside the existing navigation UI, not a separate page or detached modal flow.
- On desktop, the search field sits directly below the sidebar title.
- Results update live while the user types.
- Filtered results should rank top hits first rather than preserving the normal pinned/visible/ignored grouping.
- When search is active, show match results only; do not keep the currently selected non-matching category pinned into the filtered list.
- Search result presentation should be search-first, with bold matched text and keyboard navigation support on desktop (`Arrow` keys + `Enter`).

### No-match state
- No-match UI should use guided recovery rather than a bare empty message.
- The primary recovery action is `Clear search`.
- No-match copy should stay simple and should not bring hidden-category concepts into normal search messaging.
- Search query state resets each time the user leaves and reopens the navigation surface.

### Mobile flow
- Mobile search lives at the top of the existing category sheet.
- Opening the mobile sheet in browse mode should not auto-focus the search field.
- Selecting a category from mobile search closes the sheet and jumps to that category.
- Search should also be available in mobile manage mode, not just browse mode.

### OpenCode's Discretion
- Exact fuzzy-ranking algorithm and whether the fuzzy lookup stays client-side or uses server assistance, as long as the UX remains live and fuzzy.
- Exact visual treatment for bold match highlighting, result emphasis, and keyboard-focus states.
- Exact copy for guided no-match messaging and any search affordance hints.

### Deferred Ideas (OUT OF SCOPE)
None - discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| NAVG-01 | User can search categories within sidebar or navigation on web and mobile | Keep search client-side in `CategorySidebar`, rank over existing `visibleItems`, render search results through `cmdk`, and preserve existing URL-driven category selection via `use-category-browser`. |
</phase_requirements>

## Summary

Phase 10 should stay entirely inside the existing sidebar/sheet contract. The current backend already returns all data needed for category search: visible rows, ignored-visible rows, hidden rows, selected-category metadata, and URL-driven selection behavior. No new route, query param, or controller work is required for the recommended implementation.

The best fit is a shell-owned search state inside `resources/js/components/category-sidebar.tsx`, with a shared inline search UI rendered below the desktop title and at the top of the mobile sheet. When the query is empty, current browse/manage rendering stays unchanged. When the query is non-empty, the shell should switch to a flat ranked result list built from existing visible items only, excluding hidden rows and `All categories`, while keeping `Uncategorized` last if it matches.

**Primary recommendation:** Use the existing `cmdk` wrapper inline, keep filtering client-side over the already-loaded sidebar payload, and centralize query/reset/result-order logic in the sidebar shell instead of adding backend search.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `react` | `^19.1.0` | Local ephemeral search state and rendering | Already powers sidebar surfaces; no new state layer needed. |
| `@inertiajs/react` | `^2.0.9` | URL-driven category navigation | Existing browse selection already works and should remain unchanged. |
| `cmdk` | `1.0.0` | Accessible inline command/combobox interactions | Already installed, already wrapped in `resources/js/components/ui/command.tsx`, and gives keyboard navigation/filter plumbing without custom ARIA work. |
| `laravel/framework` | `^12.0` | Existing page/data contract | Current controllers + DTOs already provide the search dataset. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `lucide-react` | `^0.475.0` | Search / clear / state icons | Reuse for affordances in the input and empty state. |
| `pestphp/pest` | `^4.4` | Feature/browser regression coverage | Keep server payload and interaction regressions locked. |
| `pestphp/pest-plugin-browser` | `^4.3` | Desktop/mobile search flow tests | Needed for keyboard navigation, sheet close-on-select, and query reset assertions. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `cmdk` + local ranked results | `Fuse.js` `7.1.0` | Use Fuse only if ranking quality becomes hard to tune; current category lists are already local and small enough to avoid a new dependency. |
| Client-side search over sidebar payload | Server-assisted search endpoint | Server search adds latency, duplicate contracts, and desktop/mobile drift risk for a list the client already has. |

**Installation:**
```bash
# No additional packages required for the recommended path
```

## Architecture Patterns

### Recommended Project Structure
```text
resources/js/components/
├── category-sidebar.tsx
├── category-sidebar/
│   ├── browse.tsx
│   ├── manage.tsx
│   ├── shared-ui.tsx
│   └── search.tsx
tests/
├── Feature/Actions/BuildPersonalizedCategorySidebarTest.php
└── Browser/SearchableCategoryNavigationTest.php
```

### Pattern 1: Shell-owned ephemeral search state
**What:** Keep `query`, ranked results, and reset behavior in `CategorySidebar`, not in the page components and not in `use-category-browser`.
**When to use:** Always for this phase; search must work in desktop browse and mobile browse/manage without affecting URL state.
**Example:**

Source: repo architecture from `resources/js/components/category-sidebar.tsx`, `resources/js/components/category-sidebar/browse.tsx`, `resources/js/components/category-sidebar/manage.tsx`

```typescript
const [query, setQuery] = useState('')

const searchResults = useMemo(() => {
    const normalized = query.trim()

    if (normalized === '') {
        return null
    }

    return buildRankedCategorySearchResults({
        query: normalized,
        items: [...pinnedItems, ...visibleItems, ...ignoredVisibleItems],
        uncategorizedItem,
    })
}, [query, pinnedItems, visibleItems, ignoredVisibleItems, uncategorizedItem])
```

### Pattern 2: Replace grouped browse/manage content while search is active
**What:** Query-active mode should render a flat ranked result list instead of grouped browse rows or manage lists.
**When to use:** Any non-empty query.
**Example:**

Source: repo constraints from `10-CONTEXT.md`; interaction primitives from `https://raw.githubusercontent.com/dip/cmdk/v1.0.0/README.md`

```typescript
const showingSearch = searchResults !== null

return showingSearch ? (
    <CategorySidebarSearchResults
        query={query}
        results={searchResults}
        onClear={() => setQuery('')}
        onSelectCategory={handleSelectAndClose}
    />
) : view === 'manage' ? (
    <CategorySidebarManage {...manageProps} />
) : (
    <CategorySidebarBrowse {...browseProps} />
)
```

### Pattern 3: Use `cmdk` for keyboard navigation, not custom keydown code
**What:** Render inline `Command`, `CommandInput`, `CommandList`, and `CommandItem` for desktop/mobile search results.
**When to use:** Search result mode.
**Example:**

Source: `https://raw.githubusercontent.com/dip/cmdk/v1.0.0/README.md`, `https://ui.shadcn.com/docs/components/command`, `resources/js/components/ui/command.tsx`

```typescript
<Command loop shouldFilter={false}>
    <CommandInput value={query} onValueChange={setQuery} placeholder="Search categories" />
    <CommandList>
        <CommandEmpty>No categories match "{query}".</CommandEmpty>
        {results.map((item) => (
            <CommandItem key={item.id} value={item.id} onSelect={() => onSelectCategory(item.id)}>
                {renderHighlightedCategoryName(item.name, query)}
            </CommandItem>
        ))}
    </CommandList>
</Command>
```

### Anti-Patterns to Avoid
- **URL-backed search query:** violates the reset-on-reopen decision and entangles transient UI state with canonical category navigation.
- **Server roundtrip per keystroke:** unnecessary for already-loaded category data and risks UX lag.
- **Grouped search results:** preserving pinned/visible/ignored sections during search conflicts with the locked “top hits first” requirement.
- **Including hidden rows in search:** breaks the hidden-as-recovery-only boundary established in Phases 8-9.
- **Keeping non-matching selected category visible in filtered mode:** directly contradicts the locked decisions.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Keyboard roving focus + `Arrow`/`Enter` behavior | Custom input + manual key handlers | Existing `cmdk` wrapper | Accessibility, focus management, and selection semantics are already solved. |
| New category-search API | Controller endpoint for sidebar search | Existing `CategorySidebarData.visibleItems` payload | The client already has the full dataset; a new endpoint adds drift and latency. |
| Separate desktop/mobile search state machines | Duplicate logic in `browse.tsx` and `manage.tsx` | One shell-owned query/result model in `category-sidebar.tsx` | Needed so mobile manage mode gets search “for free” and reset rules stay consistent. |

**Key insight:** This phase is a local interaction upgrade, not a data-contract phase.

## Common Pitfalls

### Pitfall 1: Treating search like normal browse ordering
**What goes wrong:** Search results keep pinned/visible/ignored buckets, so the best textual match can land below weaker matches.
**Why it happens:** Reusing `CategorySidebarBrowse` row order directly.
**How to avoid:** Build a dedicated ranked result array for query-active mode.
**Warning signs:** Search for `dra` still shows pinned categories first even when they do not match best.

### Pitfall 2: Losing the hidden vs ignored boundary
**What goes wrong:** Hidden categories appear in search or no-match copy mentions hidden-category recovery.
**Why it happens:** Using all category collections as search input.
**How to avoid:** Search only `visibleItems` and explicitly exclude `hiddenItems`.
**Warning signs:** Search finds categories that are only visible inside manage recovery sections.

### Pitfall 3: Putting query state in the page or URL
**What goes wrong:** Search persists after closing the mobile sheet or after leaving/reopening the surface.
**Why it happens:** Search state is stored next to canonical page filters.
**How to avoid:** Reset query in the sidebar shell when the mobile sheet closes and after mobile result selection.
**Warning signs:** Reopening the sheet shows an old query and stale filtered results.

### Pitfall 4: Using `cmdk` built-in filtering without controlling final order
**What goes wrong:** `Uncategorized` cannot stay anchored last when it matches, or highlight order becomes hard to reason about.
**Why it happens:** Internal ranking is opaque for this phase’s custom ordering rules.
**How to avoid:** Use `shouldFilter={false}` and feed `cmdk` a pre-ranked array.
**Warning signs:** `Uncategorized` floats into the middle of search results.

### Pitfall 5: Assuming browser validation is already green
**What goes wrong:** Search work lands with no reliable desktop/mobile interaction coverage.
**Why it happens:** Existing browser infrastructure exists, but baseline execution in this research environment was not green.
**How to avoid:** Smoke-test browser harness before relying on search browser assertions.
**Warning signs:** `tests/Browser/CategorySidebarScrollTest.php` fails before Phase 10 code changes.

## Code Examples

Verified patterns from official sources and current repo:

### Inline command surface for category results

Source: `https://raw.githubusercontent.com/dip/cmdk/v1.0.0/README.md`, `resources/js/components/ui/command.tsx`

```typescript
<Command loop shouldFilter={false}>
    <CommandInput value={query} onValueChange={setQuery} placeholder="Search categories" />
    <CommandList>
        <CommandEmpty>No categories match "{query}".</CommandEmpty>
        {results.map((item) => (
            <CommandItem key={item.id} value={item.id} onSelect={() => onSelectCategory(item.id)}>
                {renderHighlightedCategoryName(item.name, query)}
            </CommandItem>
        ))}
    </CommandList>
</Command>
```

### Search dataset assembly from current sidebar props

Source: `resources/js/components/category-sidebar.tsx`, `app/Actions/BuildPersonalizedCategorySidebar.php`

```typescript
function buildSearchableItems(input: {
    pinnedItems: CategorySidebarItem[]
    visibleItems: CategorySidebarItem[]
    ignoredVisibleItems: CategorySidebarItem[]
    uncategorizedItem: CategorySidebarItem | null
}) {
    return [
        ...input.pinnedItems,
        ...input.visibleItems,
        ...input.ignoredVisibleItems,
        ...(input.uncategorizedItem ? [input.uncategorizedItem] : []),
    ]
}
```

### Guided no-match recovery

Source: `10-CONTEXT.md`, `https://raw.githubusercontent.com/dip/cmdk/v1.0.0/README.md`

```typescript
<CommandEmpty>
    <div className="space-y-3 py-6 text-center">
        <p className="text-sm font-medium">No categories match "{query}".</p>
        <Button type="button" variant="outline" size="sm" onClick={() => setQuery('')}>
            Clear search
        </Button>
    </div>
</CommandEmpty>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Static grouped button list | Inline command-style result mode inside the same surface | Phase 10 plan | Search gets keyboard support and ranked matching without a new page/modal. |
| Server-assisted lookup for every query | Client-side filtering over already-loaded sidebar payload | Current recommendation | Live UX, no new controller contract, no desktop/mobile drift. |
| Manual keydown handling | `cmdk` combobox primitives | `cmdk` 1.x in current repo | Less custom accessibility work. |

**Deprecated/outdated:**
- New endpoint for category search: unnecessary for the current payload shape and search scope.
- Search result grouping by pinned/ignored state: conflicts with the locked search-first ranking requirement.

## Open Questions

1. **How aggressive should fuzzy ranking be?**
   - What we know: Search must feel fuzzy and forgiving, not exact-only.
   - What's unclear: Exact scoring thresholds and tie-break rules.
   - Recommendation: Start with a small local scorer over normalized names plus keywords; move to Fuse only if usability feedback says ranking quality is insufficient.

2. **Is browser coverage stable enough to gate this phase?**
   - What we know: Pest browser infrastructure exists.
   - What's unclear: In this research environment, `tests/Browser/CategorySidebarScrollTest.php` failed before any Phase 10 work.
   - Recommendation: Add a browser smoke check early in Wave 0 before treating browser search tests as a merge gate.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest `^4.4` + `pest-plugin-browser` `^4.3` |
| Config file | `phpunit.xml` |
| Quick run command | `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php` |
| Full suite command | `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Browser/SearchableCategoryNavigationTest.php` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| NAVG-01 | Search dataset excludes hidden rows, hides `All categories` on active query, keeps matching `Uncategorized` last | feature | `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php --filter="search"` | ✅ |
| NAVG-01 | Desktop movies + series sidebar search ranks matches and supports keyboard selection | browser | `./vendor/bin/pest tests/Browser/SearchableCategoryNavigationTest.php --filter="desktop"` | ❌ Wave 0 |
| NAVG-01 | Mobile sheet search works for active media type, closes on select, and resets after reopen | browser | `./vendor/bin/pest tests/Browser/SearchableCategoryNavigationTest.php --filter="mobile"` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php`
- **Per wave merge:** `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Browser/SearchableCategoryNavigationTest.php`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] Add search-focused assertions to `tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php` for result-shaping rules.
- [ ] Create `tests/Browser/SearchableCategoryNavigationTest.php` for desktop keyboard navigation and mobile sheet flows.
- [ ] Investigate current browser baseline; `./vendor/bin/pest tests/Browser/CategorySidebarScrollTest.php` failed during research before Phase 10 changes.

## Sources

### Primary (HIGH confidence)
- Local repo: `resources/js/components/category-sidebar.tsx`, `resources/js/components/category-sidebar/browse.tsx`, `resources/js/components/category-sidebar/manage.tsx`, `resources/js/hooks/use-category-browser.ts`
- Local repo: `app/Actions/BuildPersonalizedCategorySidebar.php`, `app/Http/Controllers/VodStream/VodStreamController.php`, `app/Http/Controllers/Series/SeriesController.php`
- Local repo: `package.json`, `composer.json`, `phpunit.xml`
- `https://raw.githubusercontent.com/dip/cmdk/v1.0.0/README.md` - inline command API, `filter`, `shouldFilter={false}`, `keywords`, `loop`, accessibility notes
- `https://ui.shadcn.com/docs/components/command` - current wrapper usage and relationship to `cmdk`

### Secondary (MEDIUM confidence)
- `https://raw.githubusercontent.com/krisk/Fuse/master/README.md` - alternative fuzzy-search dependency only if local scoring is insufficient
- `https://raw.githubusercontent.com/krisk/Fuse/master/package.json` - current Fuse package version metadata

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - mostly derived from the current repo plus official library docs.
- Architecture: HIGH - locked phase decisions map cleanly onto the existing sidebar shell and DTO contract.
- Pitfalls: MEDIUM - mostly strong, but browser-harness reliability needs confirmation.

**Research date:** 2026-03-19
**Valid until:** 2026-04-18
