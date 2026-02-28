# Milestone v1: Streaming Platform Enhancements

**Status:** SHIPPED 2026-02-28
**Phases:** 01-07
**Total Plans:** 39

## Overview

This milestone turned LionzHD into a production-ready multi-user streaming companion for Xtream VOD + Series (no Live): category-based discovery, permissioned access, user-scoped download ownership, reliable aria2 download lifecycle, and per-user watchlist automation for new episodes.

## Phases

### Phase 01: Access Control

**Goal**: Users experience correct access boundaries (Admin/Member + Internal/External) and cannot reach admin-only areas when unauthorized.
**Depends on**: Nothing (first phase)
**Plans**: 5 plans

Plans:
- [x] 01-01-PLAN.md - Add persisted roles/subtypes + registration bootstrap
- [x] 01-02-PLAN.md - Gates + admin-only settings + Inertia 403 UX + prevent zero-admin
- [x] 01-03-PLAN.md - Admin user management UI (super-admin + Internal/External)
- [x] 01-04-PLAN.md - Server-side enforcement for External download restrictions
- [x] 01-05-PLAN.md - Frontend enforcement (badges, disabled states) + manual verification

### Phase 02: Download Ownership & Authorization

**Goal**: Download records and operations are user-owned and enforced consistently across pages and APIs.
**Depends on**: Phase 01
**Plans**: 5 plans

Plans:
- [x] 02-01-PLAN.md - Persist download ownership (DB + model + DTO)
- [x] 02-02-PLAN.md - Own new downloads + user-scoped active-download dedupe
- [x] 02-03-PLAN.md - Server enforcement (gates + routes + member scoping) + tests
- [x] 02-04-PLAN.md - Downloads UI updates (member ops + owner visibility + confirmations)
- [x] 02-05-PLAN.md - Admin owner filtering UX + manual verification

### Phase 03: Categories Sync & Categorization Correctness

**Goal**: Categories from Xtream (VOD + Series only) are synced and content retains correct category relationships.
**Depends on**: Phase 01
**Plans**: 4 plans

Plans:
- [x] 03-01-PLAN.md - Add Xtream category requests + defensive parsing tests
- [x] 03-02-PLAN.md - Add categories/sync-run persistence + DTO alignment
- [x] 03-03-PLAN.md - Implement core category sync correctness action + job + tests
- [x] 03-04-PLAN.md - Expose settings sync + history UI + empty-source confirmation flow

### Phase 04: Category Browse/Filter UX

**Goal**: Users can browse and filter movies/series by category with a sidebar-driven discovery flow.
**Depends on**: Phase 03
**Plans**: 6 plans

Plans:
- [x] 04-01-PLAN.md - Shared sidebar/filter DTOs + aggregate-count sidebar builder
- [x] 04-02-PLAN.md - Movies backend filtering + feature tests
- [x] 04-03-PLAN.md - Series backend filtering + feature tests
- [x] 04-04-PLAN.md - Shared sidebar UI + Movies/Series page wiring (Inertia partial reload + skeletons)
- [x] 04-05-PLAN.md - Indexes + TS type regen + manual UX verification
- [x] 04-06-PLAN.md - Mobile bottom sheet + close-on-select (gap closure)

### Phase 05: Download Lifecycle Reliability

**Goal**: Server-side downloads behave reliably (progress, cancel, resume, retry) and are covered by automated tests.
**Depends on**: Phase 02
**Plans**: 4 plans

Plans:
- [x] 05-01-PLAN.md - Persist download reliability lifecycle fields (DB + DTO + TS types)
- [x] 05-02-PLAN.md - Persisted cancel + sticky pause semantics + safe delete-partial + tests
- [x] 05-03-PLAN.md - Auto-retry with bounded exponential backoff (monitor+retry jobs) + cooldown enforcement + tests
- [x] 05-04-PLAN.md - Downloads UI reliability UX (progress/cancel/retry) + manual smoke checkpoint

### Phase 06: Mobile Infinite-Scroll Pagination

**Goal**: Mobile infinite scroll pagination is correct, deterministic, and regression-tested.
**Depends on**: Phase 04
**Plans**: 3 plans

Plans:
- [x] 06-01-PLAN.md - Server pagination determinism + snapshot cutoff + regression tests
- [x] 06-02-PLAN.md - Mobile infinite-scroll hook + page wiring + restore/error UX
- [x] 06-03-PLAN.md - Manual mobile infinite-scroll smoke verification checkpoint

### Phase 07: Auto Episodes (Schedules + Dedupe)

**Goal**: Users can schedule per-series monitoring that detects new episodes and auto-queues downloads without duplicates.
**Depends on**: Phase 05
**Plans**: 12 plans

Plans:
- [x] 07-01-PLAN.md - Allow GET schedules page for all (visible-but-disabled UX)
- [x] 07-02-PLAN.md - Add monitoring + known-episodes persistence (schema + models)
- [x] 07-03-PLAN.md - Implement schedule math (hourly/daily/weekly) + unit tests
- [x] 07-04-PLAN.md - Add activity log persistence (runs + events)
- [x] 07-05-PLAN.md - Add dispatcher + scan jobs and scheduler wiring
- [x] 07-06-PLAN.md - Implement scan/diff/queue with dedupe + cap + tests
- [x] 07-07-PLAN.md - Add HTTP endpoints + access control for monitoring
- [x] 07-10-PLAN.md - Add request validation + explicit 422 coverage for monitoring mutations
- [x] 07-12-PLAN.md - Add monitoring DTOs + typed props + TS type generation
- [x] 07-11-PLAN.md - Add explicit recent-only backfill on enable (no auto-run)
- [x] 07-08-PLAN.md - Series detail monitoring UX + schedule editor (checkpoint)
- [x] 07-09-PLAN.md - Central monitoring management page in settings/schedules (checkpoint)

---

## Milestone Summary

**Key Decisions**
- Keep Admin/Member with Internal/External subtype model and enforce restrictions through route-level gates.
- Keep aria2 and harden lifecycle reliability instead of replacing downloader engine.
- Keep infinite-scroll UX and fix deterministic boundary behavior instead of replacing with Load More.
- Keep categories as first-class model with explicit Uncategorized behavior and sync observability.
- Keep schedules page visible for all authenticated users while gating all monitoring mutations.

**Issues Resolved**
- Access-control boundaries enforced across settings, downloads, and monitoring routes.
- Download ownership and authorization wired end-to-end across APIs and UI.
- Category sync correctness (including empty-source safeguards and remapping) implemented.
- Mobile category and infinite-scroll UX regression gaps closed.
- Auto-episodes pipeline delivered with dedupe, scheduling, and activity visibility.

**Issues Deferred**
- None blocking milestone completion.

**Technical Debt Incurred**
- SyncCategories controller has one test-environment-only Inertia asset-version mismatch case.
- Manual reliability UX smoke checklist remains recommended for operational confidence.
- Manual mobile infinite-scroll smoke checklist remains recommended for operational confidence.

---

_For current project status, see `.planning/ROADMAP.md`._
