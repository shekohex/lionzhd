# Roadmap: LionzHD Streaming Platform Enhancements

## Overview

This milestone turns LionzHD into a more production-ready multi-user streaming companion for Xtream VOD + Series (no Live): category-based discovery, permissioned access, user-scoped download ownership, reliable aria2 download lifecycle, and per-user watchlist automation for new episodes.

## Phases

- [x] **Phase 1: Access Control** - Admin/Member + Internal/External enforcement with admin-only areas locked down
- [x] **Phase 2: Download Ownership & Authorization** - Downloads are owned, private, and role-correct across UI and APIs
- [ ] **Phase 3: Categories Sync & Categorization Correctness** - Admin syncs categories (excluding Live) and content stays correctly categorized
- [ ] **Phase 4: Category Browse/Filter UX** - Users browse and filter movies/series by sidebar categories (incl. Uncategorized)
- [ ] **Phase 5: Download Lifecycle Reliability** - Accurate progress + correct cancel/resume/retry behavior with tests
- [ ] **Phase 6: Mobile Infinite-Scroll Pagination** - Mobile infinite scroll is deterministic, doesn’t skip items, and is regression-tested
- [ ] **Phase 7: Auto Episodes (Schedules + Dedupe)** - Per-user series monitoring schedules detect new episodes and auto-queue without duplicates

## Phase Details

### Phase 1: Access Control
**Goal**: Users experience correct access boundaries (Admin/Member + Internal/External) and cannot reach admin-only areas when unauthorized.
**Depends on**: Nothing (first phase)
**Requirements**: ACCS-01, ACCS-02, ACCS-03, ACCS-04, ACCS-05, ACCS-06
**Success Criteria** (what must be TRUE):
  1. First registered user is Admin; subsequent users are Member by default.
  2. Admin can mark members as Internal or External.
  3. Member cannot access admin-only areas (user management, system settings, sync/import controls, download operations, analytics/monitoring).
   4. External member can only use direct-download links and cannot use server-download actions.
   5. External member cannot configure or run auto-download schedules.
**Plans**: 5 plans

Plans:
- [x] 01-01-PLAN.md — Add persisted roles/subtypes + registration bootstrap
- [x] 01-02-PLAN.md — Gates + admin-only settings + Inertia 403 UX + prevent zero-admin
- [x] 01-03-PLAN.md — Admin user management UI (super-admin + Internal/External)
- [x] 01-04-PLAN.md — Server-side enforcement for External download restrictions
- [x] 01-05-PLAN.md — Frontend enforcement (badges, disabled states) + manual verification

### Phase 2: Download Ownership & Authorization
**Goal**: Download records and operations are user-owned and enforced consistently across pages and APIs.
**Depends on**: Phase 1
**Requirements**: DOWN-01, DOWN-02, DOWN-03, DOWN-04
**Success Criteria** (what must be TRUE):
  1. Member can see only their own downloads in downloads pages and APIs.
  2. Member can operate only on their own downloads (pause, resume, cancel, retry).
  3. Admin can view and operate on downloads across all users.
  4. Each new download is owned by the initiating user and persists with that ownership.
**Plans**: 5 plans

Plans:
- [x] 02-01-PLAN.md — Persist download ownership (DB + model + DTO)
- [x] 02-02-PLAN.md — Own new downloads + user-scoped active-download dedupe
- [x] 02-03-PLAN.md — Server enforcement (gates + routes + member scoping) + tests
- [x] 02-04-PLAN.md — Downloads UI updates (member ops + owner visibility + confirmations)
- [x] 02-05-PLAN.md — Admin owner filtering UX + manual verification

### Phase 3: Categories Sync & Categorization Correctness
**Goal**: Categories from Xtream (VOD + Series only) are synced and content retains correct category relationships.
**Depends on**: Phase 1
**Requirements**: DISC-05, DISC-06
**Success Criteria** (what must be TRUE):
  1. Admin can sync VOD and series categories from Xtream while excluding Live categories.
  2. User can open content that remains correctly categorized based on synced category relationships.
  3. Re-running category sync does not break existing categorization or create obvious duplicates.
**Plans**: TBD

### Phase 4: Category Browse/Filter UX
**Goal**: Users can browse and filter movies/series by category with a sidebar-driven discovery flow.
**Depends on**: Phase 3
**Requirements**: DISC-01, DISC-02, DISC-03, DISC-04
**Success Criteria** (what must be TRUE):
  1. User can browse movies by category using a sidebar on movies pages.
  2. User can browse series by category using a sidebar on series pages.
  3. User can filter movies to a selected category, including an explicit Uncategorized option.
  4. User can filter series to a selected category, including an explicit Uncategorized option.
**Plans**: TBD

### Phase 5: Download Lifecycle Reliability
**Goal**: Server-side downloads behave reliably (progress, cancel, resume, retry) and are covered by automated tests.
**Depends on**: Phase 2
**Requirements**: RELY-01, RELY-02, RELY-03, RELY-04, RELY-05, RELY-06
**Success Criteria** (what must be TRUE):
  1. User can see accurate progress updates for active downloads.
  2. User can abort a download and see a correct terminal canceled state.
  3. User can resume a paused or interrupted download and continue from prior progress where possible.
  4. Failed downloads show actionable failure states and can be retried; transient failures are retried automatically with bounded backoff rules.
  5. Download lifecycle behavior (progress, abort, resume, retry) is covered by automated tests.
**Plans**: TBD

### Phase 6: Mobile Infinite-Scroll Pagination
**Goal**: Mobile infinite scroll pagination is correct, deterministic, and regression-tested.
**Depends on**: Phase 4
**Requirements**: MOBL-01, MOBL-02, MOBL-03
**Success Criteria** (what must be TRUE):
  1. User does not miss the last item when mobile infinite scroll crosses page boundaries.
  2. User sees deterministic ordering across mobile infinite-scroll pagination.
  3. Mobile infinite-scroll boundary behavior is covered by automated regression tests.
**Plans**: TBD

### Phase 7: Auto Episodes (Schedules + Dedupe)
**Goal**: Users can schedule per-series monitoring that detects new episodes and auto-queues downloads without duplicates.
**Depends on**: Phase 5
**Requirements**: AUTO-01, AUTO-02, AUTO-03, AUTO-04, AUTO-05, AUTO-06
**Success Criteria** (what must be TRUE):
  1. User can enable automatic new-episode monitoring for a watched series.
  2. User can configure an hourly, daily-at-time, or weekly day+time monitoring schedule.
  3. System detects new episodes by comparing Xtream episode IDs against known episode IDs for that user-series.
  4. System auto-queues download of newly detected episodes and prevents duplicate queue entries for the same episode/user.
**Plans**: TBD

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Access Control | 5/5 | Complete | 2026-02-25 |
| 2. Download Ownership & Authorization | 5/5 | Complete | 2026-02-25 |
| 3. Categories Sync & Categorization Correctness | 0/TBD | Not started | - |
| 4. Category Browse/Filter UX | 0/TBD | Not started | - |
| 5. Download Lifecycle Reliability | 0/TBD | Not started | - |
| 6. Mobile Infinite-Scroll Pagination | 0/TBD | Not started | - |
| 7. Auto Episodes (Schedules + Dedupe) | 0/TBD | Not started | - |
