# LionzHD Streaming Platform Enhancements

## What This Is

LionzHD is an existing Laravel + Inertia app for personal/team use that syncs Xtream Codes VOD and series catalogs, lets users browse content, and manages downloads. This milestone extends it into a more production-ready multi-user streaming companion with category-based discovery, permissioned access, user-scoped download ownership, and reliable automation around watchlists and episode downloads.

## Core Value

Users can quickly find the right movie/series and reliably get their own downloads with correct permissions and automation.

## Requirements

### Validated

- ✓ Sync VOD and series metadata from Xtream Codes into local storage and search indexes — existing
- ✓ Browse and search VOD and series content in the web UI — existing
- ✓ Add movies/series/episodes to a watchlist — existing
- ✓ Start downloads through aria2 and monitor status from the app — existing
- ✓ Generate signed direct download links for media — existing

### Active

- [ ] Fetch VOD and series categories from Xtream (exclude Live), store categories, and map movies/series to categories
- [ ] Add sidebar category browsing/filtering in both movies and series pages
- [ ] Add role model: first registered user is Admin, all others default to Member
- [ ] Add Member subtype: Internal vs External; External users can use direct links only, cannot schedule downloads
- [ ] Restrict admin-only areas: user management, system settings, sync/import controls, download operations, analytics/monitoring
- [ ] Scope download records and views per user so members only see their own downloads
- [ ] Add watchlist automation for series: per-user schedules (hourly, daily at time, weekly day+time) via jobs/queues
- [ ] Detect new episodes by comparing Xtream episode IDs and auto-download when enabled
- [ ] Fix download progress/abort/resume reliability while keeping aria2 and add lifecycle tests
- [ ] Fix mobile infinite-scroll boundary bug where last item can be skipped during page transition

### Out of Scope

- Live TV categories and Live playback — current product scope is VOD + series only
- Replacing aria2 with a different downloader — defer until hardening effort proves insufficient
- Switching mobile pagination to Load More — decision is to keep infinite scroll and fix current behavior

## Context

This is a brownfield Laravel 12 monolith with Inertia React frontend, Saloon integrations, and action-based application services. Xtream Codes is the primary media source, with categories currently not modeled for browsing. Downloads currently rely on aria2 and have lifecycle edge cases (progress, abort, resume). Existing codebase mapping already exists in `.planning/codebase/` and confirms established patterns for sync, watchlist, downloads, jobs, and UI pages.

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
| Category UX uses sidebar categories | Faster scanning/filtering for large catalogs and aligned with streaming UX | — Pending |
| Category filtering ships for both movies and series in v1 | Avoid fragmented discovery experience | — Pending |
| Member subtype is Internal + External | Need policy controls without introducing extra top-level role complexity | — Pending |
| External members are direct-link only and cannot schedule downloads | Enforce stricter resource/control boundaries for external users | — Pending |
| Download visibility is strictly user-scoped for members | Multi-user correctness and privacy | — Pending |
| Auto-download scheduling is per-user and calendar-like | Users need flexible cadence control (hourly/daily/weekly at specific times) | — Pending |
| New episode detection uses Xtream episode IDs | Most deterministic signal for newly available episodes | — Pending |
| Keep aria2 and harden lifecycle behavior | Reduce risk of engine migration while solving current reliability issues | — Pending |
| Keep infinite scroll on mobile and fix boundary logic | Preserve UX while addressing missed-item bug | — Pending |

---
*Last updated: 2026-02-25 after initialization*
