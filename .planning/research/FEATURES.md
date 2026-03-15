# Feature Research

**Domain:** Discovery personalization + search UX for an existing Xtream-based VOD/series catalog
**Researched:** 2026-03-15
**Confidence:** MEDIUM

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist once a catalog app offers multi-user accounts and category-based discovery. Missing these makes personalization feel fake or inconsistent.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Per-user category preferences: reorder, pin, hide | In a large catalog, users expect the navigation to adapt to their habits, not stay globally fixed | MEDIUM | Keep prefs per user and per media type. Strong default: synced category order until user changes it. Pins should stay capped at 5 and render first. Hidden should remove from sidebar/navigation only, not destroy data. |
| Ignored categories remove matching titles from discovery listings | If a user says “I do not want this category,” they expect browse surfaces to stop showing those titles | HIGH | Apply to movies + series catalog listings and category-driven browsing. Do not mutate source mappings. Titles should still remain reachable from direct links, watchlist/history, or admin views if already known. |
| Category labels on detail pages | Users often land on detail pages from search/watchlist and need category context to decide relevance | LOW | Show all assigned categories as badges/chips on movie and series details, even if the category is hidden or ignored in navigation. This preserves context and trust. |
| Searchable category sidebar/navigation | Long category lists are painful without in-place filtering, especially on mobile | MEDIUM | Web: inline search field above sidebar list. Mobile: searchable sheet/drawer with sticky search field. Search should filter category labels only, not titles. Keep pinned categories visible when the search box is empty. |
| Correct media-type filtering in search | A Movies mode that still leaks series results reads as a bug, not a missing enhancement | MEDIUM | Server-side filter must be authoritative. UI chips/tabs should match query params and URL state. Add regression coverage for mode switching, back/forward nav, and deep links. |
| Adaptive full-width results for single-type search modes | When the user chooses Movies-only or Series-only, split layouts waste space and reduce scan speed | MEDIUM | In “All” mode, mixed presentation is fine. In single-type mode, switch to a full-width results grid/list tuned for that content type. Mobile should keep one-column flow; web should reclaim the secondary pane. |
| Reset and empty-state recovery | Personalization without a safe reset path creates support burden and user fear | LOW | Provide “Reset to default” per media type. If all categories are hidden or ignored, show a recovery empty state with clear restore actions. |

### Differentiators (Competitive Advantage)

Features that make personalization feel deliberate instead of a thin preference layer.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Clear separation between Hide and Ignore | Users can tidy navigation without losing catalog breadth, or aggressively suppress unwanted content when they mean it | MEDIUM | Strong UX language matters: Hide = remove category from navigation; Ignore = remove matching titles from discovery listings. This avoids accidental over-filtering. |
| Independent personalization for Movies vs Series | Users often organize movie browsing very differently from episodic browsing | MEDIUM | Store separate preference sets and reset controls. Do not force one ordering model across both media types. |
| Search mode drives layout, not just filtering | Faster scanning and fewer false negatives; the UI feels purposeful instead of patched | MEDIUM | “Movies” and “Series” modes should feel like dedicated result experiences, not a filtered copy of “All.” |
| Preference persistence across web and mobile | Multi-device continuity makes personalization feel owned by the account, not the device | MEDIUM | Persist server-side. Mobile and web should reflect the same order/pins/hidden/ignored state after refresh/login. |

### Anti-Features (Commonly Requested, Often Problematic)

Features that sound useful but usually add confusion, regression risk, or support cost.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Global category order/visibility changes for all users | Admin convenience | Breaks the core value of per-user personalization and creates preference conflicts in shared accounts | Keep source category sync global, but apply a per-user preference overlay at read time |
| Unlimited pinned categories | Users want full control | Once everything can be pinned, pins lose meaning and the sidebar becomes cluttered on mobile | Cap pins at 5 and keep the rest reorderable beneath them |
| Hard-deleting ignored categories from local mappings | Seems simpler than filtering | Destroys sync integrity, complicates re-sync, and leaks user preference into global catalog data | Keep ignored as a user-specific query filter only |
| Hiding or ignoring categories everywhere, including direct links/detail pages/watchlist | Users may assume “remove it everywhere” | Makes items appear broken or vanish unpredictably; hard to explain support cases | Limit ignore to discovery surfaces, but still show category badges/context on details |
| Separate search implementations for All/Movies/Series | Fastest way to ship visually | Behavior drifts, bugs multiply, and regressions return when one mode is patched independently | Use one search contract with a media-type parameter and layout variants on top |

## Feature Dependencies

```
[Per-user category preferences]
    ├──requires──> [Stable category IDs + existing item↔category mapping]
    ├──requires──> [User-scoped preference storage]
    └──enhances──> [Existing sidebar category browsing]

[Pinned categories]
    └──requires──> [Per-user category preferences]

[Hidden categories]
    └──requires──> [Per-user category preferences]

[Ignored categories]
    ├──requires──> [Per-user category preferences]
    ├──requires──> [Catalog queries that join/filter by category]
    └──conflicts──> [Naive global counts/caches for category listings]

[Category labels on detail pages]
    └──requires──> [Stable item↔category mapping]

[Searchable category sidebar]
    ├──requires──> [Existing category navigation]
    └──enhances──> [Pins + hidden categories]

[Correct media-type search filtering]
    ├──requires──> [Single search contract with media-type parameter]
    └──enhances──> [Adaptive full-width results]
```

### Dependency Notes

- **Per-user preferences require stable category identity:** reorder/pin/hide/ignore only stay correct if category IDs survive re-syncs.
- **Ignored categories require query-level enforcement:** filtering in the UI alone is insufficient; list endpoints/search endpoints must exclude matching titles consistently.
- **Ignored categories conflict with naive cached counts:** if counts are cached globally, users will see categories or result totals that contradict their preferences.
- **Adaptive full-width results should wait for the search filter fix:** otherwise the team may polish the wrong layout on top of incorrect data.
- **Detail-page category badges depend on the same category mapping used by discovery:** do not introduce a second source of truth.

## MVP Definition

### Launch With (v1.1)

- [ ] User-scoped category preference model for movies and series — foundation for every new personalization behavior
- [ ] Reorder + pin (max 5) + hide categories — core navigation ownership feature
- [ ] Ignore categories in catalog listings — biggest relevance improvement for discovery
- [ ] Category badges on movie and series detail pages — preserves context after filtering/personalization
- [ ] Searchable sidebar/navigation on web and mobile — necessary once category lists become user-tuned and potentially long
- [ ] Correct media-type search filtering + regression tests — functional bug fix before UX polish
- [ ] Full-width single-type search results — makes fixed filtering visibly useful
- [ ] Reset-to-default + “all hidden/ignored” recovery UX — required safety valve

### Add After Validation (v1.1.x)

- [ ] Bulk edit/manage mode for many categories — add if users have very large category sets and drag/drop becomes tedious
- [ ] Lightweight onboarding hint/tooltips for Hide vs Ignore — add if support confusion appears after release
- [ ] Usage-informed suggested pins — only if repeated behavior shows obvious “favorites” patterns

### Future Consideration (v2+)

- [ ] Cross-device advanced preference sync rules (e.g. temporary mobile-only ordering) — defer unless device-specific behavior becomes a real need
- [ ] Personalized category recommendations/auto-grouping — high complexity, weak need until base controls prove valuable

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| User-scoped category preference foundation | HIGH | MEDIUM | P1 |
| Reorder + pin + hide categories | HIGH | MEDIUM | P1 |
| Ignore categories in catalog listings | HIGH | HIGH | P1 |
| Category labels on detail pages | MEDIUM | LOW | P1 |
| Searchable category sidebar/navigation | HIGH | MEDIUM | P1 |
| Correct media-type search filtering | HIGH | MEDIUM | P1 |
| Full-width single-type search results | MEDIUM-HIGH | MEDIUM | P1 |
| Bulk category management tools | MEDIUM | MEDIUM | P2 |
| Suggested pins/recommendations | LOW-MEDIUM | MEDIUM-HIGH | P3 |

**Priority key:**
- P1: Must have for this milestone
- P2: Should have if adoption pain appears
- P3: Future optimization

## Competitor Feature Analysis

| Feature | Netflix / Prime-style pattern | Plex / Jellyfin-style pattern | Our Approach |
|---------|-------------------------------|-------------------------------|--------------|
| Navigation personalization | Usually lightweight row/order personalization; strong emphasis on fast access to preferred sections | More library-driven than per-user category curation | Add explicit per-user category controls because this app already uses categories as primary discovery IA |
| Search mode behavior | Strong type cues; single-type results usually take full width and suppress mixed noise | Often unified search first, then filtered library views | Keep one search flow, but let Movies/Series modes become visually dedicated result views |
| Hidden vs ignored content | Mainstream apps often reduce recommendation visibility rather than expose hard controls | Self-hosted apps tend to expose filtering, less often preference semantics | Make the distinction explicit because the catalog is operator-synced and users need predictable control |
| Category discoverability on mobile | Search/filter within long nav lists is common when taxonomies get large | Sidebars often degrade into long static lists | Use searchable mobile drawer/sheet rather than forcing long-scroll category selection |

## Recommendations for Scoping

- **Sequence foundation before polish:** ship preference storage + query semantics + search filtering before drag/drop or layout refinement.
- **Treat Hide and Ignore as separate capability sets:** combining them into one ambiguous toggle will create product debt fast.
- **Keep preference precedence simple:** `pinned first -> remaining visible categories in user order -> hidden excluded from nav -> ignored excluded from listings`.
- **Use safe defaults:** new users inherit synced category order, zero pins, zero hidden, zero ignored.
- **Respect recovery paths:** users must always be able to restore defaults even if they hide or ignore too aggressively.
- **Mobile should favor manage flows over dense inline controls:** search + toggle sheet first, advanced reorder in a dedicated full-screen manage view if needed.
- **Web can expose more directly:** inline sidebar search, visible pin affordances, and quick reset work well on desktop.

## Sources

- Internal project context: `/home/coder/project/lionzhd/.planning/PROJECT.md`
- Existing product constraints from milestone v1 and v1.1 scope in project planning
- Comparative UX pattern synthesis from mainstream streaming and self-hosted media apps (observational, not tied to one vendor spec) — LOW confidence for competitor specifics

---
*Feature research for: LionzHD Streaming Platform Enhancements*
*Researched: 2026-03-15*
