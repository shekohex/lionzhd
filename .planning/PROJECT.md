# LionzHD Streaming Platform Enhancements

## What This Is

LionzHD is a Laravel + Inertia streaming companion for Xtream VOD and Series catalogs. It now ships production-ready multi-user access control, permissioned downloads and automation, and user-owned discovery with personalized categories, ignored filters, searchable navigation, and detail-page category context.

## Core Value

Users can quickly find the right movie/series and reliably get their own downloads with correct permissions and automation.

## Current State

- Latest shipped milestone: **v1.1 Category Personalization & Search UX** (2026-03-27)
- Shipped scope: per-user category personalization, ignored discovery recovery, searchable category navigation, canonical `/search` mode + history behavior, detail-page category chips, and refreshed browser proof
- Delivery size: 9 phases, 28 plans, 58 tasks
- Archive: `.planning/milestones/v1.1-ROADMAP.md`, `.planning/milestones/v1.1-REQUIREMENTS.md`, `.planning/milestones/v1.1-MILESTONE-AUDIT.md`
- Prior shipped milestone: **v1 Streaming Platform Enhancements** (2026-02-28)

## Next Milestone Goals

- Tighten automation with unplayed-only episode rules and per-series queue or storage caps.
- Add governance and audit controls for external-user quotas, rate limits, and direct-link history.
- Improve discovery UX with bulk category management and clearer onboarding for hide vs ignore behavior.

## Requirements

### Validated

- ✓ Sync VOD and series metadata from Xtream Codes into local storage and search indexes — existing
- ✓ Browse and search VOD and series content in the web UI — existing
- ✓ Add movies/series/episodes to a watchlist — existing
- ✓ Start downloads through aria2 and monitor status from the app — existing
- ✓ Generate signed direct download links for media — existing
- ✓ Fetch VOD and series categories from Xtream (exclude Live), store categories, and map movies/series to categories — v1
- ✓ Add sidebar category browsing/filtering in both movies and series pages — v1
- ✓ Add role model: first registered user is Admin, all others default to Member — v1
- ✓ Add Member subtype: Internal vs External; External users can use direct links only, cannot schedule downloads — v1
- ✓ Restrict admin-only areas: user management, system settings, sync/import controls, download operations, analytics/monitoring — v1
- ✓ Scope download records and views per user so members only see their own downloads — v1
- ✓ Add watchlist automation for series: per-user schedules (hourly, daily at time, weekly day+time) via jobs/queues — v1
- ✓ Detect new episodes by comparing Xtream episode IDs and auto-download when enabled — v1
- ✓ Fix download progress/abort/resume reliability while keeping aria2 and add lifecycle tests — v1
- ✓ Fix mobile infinite-scroll boundary bug where last item can be skipped during page transition — v1
- ✓ User can personalize movie and series category order per account, including pinning up to 5 categories — v1.1
- ✓ User can hide categories from sidebar/navigation without affecting default behavior for other users — v1.1
- ✓ User can ignore categories so matching movies and series are removed from their catalog listings — v1.1
- ✓ User can search categories in sidebar/navigation on web and mobile — v1.1
- ✓ Search media-type filtering stays URL-authoritative across refresh, deep links, and browser history — v1.1
- ✓ User can see all assigned categories on movie and series detail pages — v1.1

### Active

- [ ] User can restrict auto-downloads to unplayed episodes only
- [ ] User can limit maximum queued episodes per series
- [ ] User can define per-series caps for storage or download count
- [ ] Admin can configure quotas or rate limits for external users
- [ ] Admin can audit external direct-link usage history
- [ ] User can bulk manage many categories in a dedicated management flow
- [ ] User can see onboarding guidance that explains the difference between hiding and ignoring a category

### Out of Scope

- Live TV categories and Live playback — current product scope is VOD + series only
- Replacing aria2 with a different downloader — defer until hardening effort proves insufficient
- Switching mobile pagination to Load More — decision is to keep infinite scroll and fix current behavior
- Global category order or visibility changes — personalization remains per user and must not mutate shared taxonomy
- Separate search implementations for All, Movies, and Series — one shared search contract remains required

## Context

This is a brownfield Laravel 12 monolith with Inertia React frontend, Saloon integrations, and action-based application services. Shipped `v1.1` extended the existing access-control and download foundations with user-scoped category preferences, ignored discovery filtering, searchable navigation, canonical `/search` state handling, and normalized detail-page category chips.

Recent milestone stats: 156 files changed, +20,319 / -649 lines changed, 12 days from start to ship.

Known tech debt:
- Existing `console.log` placeholders remain in `resources/js/pages/movies/show.tsx`.
- Existing `console.log` placeholders remain in `resources/js/pages/series/show.tsx`.

Reference API source used for this milestone:
- Xtream player API reference: https://raw.githubusercontent.com/gtaman92/XtreamCodesExtendAPI/refs/heads/master/player_api.php

## Constraints

- **Audience**: Personal/team use now — optimize for practical production readiness in controlled usage
- **Milestone outcome**: Ship all six requested areas with tests, not partial foundation-only delivery
- **Data migration tolerance**: Breaking schema changes are acceptable; catalog can be re-synced if needed
- **Domain scope**: Live content remains unsupported and must be ignored in category implementation

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Category UX uses sidebar categories | Faster scanning/filtering for large catalogs and aligned with streaming UX | ✓ Shipped in v1 |
| Category filtering ships for both movies and series in v1 | Avoid fragmented discovery experience | ✓ Shipped in v1 |
| Member subtype is Internal + External | Need policy controls without introducing extra top-level role complexity | ✓ Shipped in v1 |
| External members are direct-link only and cannot schedule downloads | Enforce stricter resource/control boundaries for external users | ✓ Shipped in v1 |
| Download visibility is strictly user-scoped for members | Multi-user correctness and privacy | ✓ Shipped in v1 |
| Auto-download scheduling is per-user and calendar-like | Users need flexible cadence control (hourly/daily/weekly at specific times) | ✓ Shipped in v1 |
| New episode detection uses Xtream episode IDs | Most deterministic signal for newly available episodes | ✓ Shipped in v1 |
| Keep aria2 and harden lifecycle behavior | Reduce risk of engine migration while solving current reliability issues | ✓ Shipped in v1 |
| Keep infinite scroll on mobile and fix boundary logic | Preserve UX while addressing missed-item bug | ✓ Shipped in v1 |
| Keep category personalization as a user-scoped overlay on shared taxonomy | Avoid mutating shared category ordering or visibility across users | ✓ Shipped in v1.1 |
| Keep ignored discovery and category navigation on shared read paths | Prevent browse, navigation, and recovery behavior drift | ✓ Shipped in v1.1 |
| Keep `/search` URL-authoritative for media type, sort, and pagination | Refresh and history replay must restore the same committed search state | ✓ Shipped in v1.1 |
| Resolve detail-page category chips from normalized assignments | Avoid coupling detail context to user preference state or legacy payload fields | ✓ Shipped in v1.1 |
| Reuse one live browser auth bootstrap across milestone browser suites | Keep proof aligned with the current login experience and reduce suite drift | ✓ Shipped in v1.1 |

<details>
<summary>Archived milestone planning: v1.1 Category Personalization & Search UX</summary>

**Goal:** Make discovery feel user-owned by adding per-user category controls and fixing search filtering behavior across web and mobile.

**Target features:**
- Per-user category preferences for movies and series: reorder, pin up to 5, hide
- Ignored categories remove matching titles from catalog listings for that user
- Category labels visible on movie and series detail pages
- Searchable category sidebar/navigation on web and mobile
- Search media-type filtering fixed with adaptive full-width filtered results and regression coverage
</details>

---
*Last updated: 2026-03-27 after shipping milestone v1.1*
