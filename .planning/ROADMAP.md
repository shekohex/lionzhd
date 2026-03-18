# Roadmap: LionzHD Streaming Platform Enhancements

## Milestones

- SHIPPED: **v1 Streaming Platform Enhancements** - Phases 01-07 (39 plans), completed 2026-02-28. See `.planning/milestones/v1-ROADMAP.md`.
- ACTIVE: **v1.1 Category Personalization & Search UX** - Phases 8-12.

## Overview

Milestone v1.1 makes discovery feel user-owned without mutating shared taxonomy. The delivery path starts with per-user category controls, then applies shared filtering semantics to discovery, adds searchable navigation, fixes the search contract, and finishes with category context on detail surfaces.

## Phases

- [x] **Phase 8: Personal Category Controls** - Per-user movie and series category order, pins, hide, and reset.
- [ ] **Phase 9: Ignored Discovery Filters** - Ignored categories consistently remove titles from discovery with recovery states.
- [ ] **Phase 10: Searchable Category Navigation** - Search categories inside web and mobile navigation.
- [ ] **Phase 11: Correct Search Mode UX** - Fix media-type search filtering, layout adaptation, and URL correctness.
- [ ] **Phase 12: Detail Page Category Context** - Show assigned categories on movie and series detail pages.

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
**Plans**: TBD

### Phase 10: Searchable Category Navigation
**Goal**: Users can quickly find categories inside navigation on both desktop and mobile surfaces.
**Depends on**: Phase 8
**Requirements**: NAVG-01
**Success Criteria** (what must be TRUE):
  1. User can search movie categories in the web sidebar and narrow the list to matching categories.
  2. User can search series categories in the web sidebar and choose a filtered match without losing navigation context.
  3. User can search categories in mobile navigation for the active media type and jump to a matching category.
**Plans**: TBD

### Phase 11: Correct Search Mode UX
**Goal**: Users can trust media-type search filtering, layout mode, and URL-driven navigation across refreshes and deep links.
**Depends on**: Phase 9
**Requirements**: SRCH-01, SRCH-02, SRCH-03, SRCH-04
**Success Criteria** (what must be TRUE):
  1. User can switch search mode between all, movies, and series and see the UI state stay in sync with the URL.
  2. User sees only movie results in movie mode and only series results in series mode.
  3. User sees movie-only or series-only search results in a full-width results mode instead of the mixed-results layout.
  4. User can refresh, deep-link, and use back or forward navigation without losing correct search mode behavior.
**Plans**: TBD

### Phase 12: Detail Page Category Context
**Goal**: Users can see full category context on title detail pages.
**Depends on**: Phase 9
**Requirements**: CTXT-01, CTXT-02
**Success Criteria** (what must be TRUE):
  1. User can see all assigned categories on movie detail pages.
  2. User can see all assigned categories on series detail pages.
**Plans**: TBD

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 8. Personal Category Controls | 4/4 | Complete | 2026-03-18 |
| 9. Ignored Discovery Filters | 0/TBD | Not started | - |
| 10. Searchable Category Navigation | 0/TBD | Not started | - |
| 11. Correct Search Mode UX | 0/TBD | Not started | - |
| 12. Detail Page Category Context | 0/TBD | Not started | - |
