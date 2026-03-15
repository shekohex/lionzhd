# Project Research Summary

**Project:** LionzHD Streaming Platform Enhancements  
**Domain:** Per-user category personalization + discovery/search UX for a Laravel 12 + Inertia React Xtream VOD/series app  
**Researched:** 2026-03-15  
**Confidence:** MEDIUM

## Executive Summary

This is a brownfield discovery enhancement on an existing Laravel monolith: the app already has category browsing, media sync, and search, and the v1.1 milestone must add user-owned category behavior without breaking global category semantics. Expert patterns for this type of project use an overlay approach—global taxonomy stays canonical, user preferences are stored separately, and UI behavior is driven from a shared, server-owned projection.

Recommended implementation is additive and action-driven. Add `user_category_preferences`, introduce shared actions for personalized sidebar build and ignored-category filtering, and keep all personalization-aware reads going through those actions. Use existing React primitives (`dnd-kit`, `cmdk`, Radix) and query-state via `nuqs` rather than inventing a new state architecture. The result is controllable, testable, and compatible with current Laravel + Inertia contracts.

The highest risks are consistency risks: leaking personalization into shared tables, diverging hide/ignore semantics across endpoints, and filter timing bugs in search pagination. Keep semantics explicit, apply filters through one shared path, and gate rollout with a matrix-driven regression plan (per-user contrasts, pagination totals, and mode-specific search behavior).

## Key Findings

### Recommended Stack

Stick to existing stack components and add only focused dependencies needed for reorder/search/persistence UX.

**Core technologies:**
- **Laravel Eloquent + migrations (12.x):** powers per-user preference persistence and deterministic query behavior for browse/search.
- **Laravel Scout + current search engine (`laravel/scout` 10.15.x, `meilisearch/meilisearch-php` 1.14.x):** keeps discovery search aligned with backend filtering when payloads/index settings are updated.
- **`@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/modifiers` (6.3.1 / 10.0.0 / 9.0.0):** stable drag/drop + touch/keyboard patterns for sidebar and mobile sheet ordering.
- **`cmdk` (1.1.1):** searchable category navigation in web + mobile flows; upgrade needed for React 19 compatibility.
- **Pest + pest-browser + Playwright:** add end-to-end coverage for persistence, ordering, and search filter correctness.

**Supporting version constraints:** `nuqs` 2.4.3 for URL query state, keep existing Radix primitives, avoid client-only filtering logic that bypasses server constraints.

### Expected Features

Research indicates a single-phase priority band: correctness-first personalization, then interaction polish.

**Must have (table stakes):**
- Per-user category preference model for movies/series.
- Reorder, pin (max 5), and hide categories in sidebar.
- Ignore categories so matching titles disappear from discovery listings.
- Category labels on movie/series detail pages.
- Searchable category sidebar/navigation on web and mobile.
- Correct `media_type` filtering + explicit query params.
- Full-width results in single-type search modes.
- Reset/recovery UX for aggressive hide/ignore states.

**Should have (competitive):**
- Explicit hide-vs-ignore semantics and copy.
- Separate movie and series preference profiles.
- Cross-device persistence of preferences.
- Layout adaptation driven by media mode rather than cosmetic styling changes.

**Defer (v2+):**
- Bulk category management workflows.
- Onboarding hints/tooltips for behavior interpretation.
- Recommended pins or broader predictive personalization.

### Architecture Approach

Use a **user-scoped overlay pattern** on global categories with shared query composition actions. Keep discovery reads centralized so ignored-category behavior cannot diverge by endpoint.

**Major components:**
1. **`user_category_preferences` table/model + upsert action:** stores visibility/order/ignore/pin state by `user_id + media_type + category`.
2. **Personalization actions:** build sidebar projection and apply ignored-category filters for all discovery paths.
3. **Controllers/pages contract:** `VodStreamController`, `SeriesController`, `SearchController`, `LightweightSearchController`, and `category-sidebar.tsx` consume shared data contracts.
4. **Testing layer:** feature tests for semantics and matrix/browser tests for reorder/search/mobile parity and filtered pagination.

### Critical Pitfalls

1. **Global-category state leakage** — writing user prefs into shared `categories` table breaks multi-user isolation.
2. **Undeclared hide/ignore/pin semantics** — leads to inconsistent behavior across browse/search/sidebar/detail.
3. **Surface-by-surface filtering only** — ignored titles vanish in one page but still appear elsewhere.
4. **Post-pagination filtering** — search shows short/empty pages because ignored content was removed after pagination.
5. **Unstable ordering** — mixing pin rank and default order without normalization causes drift and cap violations.

## Implications for Roadmap

### Phase 1: Semantics contract + preference schema
**Rationale:** Prevents irreversible product debt; all later work depends on invariant definitions.
**Delivers:** behavior contract, migration for `user_category_preferences`, request/data objects, `media_type` scoping, and pin/enforcement rules.
**Addresses:** per-user preferences, reorder/pin/hide foundation, media-type separation.
**Avoids:** Pitfalls 1, 2, 3.

### Phase 2: Shared read-path integration
**Rationale:** Correctness must be consistent before interaction polish.
**Delivers:** new shared sidebar builder and ignored-category query action wired into movies/series browse and counters.
**Addresses:** hide/ignore correctness, stable ordering, ignored-category filtering in browse.
**Avoids:** Pitfalls 4 and 5.

### Phase 3: Sidebar + write path implementation
**Rationale:** UX for personalization is only safe after server contract is stable.
**Delivers:** drag/drop and pin/hide/search interactions in desktop/mobile sidebar, optimistic local state, preference endpoint write path.
**Addresses:** Searchable sidebar, reorder/pin/hide workflows, user-side controls.
**Avoids:** Pitfalls 6 and 10 (mobile parity and regressions).

### Phase 4: Search/detail integration
**Rationale:** Search is the largest trust surface; fixed filtering must be end-to-end.
**Delivers:** explicit search params (`media_type`, `sort_by`), single-mode filtered results, pagination correctness, detail category labels via local lookup.
**Addresses:** search bugfix + filtered full-width mode + category visibility context.
**Avoids:** Pitfalls 7 and 8.

### Phase 5: Validation, migration rollout, and smoke
**Rationale:** Brownfield rollout requires controlled migration and proof of consistency.
**Delivers:** matrix tests for all personalized states, migration/re-sync playbook, rollback and smoke checks.
**Addresses:** production readiness and release confidence.
**Avoids:** Pitfalls 9 and 10.

### Phase Ordering Rationale
- Phased by dependency: model/semantics → shared read layer → mutations/UI → search contract → rollout hardening.
- Prevents UX features from being built on inconsistent data behavior.
- Aligns with architecture by isolating cross-cutting concern (`ignored`/`hidden`/`pin`) early.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 2:** confirm Scout/meilisearch constraint behavior vs DB search path in this repository’s exact driver configuration.
- **Phase 4:** verify media-type pagination and single-mode payload shape under mixed results and filter combinations.

Phases with standard patterns (skip deep research):
- **Phase 1:** Laravel migration/actions and validation constraints.
- **Phase 3:** `dnd-kit` + `cmdk` + `nuqs` usage in existing Inertia patterns.
- **Phase 5:** test matrix + smoke checklist, rollback strategy.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Inputs reference existing versions and explicit compatibility checks in repository context. |
| Features | MEDIUM | P1 scope well-defined; some behavioral edges depend on product decision around recovery UX. |
| Architecture | HIGH | Strong fit with current action/controller structure and monolith boundaries. |
| Pitfalls | HIGH | Clear, phase-mapped risks with concrete prevention checks. |

**Overall confidence:** HIGH-MEDIUM

### Gaps to Address
- **Category assignment model scope:** existing media models still rely heavily on single category references; decide if v1.1 keeps this as-is or starts normalized assignment migration.
- **Search-engine capability variance:** final behavior differs if Scout is database-driver vs Meilisearch; lock this early in phase planning.
- **Recovery UX wording and copy:** determine exact empty states/reset messaging before development.

## Sources

### Primary
- `.planning/research/STACK.md`
- `.planning/research/FEATURES.md`
- `.planning/research/ARCHITECTURE.md`
- `.planning/research/PITFALLS.md`

### Secondary
- `.planning/PROJECT.md`
- Filesets referenced throughout research files (controllers/actions/pages/tests)

### Tertiary
- Official docs and package references linked in STACK and source files.

---
*Research completed: 2026-03-15*
*Ready for roadmap: yes*
