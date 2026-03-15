# Pitfalls Research

**Domain:** Brownfield per-user category personalization and search/filter fixes in an existing Laravel + Inertia multi-user streaming app
**Researched:** 2026-03-15
**Confidence:** HIGH

## Critical Pitfalls

### Pitfall 1: Writing per-user state onto the shared category table

**What goes wrong:**
One user’s reorder, pin, hide, or ignore change leaks into every other user, or the next category sync wipes out personalizations.

**Why it happens:**
The current system already has a global `categories` table and global sidebar builder, so the easiest brownfield move is adding `sort_order`, `hidden`, or `ignored` columns there.

**How to avoid:**
- Keep taxonomy canonical and shared.
- Add a separate `user_category_preferences` read/write model keyed by at least `{user_id, media_type, category_provider_id}`.
- Store user-only state there: manual order, pin rank, hidden flag, ignored flag.
- Treat sync as updating canonical categories only; never let sync mutate user preference rows.

**Warning signs:**
- A migration adds personalization columns to `categories`.
- `BuildCategorySidebarItems` starts reading or writing shared user-facing sort state.
- One user’s sidebar order changes after another user edits preferences.

**Phase to address:**
Phase 1 — Preference semantics + schema split.

---

### Pitfall 2: Not defining hide vs ignore vs pin semantics up front

**What goes wrong:**
The same category is hidden in the sidebar but still affects search; ignored items disappear from browse pages but remain in related-content lists; details pages either over-hide or re-expose ignored content.

**Why it happens:**
These features sound similar but are not interchangeable. Brownfield systems usually bolt them onto existing filters one endpoint at a time.

**How to avoid:**
- Write a behavior matrix before implementation.
- Explicitly define what each state affects for each surface: movies index, series index, search, sidebar search, detail pages, related items, watchlist, direct links.
- Recommended contract:
  - `pin`: ordering only
  - `hide`: remove from sidebar/navigation only
  - `ignore`: exclude titles from discovery/search listings for that user
  - detail pages: show canonical category labels, but do not silently use hidden/ignored state to erase metadata

**Warning signs:**
- Different controllers implement different semantics for the same flag.
- QA reports “works on movies page, not on search/mobile/detail.”
- Engineers debate expected behavior after coding has started.

**Phase to address:**
Phase 1 — Semantics contract + acceptance criteria.

---

### Pitfall 3: Keeping `category_id` as the only truth when the feature needs full category assignment data

**What goes wrong:**
Detail pages cannot reliably show all assigned categories, ignore filtering is incomplete, and future syncs become harder because media only carries one category slot.

**Why it happens:**
Current `vod_streams` and `series` models still rely on a single `category_id` field. It is tempting to keep stretching that field instead of introducing a proper assignment model.

**How to avoid:**
- Introduce canonical media↔category assignment tables for movies and series, or one normalized polymorphic assignment table.
- Backfill from the existing single `category_id` field.
- Keep the old column only as a temporary compatibility field if needed.
- Index assignments by `{media_type, media_id, category_provider_id}`.

**Warning signs:**
- Comma-delimited IDs or JSON arrays start appearing in `category_id`.
- Detail-page category badges come from ad hoc parsing instead of relations.
- Ignore filtering still checks only one category per item.

**Phase to address:**
Phase 1 — Canonical category assignment model + migration plan.

---

### Pitfall 4: Applying ignore filtering only in browse controllers, not in the shared read model

**What goes wrong:**
Ignored titles disappear from category browse pages but still appear in search, lightweight search, related rows, counts, and pagination totals.

**Why it happens:**
Current browse pages query Eloquent directly by `category_id`, while search uses separate Scout-based actions. In a brownfield app, teams patch each surface separately and drift.

**How to avoid:**
- Create one “visible catalog for user” query policy used by browse, search, counts, and detail-side discovery widgets.
- Decide early whether ignored-category filtering must happen in the search engine, in DB queries, or as post-filtering with corrected totals.
- Prefer pre-pagination filtering. Post-pagination filtering causes empty/short pages.

**Warning signs:**
- Same title is hidden in `/movies` but still returned by `/search`.
- Sidebar counts do not match visible result counts.
- Search totals remain high while rendered cards are low.

**Phase to address:**
Phase 2 — Personalization-aware query layer.

---

### Pitfall 5: Unstable ordering and pin enforcement

**What goes wrong:**
Pinned categories reshuffle after sync, hidden categories still occupy rank slots, movie and series ordering bleed together, or users end up with more than five pins.

**Why it happens:**
Teams persist a single absolute sort integer and keep mutating it as categories appear/disappear, instead of separating pin priority from fallback order.

**How to avoid:**
- Scope preferences by media type.
- Store `pin_rank` separately from `manual_order`.
- Normalize order values on every write.
- Enforce the “max 5 pins” rule transactionally, not only in the UI.
- Use a deterministic fallback order for categories with no user override.

**Warning signs:**
- Duplicate rank values appear for the same user/media type.
- Users can pin a sixth category via direct request.
- A sync introducing a new category changes unrelated category positions.

**Phase to address:**
Phase 2 — Preference write rules + ordering engine.

---

### Pitfall 6: Sidebar category search that drops selection or breaks mobile state

**What goes wrong:**
The selected category disappears when the sidebar search narrows the list, the mobile sheet closes before state settles, retry/error states lose the search input, or keyboard focus becomes unusable.

**Why it happens:**
The current sidebar component is a static list with separate desktop/mobile shells. Adding live search on top of that without a clear state model creates divergent behavior.

**How to avoid:**
- Keep the selected category visible even if it does not match the current sidebar search term.
- Preserve sheet/search state long enough to complete selection feedback.
- Test desktop and mobile flows separately.
- Make empty-state copy distinguish between “no categories exist,” “no categories match search,” and “categories failed to load.”

**Warning signs:**
- Selecting a category from mobile intermittently clears the filter.
- A hidden category can still be surfaced by sidebar search.
- QA can reproduce different behavior between desktop sidebar and mobile sheet.

**Phase to address:**
Phase 3 — Sidebar search UX + mobile parity.

---

### Pitfall 7: Fixing the media-type bug in the UI while leaving backend pagination/model selection wrong

**What goes wrong:**
The search page looks filtered, but the backend still splits `per_page` between movies and series, keeps two result sections, or calculates totals for data that should not be returned. Filtered pages render half-empty or inconsistent “Found X results” counts.

**Why it happens:**
Current search behavior is built around dual-type results, split limits, and section-specific pagination. A UI-only patch does not change the query contract.

**How to avoid:**
- When `media_type` is set, switch to a single-result-mode contract end to end.
- Do not split `per_page` across two datasets in filtered mode.
- Render full-width filtered results from a single paginator.
- Add explicit controller tests for movie-only and series-only totals, payload shape, and pagination links.

**Warning signs:**
- `media_type=movie` still computes or returns series payloads.
- Filtered results still render under two section headings.
- Page 2 of filtered search has fewer items than `per_page` without actually exhausting results.

**Phase to address:**
Phase 4 — Search contract fix + full-width filtered result mode.

---

### Pitfall 8: Post-filtering ignored categories after search pagination

**What goes wrong:**
Search pages come back short, empty, or with wrong totals because ignored items were removed after the search engine already paginated them.

**Why it happens:**
Brownfield search stacks often cannot apply user-specific filters inside the index query, so teams filter the page collection afterward.

**How to avoid:**
- Prefer DB-backed filtered search if the search backend cannot enforce per-user ignored-category filters.
- If staying on Scout/database search, ensure category visibility is applied before pagination.
- If post-filtering is unavoidable temporarily, mark it as an explicit short-lived compromise and recompute totals/pages correctly.

**Warning signs:**
- Users see “10 results” but only 3 cards.
- Page 1 is empty while page 2 has results.
- Ignoring a large category causes pagination to collapse unpredictably.

**Phase to address:**
Phase 4 — Search filtering correctness.

---

### Pitfall 9: Brownfield migration that breaks existing category browsing during rollout

**What goes wrong:**
Deploying new preference or assignment tables breaks category browse pages, detail pages, or sync because old code still assumes the legacy shape and the backfill is incomplete.

**Why it happens:**
Existing code paths already read `category_id` directly and build sidebars globally. Schema changes are acceptable here, which makes it easy to under-plan compatibility and recovery.

**How to avoid:**
- Use additive migrations first.
- Backfill from existing category data before cutting reads over.
- Keep a rollback path: either dual-read temporarily or keep a deterministic re-sync path ready.
- Run migration tests against a production-like snapshot.
- Feature-flag user personalization reads until backfill completes.

**Warning signs:**
- Deploy requires app and DB to switch in lockstep with no fallback.
- Not-null constraints are added before data exists.
- “We can always resync later” is the only recovery story.

**Phase to address:**
Phase 1 and Phase 5 — Migration design first, rollout validation last.

---

### Pitfall 10: Regression coverage that only proves pages render

**What goes wrong:**
Search bugs reappear, user-specific filtering leaks, and sidebar behavior regresses because tests only assert status codes or minimal payload presence.

**Why it happens:**
Current search tests mostly cover omitted `per_page` behavior. That is useful, but not enough for personalized visibility and media-type correctness.

**How to avoid:**
- Add a matrix test suite, not a few happy-path tests.
- Cover: hide vs ignore semantics, max pin limit, reordering stability, details-page category labels, sidebar search on desktop/mobile, movie-only search, series-only search, mixed search, ignored-category search exclusion, and pagination after filtering.
- Add tests that create two users with different preferences against the same catalog.

**Warning signs:**
- No test creates two users with contradictory category preferences.
- No assertion checks that ignored titles are absent from search.
- Search tests pass even if the wrong payload shape is returned.

**Phase to address:**
Phase 4 — Regression suite + Phase 5 smoke tests.

---

## Technical Debt Patterns

Shortcuts that look fast but will hurt this milestone.

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Add `hidden` / `ignored` / `sort_order` columns to `categories` | Fastest schema patch | Breaks multi-user isolation; sync clobbers personalization | Never |
| Keep using only `vod_streams.category_id` / `series.category_id` | No migration of relations | Cannot model full category assignments cleanly; future filters drift | Only as a temporary read-compat field during backfill |
| Filter ignored categories only in controllers | Small local diff | Search, counts, related content, and pagination diverge | Never |
| Enforce “max 5 pins” only in React | Easy demo | Direct requests violate invariants; bad data accumulates | Never |
| Keep search in dual-section mode even when `media_type` is selected | Less UI work | Totals, pagination, and adaptive layout stay wrong | Never |

## Filtering Correctness Traps

| Trap | Failure Mode | Prevention |
|------|--------------|------------|
| Ignore list applied to browse but not search | Hidden titles still discoverable | Centralize personalization-aware visibility rules across all read paths |
| Hide treated like ignore | Categories vanish and content disappears unexpectedly | Keep navigation visibility separate from content visibility |
| Detail pages respect ignore too aggressively | Metadata disappears for reachable items | Show canonical category labels on details; only discovery lists should be filtered unless explicitly decided otherwise |
| Sidebar search searches canonical categories instead of visible categories | Hidden categories resurface through search | Search the user-visible sidebar dataset, not the raw global taxonomy |
| Counts computed before ignore filtering | Sidebar and result totals lie | Compute counts from the same filtered dataset used for rendering |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Joining user preferences and category assignments on every request without indexes | Browse/search latency jumps; DB CPU rises | Add composite indexes on preference and assignment tables; cache sidebar payloads per user+media type | Breaks quickly once category counts and user count both grow |
| N+1 category badge loading on detail pages | Detail pages slow down after adding category labels | Eager load assignments/categories or pre-hydrate category badge data | Breaks immediately on detail pages with related-content widgets |
| Recomputing sidebar counts from full catalog each render | Mobile/category navigation feels sluggish | Precompute or efficiently aggregate counts; invalidate cache on sync/pref change | Breaks as catalog size grows, even with modest user count |
| Post-search filtering of ignored categories | Empty pages and wasted queries | Filter before paginate; use a query path that understands personalization | Breaks as soon as ignored categories are common |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Hidden categories simply vanish with no recovery affordance | Users think categories were deleted | Provide a clear manage/edit state with hidden-category recovery |
| Ignored categories have the same visual treatment as hidden ones | Users cannot predict whether titles will disappear | Use distinct labels/copy for “hidden from sidebar” vs “ignored from catalog” |
| Selected category disappears when sidebar search narrows results | Users lose context and cannot undo easily | Keep selected state anchored and always clearable |
| Movie-only / series-only search still shows split sections | Filtered UX feels broken and wastes space | Switch filtered mode to a single full-width result grid/list |
| Detail pages show category labels that link into ignored categories without explanation | Users can navigate into content they intended to suppress | Decide whether labels are informational only or navigable under ignore rules, then enforce consistently |

## Migration and Rollout Risks

| Risk | What Goes Wrong | Mitigation |
|------|-----------------|------------|
| Assignment-table backfill incomplete | Some titles lose category badges or ignore filtering | Add backfill validation counts and fallback reads until complete |
| Preference rows created without media-type scope | Movie preferences leak into series sidebar | Include media type in unique keys and fixture coverage |
| Legacy reads remain in one controller | One surface ignores personalization | Audit every entry point that reads categories or media discovery |
| Feature ships without observability | Hard to detect wrong totals or empty searches | Log preference mutations, search filter mode, and ignored-result drops; add post-deploy smoke checks |

## "Looks Done But Isn't" Checklist

- [ ] **Per-user preferences:** Two users can reorder/pin/hide/ignore the same category differently with no leakage.
- [ ] **Category semantics:** Hide affects navigation only; ignore affects discovery surfaces exactly as specified.
- [ ] **Canonical assignments:** Detail pages show all assigned categories from normalized data, not from hacked string parsing.
- [ ] **Sidebar search:** Works on desktop and mobile, preserves selected state, and does not resurface hidden categories.
- [ ] **Filtered search:** `media_type=movie` and `media_type=series` each use a single-result-mode payload and full-width layout.
- [ ] **Ignored search results:** Titles in ignored categories are absent from browse and search, not just from one page.
- [ ] **Pagination:** Totals, links, and item counts stay correct after personalization filters are applied.
- [ ] **Migration:** Fresh install, upgraded DB, and re-sync path all produce equivalent category/discovery behavior.

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Shared table used for per-user state | Phase 1 | Schema review shows separate preference table and user-scoped unique key |
| Hide/ignore semantics drift | Phase 1 | Acceptance matrix exists and is referenced by tests |
| Single `category_id` stretched too far | Phase 1 | Normalized assignment model exists and detail pages use it |
| Ignore filtering only patched into some surfaces | Phase 2 | Browse, search, and counts all use one personalization-aware read path |
| Ordering and pin instability | Phase 2 | Two-user tests prove stable ranks and hard pin limit |
| Sidebar search/mobile state regressions | Phase 3 | Web/mobile interaction tests cover selection, clearing, and empty/error states |
| Media-type search bug fixed only visually | Phase 4 | Controller and page tests assert single-mode filtered payload + layout |
| Post-pagination ignore filtering | Phase 4 | Search totals and per-page counts stay correct with ignored categories present |
| Migration rollout breaks discovery | Phase 5 | Upgrade test + smoke test + rollback/re-sync plan verified |
| Tests too shallow | Phase 4 and 5 | Regression matrix exists and post-deploy smoke suite passes |

## Sources

- Project context: `/home/coder/project/lionzhd/.planning/PROJECT.md`
- Current global category sidebar builder: `/home/coder/project/lionzhd/app/Actions/BuildCategorySidebarItems.php`
- Current canonical category model: `/home/coder/project/lionzhd/app/Models/Category.php`
- Current single-category media models: `/home/coder/project/lionzhd/app/Models/VodStream.php`, `/home/coder/project/lionzhd/app/Models/Series.php`
- Current search controller contract: `/home/coder/project/lionzhd/app/Http/Controllers/SearchController.php`
- Current search query DTO and split-mode behavior: `/home/coder/project/lionzhd/app/Data/SearchMediaData.php`, `/home/coder/project/lionzhd/app/Actions/SearchMovies.php`, `/home/coder/project/lionzhd/app/Actions/SearchSeries.php`
- Current search UI structure and filter handling: `/home/coder/project/lionzhd/resources/js/pages/search.tsx`
- Current sidebar UI structure: `/home/coder/project/lionzhd/resources/js/components/category-sidebar.tsx`
- Current search test coverage baseline: `/home/coder/project/lionzhd/tests/Feature/Controllers/SearchControllerTest.php`

---
*Pitfalls research for: LionzHD v1.1 category personalization + search UX milestone*
*Researched: 2026-03-15*
