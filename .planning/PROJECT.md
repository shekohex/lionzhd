# LionzHD Streaming Platform Enhancements

## What This Is

LionzHD is a Laravel + Inertia streaming companion for Xtream VOD and Series catalogs. It now supports production-ready multi-user usage with category-based discovery, permissioned access control, user-owned downloads, hardened download lifecycle behavior, and per-user auto-episodes monitoring.

## Core Value

Users can quickly find the right movie/series and reliably get their own downloads with correct permissions and automation.

## Current Milestone: v1.1 Category Personalization & Search UX

**Goal:** Make discovery feel user-owned by adding per-user category controls and fixing search filtering behavior across web and mobile.

**Target features:**
- Per-user category preferences for movies and series: reorder, pin up to 5, hide
- Ignored categories remove matching titles from catalog listings for that user
- Category labels visible on movie and series detail pages
- Searchable category sidebar/navigation on web and mobile
- Search media-type filtering fixed with adaptive full-width filtered results and regression coverage

## Current State

- Latest shipped milestone: **v1** (2026-02-28)
- Shipped scope: access control, ownership and authorization, categories sync + browse UX, lifecycle reliability, mobile pagination correctness, auto-episodes scheduling/dedupe
- Delivery size: 7 phases, 39 plans, 99 tasks
- Archive: `.planning/milestones/v1-ROADMAP.md` and `.planning/milestones/v1-REQUIREMENTS.md`

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

### Active

- [ ] User can personalize movie and series category order per account, including pinning up to 5 categories
- [ ] User can hide categories from sidebar/navigation without affecting default behavior for other users
- [ ] User can ignore categories so matching movies and series are removed from their catalog listings
- [ ] User can see all assigned categories on movie and series detail pages
- [ ] User can search categories in sidebar/navigation on web and mobile, and search media-type filtering behaves correctly

### Out of Scope

- Live TV categories and Live playback — current product scope is VOD + series only
- Replacing aria2 with a different downloader — defer until hardening effort proves insufficient
- Switching mobile pagination to Load More — decision is to keep infinite scroll and fix current behavior

## Context

This is a brownfield Laravel 12 monolith with Inertia React frontend, Saloon integrations, and action-based application services. Xtream categories are now modeled and surfaced in browsing UX. Download lifecycle reliability is implemented with persisted lifecycle state and retry policy. Auto-episodes scheduling and dedupe queueing are live with monitoring activity visibility.

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

---
*Last updated: 2026-03-15 after starting milestone v1.1*
