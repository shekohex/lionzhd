# Feature Research

**Domain:** Xtream-based VOD/series streaming companion (multi-user + downloader)
**Researched:** 2026-02-25
**Confidence:** MEDIUM

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete/untrustworthy.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Category browsing + filtering (VOD + Series) | Large catalogs are unusable without category navigation; IPTV-style UX norm | MEDIUM | Store categories and map items; include “All”, “Uncategorized”, empty states; category selection must be sticky across navigation; exclude Live domain. |
| Category UX on mobile (fast + stable) | Category switching should feel instant; mobile users are majority for “companion” usage | MEDIUM | Cache category lists; preserve scroll positions per category; avoid full list re-render on filter changes; prefetch next page. |
| RBAC: Admin vs Member (admin-only areas locked) | Any multi-user server expects an admin surface and locked settings | MEDIUM | Admin-only: user management, system settings, sync/import, download operations, monitoring; Members can browse/search/watchlist/direct-link (per policy). (Jellyfin supports admin flag per user.) |
| Member subtype policies: Internal vs External | Sharing access beyond core team requires guardrails (bandwidth/storage/control) | MEDIUM | External users: direct links only; no scheduling; ideally no server-side downloads. Mirrors “share access but restrict capabilities” patterns (e.g., Plex “Allow Downloads” permission model). |
| User-scoped downloads (privacy + correctness) | Multi-user download lists must not leak titles/actions across users | HIGH | Downloads need explicit owner; list views filtered by owner for Members; Admin can view/manage all. Explicitly avoid “downloads have no ownership” behavior (Plex FAQ notes downloads can be visible to other managed users on same device). |
| Download queue UX: pause/cancel/retry + error visibility | Users expect a queue with control and actionable failures | MEDIUM | Clear states: queued/preparing/downloading/paused/failed/completed/canceled; show failure reason + “Retry”; avoid silent stalls. (Plex documents in-progress queue and error listing.) |
| Download lifecycle reliability (progress/abort/resume) | If downloads are flaky, the app’s core value collapses | HIGH | Persist state across app restarts; idempotent commands; handle aria2 reconnect; prevent duplicates; reconcile “actual files on disk” vs “DB state”; add lifecycle tests. |
| Mobile infinite scroll correctness | Skipping/duplicating items destroys browsing trust | MEDIUM | Cursor/page boundary correctness; stable ordering; no “last item skipped” during page transition; preserve scroll anchor. |

### Differentiators (Competitive Advantage)

Features that set the product apart. Not required, but valuable.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Per-user series auto-download scheduling (hourly/daily/weekly) | Turns watchlist into a “set-and-forget” pipeline; reduces manual checking | HIGH | Must be per-user + timezone-aware; should be easy to explain (“check for new episodes at …”); needs guardrails (limits, dedupe, backoff). |
| New-episode detection via Xtream episode IDs | Deterministic, avoids fuzzy metadata matching | MEDIUM | Compare prior known episode IDs vs latest sync; define behavior for missing/renumbered IDs; ensure no redownload loop. |
| Smart episode download rules | Matches “offline-first” streaming patterns (download next/unplayed; limit count) | MEDIUM | Emby exposes options like “only unplayed”, “auto download new episodes”, and “limit number to download”; bring similar semantics to user automation. |
| External-member safe sharing (signed direct links + auditing) | Enables controlled “external access” without letting outsiders burn CPU/storage | MEDIUM | Signed links, expiry, per-user rate limits/quotas; per-user audit trail (what was accessed, when). |
| Self-healing downloads (auto-retry + integrity checks) | Fewer support incidents; “it just works” | HIGH | Automatic retry policy; detect stuck jobs; optional checksum/size verification; “repair” action that reattaches aria2/gid or restarts cleanly. |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem good but create problems.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| “Everyone can see/manage everyone’s downloads” | Convenience for small groups | Privacy leak; accidental deletion; social friction; breaks external access model | Owner-scoped by default; Admin-only global view; optional explicit sharing per download. |
| “Let External users schedule server-side downloads” | Users want automation too | External abuse risk (bandwidth/storage), hardest to police; turns app into a public download service | External: direct-link only; if needed later, add strict quotas + approval workflow. |
| “Auto-download entire series/library” | Offline hoarding | Storage explosion; queue starvation; high failure rate; violates many products’ intended design (Plex explicitly says Downloads isn’t intended for entire libraries) | Constrain automation: per-user limits, only new/unplayed, max N episodes/GB, per-series caps. |
| “Complex role hierarchy” (many roles/permissions) | Fine-grained control | Configuration fatigue; RBAC bugs; hard to test | Two roles (Admin/Member) + one policy flag (Internal/External) + a small number of capability toggles. |
| “Infinite scroll without stable ordering/cursors” | Quick implementation | Causes skipped/duplicated items as data changes; hard to reproduce bugs | Cursor-based pagination with stable sort key; snapshot semantics per query if possible. |

## Feature Dependencies

```
[RBAC: Admin/Member]
    └──requires──> [User model + authn]

[Internal vs External policy]
    └──requires──> [RBAC: Admin/Member]

[User-scoped downloads]
    ├──requires──> [RBAC: Admin/Member]
    └──requires──> [Download ownership model (user_id) + authorization]

[Download reliability hardening]
    └──requires──> [Persistent download state + aria2 job reconciliation]

[Categories UX]
    └──requires──> [Category sync + item↔category mapping]

[Per-user auto-episode scheduling]
    ├──requires──> [Watchlist per user]
    ├──requires──> [Job/queue + scheduler + timezone]
    ├──requires──> [New-episode detection]
    └──requires──> [Download operations allowed (Internal only)]

[Mobile infinite scroll correctness]
    └──requires──> [Stable pagination contract (cursor/page) + deterministic ordering]
```

### Dependency Notes

- **Internal/External policy requires RBAC:** policy only matters once authorization gates exist across routes/actions.
- **User-scoped downloads requires ownership:** without `download.user_id` (or equivalent), you can’t enforce visibility or quota.
- **Scheduling requires a stable “new episode” signal:** Xtream episode IDs are a good primary key; otherwise automation will double-download.
- **Mobile infinite scroll correctness requires a stable paging contract:** fixing the UI without fixing the paging invariant tends to regress.

## MVP Definition

### Launch With (v1) — “Production hardening + multi-user”

- [ ] Categories (VOD + Series) + sidebar filtering — required for discovery at scale
- [ ] RBAC (Admin vs Member) + Internal/External policy enforcement — required for multi-user safety
- [ ] User-scoped downloads + authorization — required for privacy/correctness
- [ ] Download reliability fixes (progress/abort/resume) + lifecycle tests — required to trust downloads
- [ ] Mobile infinite-scroll boundary fix — required to trust browsing on mobile

### Add After Validation (v1.x)

- [ ] Per-user auto-episode scheduling (hourly/daily/weekly) — add once RBAC + downloads are stable
- [ ] New-episode auto-download rules (only unplayed, max N episodes, etc.) — prevent runaway automation
- [ ] External-user auditing/quotas/rate limits — add if external access is used heavily

### Future Consideration (v2+)

- [ ] “Offline library management” UX (bulk operations, smart prefetch) — only if usage proves it’s needed
- [ ] Advanced download integrity verification (hash catalogs, repair workflows) — only if corruption becomes a recurring issue

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Categories (VOD + Series) + sidebar filtering | HIGH | MEDIUM | P1 |
| RBAC (Admin/Member) + Internal/External policy | HIGH | MEDIUM | P1 |
| User-scoped downloads | HIGH | HIGH | P1 |
| Download reliability hardening + tests | HIGH | HIGH | P1 |
| Mobile infinite-scroll correctness | HIGH | MEDIUM | P1 |
| Per-user auto-episode scheduling | MEDIUM-HIGH | HIGH | P2 |
| Episode auto-download rules (limits/unplayed) | MEDIUM | MEDIUM | P2 |
| External-user auditing/quotas | MEDIUM | MEDIUM | P2 |

## Competitor Feature Analysis

| Feature | Plex / Emby (media servers) | Seerr / Servarr ecosystem | Our Approach |
|---------|------------------------------|---------------------------|--------------|
| Offline downloads / queue | First-class offline downloads with queue/error UI; permissions exist; constraints documented (Plex Pass; “Allow Downloads”) | Not a downloader; orchestrates requests into Sonarr/Radarr | Keep aria2 engine; implement first-class queue UX + reliability + ownership. |
| Per-user permissions | Strong multi-user story; admin vs user; per-user rights; sharing restrictions | Granular permission system + account integration with media servers | Minimal RBAC (Admin/Member) + Internal/External policy; lock admin surfaces; keep policy understandable. |
| Auto-download new episodes | Emby exposes “auto download new episodes” and limits within download options | Automation is the core value (monitoring + grabbing new episodes) | Per-user watchlist schedules + Xtream ID-based new-episode detection + strict caps/dedupe. |
| Mobile browsing UX | Mature apps generally avoid list glitches; category/section navigation is expected | Web UIs focus on mobile-friendly approvals | Fix infinite-scroll correctness; keep fast category switching; preserve scroll position; avoid skipped items. |

## Sources

- Plex Support — Downloads Overview (last modified 2025-01-09): https://support.plex.tv/articles/downloads-overview/
- Plex Support — Downloads for iOS/Android (last modified 2025-04-03): https://support.plex.tv/articles/download-ios-android/
- Plex Support — Downloads FAQ (last modified 2025-06-16): https://support.plex.tv/articles/downloads-sync-faq/
- Emby Docs — Offline Access: https://emby.media/support/articles/Offline-Access.html
- Emby Docs — Download Options (includes auto-download new episodes + limits): https://emby.media/support/articles/Sync.html
- Jellyfin Docs — Users (admin flag + per-user controls): https://jellyfin.org/docs/general/server/users/
- Seerr Docs — Introduction (integrations + granular permission system): https://docs.seerr.dev/

---
*Feature research for: LionzHD Streaming Platform Enhancements*
*Researched: 2026-02-25*
