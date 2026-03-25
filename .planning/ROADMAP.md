# Roadmap: LionzHD Streaming Platform Enhancements

## Milestones

- SHIPPED: **v1 Streaming Platform Enhancements** - Phases 01-07 (39 plans), completed 2026-02-28. See `.planning/milestones/v1-ROADMAP.md`.
- ACTIVE: **v1.1 Category Personalization & Search UX** - Phases 8-15.

## Overview

Milestone v1.1 makes discovery feel user-owned without mutating shared taxonomy. The delivery path starts with per-user category controls, then applies shared filtering semantics to discovery, adds searchable navigation, fixes the search contract, finishes with category context on detail surfaces, and closes with refreshed browser proof for the shipped flows.

## Phases

- [x] **Phase 8: Personal Category Controls** - Per-user movie and series category order, pins, hide, and reset.
- [x] **Phase 9: Ignored Discovery Filters** - Ignored categories consistently remove titles from discovery with recovery states.
- [x] **Phase 10: Searchable Category Navigation** - Search categories inside web and mobile navigation. (completed 2026-03-20)
- [x] **Phase 11: Correct Search Mode UX** - Fix media-type search filtering, layout adaptation, and URL correctness. (completed 2026-03-22)
- [x] **Phase 12: Detail Page Category Context** - Show assigned categories on movie and series detail pages. (completed 2026-03-23)
- [ ] **Phase 13: Refresh Search and Navigation Browser Auth Proof** - Update stale browser auth bootstrap assertions so current search and navigation flows execute end to end.
- [ ] **Phase 14: Refresh Ignored Discovery Browser Recovery Proof** - Update ignored discovery browser expectations so current recovery flows prove correctly.
- [ ] **Phase 15: Refresh Detail Page Category Browser Proof** - Update stale detail-page browser expectations so category chips and browse handoff are proven end to end.

## Phase Details

### Phase 8: Personal Category Controls
**Goal**: Users can personalize category navigation separately for movies and series without affecting shared taxonomy or other users.
**Depends on**: Phase 07 shipped category browse foundations
**Requirements**: PERS-01, PERS-02, PERS-03, PERS-04, PERS-05
**Success Criteria** (what must be TRUE):
  1. User can keep different category arrangements for movies and series under the same account.
  2. User can reorder visible categories for a media type and see that order persist after refresh or a new session.
  3. User can pin up to 5 categories for a media type and pinned categories stay above non-pinned categories.
  4. User can hide a category from navigation for a media type without changing what another user sees.
  5. User can reset one media type back to default synced order and visibility.
**Plans**: 4 plans

Plans:
- [x] 08-01-PLAN.md — Add user-scoped preference storage and personalized sidebar contracts
- [x] 08-02-PLAN.md — Add instant-save preference mutation endpoints and scoped reset validation
- [x] 08-03-PLAN.md — Build browse-attached desktop/mobile manage UI and recovery flows
- [x] 08-04-PLAN.md — Switch movies/series browse payloads to personalized sidebar data and add read-path regression coverage

### Phase 9: Ignored Discovery Filters
**Goal**: Users can exclude unwanted categories from discovery results without losing a recovery path back to browseable state.
**Depends on**: Phase 8
**Requirements**: IGNR-01, IGNR-02
**Success Criteria** (what must be TRUE):
  1. User can ignore a movie category and matching movies disappear from that user's movie catalog listings.
  2. User can ignore a series category and matching series disappear from that user's series catalog listings.
  3. User sees a clear recovery action when hidden or ignored preferences leave no visible categories or no catalog results.
**Plans**: 7 plans

Plans:
- [x] 09-01-PLAN.md — Extend ignored-state sidebar and browse contracts
- [x] 09-02-PLAN.md — Apply movie browse ignored filtering and recovery metadata
- [x] 09-03-PLAN.md — Apply series browse ignored filtering and recovery metadata
- [x] 09-04-PLAN.md — Add shared sidebar ignore/unignore mutation affordances
- [x] 09-05-PLAN.md — Wire movie and series recovery UI with browser regressions
- [x] 09-06-PLAN.md — Add ignored preference persistence and validation flow
- [x] 09-07-PLAN.md — Fix ignored_ids browse payload persistence and recovery regressions

### Phase 10: Searchable Category Navigation
**Goal**: Users can quickly find categories inside navigation on both desktop and mobile surfaces.
**Depends on**: Phase 8
**Requirements**: NAVG-01
**Success Criteria** (what must be TRUE):
  1. User can search movie categories in the web sidebar and narrow the list to matching categories.
  2. User can search series categories in the web sidebar and choose a filtered match without losing navigation context.
  3. User can search categories in mobile navigation for the active media type and jump to a matching category.
**Plans**: 3 plans

Plans:
- [x] 10-01-PLAN.md — Create searchable navigation contracts and regression harness
- [x] 10-02-PLAN.md — Add desktop inline search-first sidebar results
- [x] 10-03-PLAN.md — Extend searchable navigation to mobile browse/manage flows

### Phase 11: Correct Search Mode UX
**Goal**: Users can trust media-type search filtering, layout mode, and URL-driven navigation across refreshes and deep links.
**Depends on**: Phase 9
**Requirements**: SRCH-01, SRCH-02, SRCH-03, SRCH-04
**Success Criteria** (what must be TRUE):
  1. User can switch search mode between all, movies, and series and see the UI state stay in sync with the URL.
  2. User sees only movie results in movie mode and only series results in series mode.
  3. User sees movie-only or series-only search results in a full-width results mode instead of the mixed-results layout.
  4. User can refresh, deep-link, and use back or forward navigation without losing correct search mode behavior.
**Plans**: 3 plans

Plans:
- [x] 11-01-PLAN.md — Canonicalize `/search` params and scaffold search-mode regressions
- [x] 11-02-PLAN.md — Move full-search state into the page and add segmented mode tabs
- [x] 11-03-PLAN.md — Render filtered full-width results and lock pagination/history restoration

### Phase 12: Detail Page Category Context
**Goal**: Users can see full category context on title detail pages.
**Depends on**: Phase 9
**Requirements**: CTXT-01, CTXT-02
**Success Criteria** (what must be TRUE):
  1. User can see all assigned categories on movie detail pages.
  2. User can see all assigned categories on series detail pages.
**Plans**: 5 plans

Plans:
- [x] 12-01-PLAN.md — Add canonical detail-category assignment storage and sync-order foundation
- [x] 12-02-PLAN.md — Expose movie detail category_context through the show controller
- [x] 12-03-PLAN.md — Expose series detail category_context through the show controller
- [x] 12-04-PLAN.md — Render hero category chips and lock click-through coverage
- [x] 12-05-PLAN.md — Add shared detail-category resolver and exported chip DTO contract

### Phase 13: Refresh Search and Navigation Browser Auth Proof
**Goal**: Current browser proof for searchable navigation and `/search` mode UX passes through the live auth flow and reaches the milestone assertions end to end.
**Depends on**: Phases 10-11
**Requirements**: NAVG-01, SRCH-01, SRCH-02, SRCH-03, SRCH-04
**Gap Closure**: Closes audit gaps from `browser-auth-harness -> search-and-navigation-browser-suites` and the broken `Search mode UX` plus `Searchable category navigation` flow proof.
**Success Criteria** (what must be TRUE):
  1. Browser login bootstrap used by search/navigation suites matches current auth copy and lands reliably on an authenticated page.
  2. `tests/Browser/SearchModeUxTest.php` reaches current mode, URL, history, and filtered-layout assertions without stale login blockers.
  3. `tests/Browser/SearchableCategoryNavigationTest.php` reaches current desktop and mobile category-search assertions without stale login blockers.
**Plans**: 3 plans

Plans:
- [ ] 13-01-PLAN.md — Centralize live browser login bootstrap and remove auth-helper drift
- [ ] 13-02-PLAN.md — Refresh `/search` browser auth proof against the current search contract
- [ ] 13-03-PLAN.md — Refresh searchable-navigation browser auth proof for desktop and mobile

### Phase 14: Refresh Ignored Discovery Browser Recovery Proof
**Goal**: Current browser proof for ignored discovery recovery matches the live browse/manage UI and proves recovery behavior end to end.
**Depends on**: Phase 9
**Requirements**: IGNR-01, IGNR-02
**Gap Closure**: Closes audit gaps from `ignored-discovery-browser-fixtures -> ignored-recovery-flow-proof` and the broken `Ignored discovery recovery` flow proof.
**Success Criteria** (what must be TRUE):
  1. `tests/Browser/IgnoredDiscoveryFiltersTest.php` asserts current recovery, empty-state, and ignored-row copy for movies and series.
  2. Browser coverage proves selected ignored categories recover in place without leaving the intended browse URL.
  3. Desktop and mobile ignored-state recovery assertions complete without stale UI text blockers.
**Plans**: 0 plans

### Phase 15: Refresh Detail Page Category Browser Proof
**Goal**: Current browser proof for detail-page category context matches the live detail UI and proves chip visibility plus browse handoff end to end.
**Depends on**: Phase 12
**Requirements**: CTXT-01, CTXT-02
**Gap Closure**: Closes audit gaps from `detail-page-browser-fixtures -> detail-category-chip-flow-proof` and the broken `Detail page category context` flow proof.
**Success Criteria** (what must be TRUE):
  1. `tests/Browser/DetailPageCategoryContextTest.php` asserts current movie and series detail titles before category-chip checks run.
  2. Browser coverage proves hero chips stay visible/readable and navigate back into browse flows for both media types.
  3. Detail-page browser assertions complete without stale title or copy blockers.
**Plans**: 0 plans

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 8. Personal Category Controls | 4/4 | Complete | 2026-03-18 |
| 9. Ignored Discovery Filters | 7/7 | Complete | 2026-03-19 |
| 10. Searchable Category Navigation | 3/3 | Complete    | 2026-03-20 |
| 11. Correct Search Mode UX | 3/3 | Complete    | 2026-03-22 |
| 12. Detail Page Category Context | 5/5 | Complete    | 2026-03-23 |
| 13. Refresh Search and Navigation Browser Auth Proof | 2/3 | In Progress|  |
| 14. Refresh Ignored Discovery Browser Recovery Proof | 0/0 | Pending | - |
| 15. Refresh Detail Page Category Browser Proof | 0/0 | Pending | - |
