# Architecture Research

**Domain:** Xtream (VOD + Series) streaming companion app (Laravel 12 monolith + Inertia React + aria2)
**Researched:** 2026-02-25
**Confidence:** MEDIUM

## Standard Architecture

### System Overview

```text
┌──────────────────────────────────────────────────────────────────────────┐
│                               Client (Web)                               │
├──────────────────────────────────────────────────────────────────────────┤
│  Inertia React pages                                                     │
│  - Movies / Series / Downloads / Watchlist / Settings                     │
│  - Mobile infinite scroll + desktop pagination                            │
└───────────────────────────────┬──────────────────────────────────────────┘
                                │ HTTP (web routes) + Inertia props
┌───────────────────────────────┴──────────────────────────────────────────┐
│                            Laravel Monolith                               │
├──────────────────────────────────────────────────────────────────────────┤
│  HTTP Layer                                                              │
│  - Controllers (VodStream/*, Series/*, Downloads, Settings/*)             │
│  - Request validation (Data DTOs)                                         │
│  - Auth (sanctum/session) + RBAC (gates/policies/middleware)              │
│                                                                          │
│  Application Layer                                                       │
│  - Actions (command/query style)                                          │
│  - Jobs (queue) + Scheduler (routes/console.php)                          │
│                                                                          │
│  Integrations                                                            │
│  - Xtream Codes API via Saloon connector                                  │
│  - aria2 JSON-RPC via Saloon connector                                    │
│                                                                          │
│  Persistence                                                             │
│  - Eloquent models + migrations                                           │
│  - Scout/Meilisearch (search indexing)                                    │
│  - Storage (downloaded files on disk / mounted volume)                    │
└───────────────┬───────────────────────────┬──────────────────────────────┘
                │                           │
                │ JSON-RPC                  │ HTTP
┌───────────────┴───────────────┐   ┌───────┴──────────────────────────────┐
│            aria2d              │   │               Xtream server          │
│  - download queue + state      │   │  - VOD/Series catalog + episode URLs │
└───────────────────────────────┘   └──────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| Catalog (VOD/Series) | Local cache of provider catalog; search; category filtering | `VodStream`, `Series`, Scout indexes; `SyncMedia` job; optional category tables |
| Categories | Human-readable category names + filtering faceting | `Category` model(s) keyed by `category_id` + `type`; sync via Xtream endpoints |
| Access/RBAC | Who can manage settings/sync/automation vs regular browsing | Laravel gates/policies + a simple `users.role` (enum string) or equivalent |
| Downloads | User-scoped download requests + status display + file path ownership | `MediaDownloadRef` (+ `user_id`) and download orchestration actions |
| Auto-download (Series) | Per-user subscriptions that enqueue episode downloads | Subscription model + scheduled job that fans out queued work |
| aria2 lifecycle | Resilient orchestration + reconciliation when aria2/Xtream is flaky | “DB-first” download records, reconciliation job, backoff + idempotency |
| Frontend pagination | Desktop pagination + mobile infinite scroll with partial reloads | Consistent paginator props shape, `withQueryString`, stable per-page rules |

## Recommended Project Structure

This project already follows “controllers + actions + jobs + integrations” boundaries; extend that pattern rather than introducing a new module system.

```text
app/
├── Actions/
│   ├── (existing) DownloadMedia, BatchDownloadMedia, CreateDownloadDir, ...
│   ├── Categories/
│   │   ├── SyncVodCategories.php
│   │   └── SyncSeriesCategories.php
│   ├── Downloads/
│   │   ├── CreateUserScopedDownloadOptions.php
│   │   ├── EnsureDownloadRecord.php
│   │   └── ReconcileAria2Downloads.php
│   └── AutoDownloads/
│       ├── EvaluateSeriesSubscriptions.php
│       └── EnqueueMissingEpisodes.php
├── Http/
│   ├── Controllers/
│   │   ├── VodStream/ (existing)
│   │   ├── Series/ (existing)
│   │   ├── MediaDownloadsController.php (existing)
│   │   └── Settings/ (existing)
│   └── Integrations/
│       ├── LionzTv/ (Xtream)
│       │   ├── Requests/
│       │   │   ├── GetVodCategoriesRequest.php
│       │   │   └── GetSeriesCategoriesRequest.php
│       │   └── Responses/
│       └── Aria2/ (existing)
├── Jobs/
│   ├── RefreshMediaContents.php (existing)
│   ├── ReconcileDownloads.php
│   └── RunAutoEpisodeDownloads.php
├── Models/
│   ├── VodStream.php (existing)
│   ├── Series.php (existing)
│   ├── MediaDownloadRef.php (existing; becomes user-scoped)
│   ├── Category.php (or VodCategory + SeriesCategory)
│   └── SeriesAutoDownloadSubscription.php
├── Policies/
│   ├── (existing) Aria2ConfigPolicy, XtreamCodeConfigPolicy
│   └── (new) DownloadPolicy / SettingsPolicy (or gates)
└── Enums/
    └── UserRole.php

resources/js/
├── pages/
│   ├── movies/index.tsx (existing)
│   ├── series/index.tsx (existing)
│   ├── downloads.tsx (existing)
│   └── watchlist.tsx (existing)
└── hooks/
    └── use-infinite-scroll.ts (existing)
```

### Structure Rationale

- **Actions/** stays the application boundary: controllers do orchestration only; actions own business rules (idempotency, dedupe, per-user scoping, path rules).
- **Jobs/** is for anything that is slow/flaky (Xtream calls, aria2 RPC, reconciliation, auto-download evaluation).
- **Integrations/** remains the only place that “knows” external APIs.
- **Models/** remain thin; keep computed business logic in actions to avoid “fat model” coupling across features.

## Architectural Patterns

### Pattern 1: “DB-first” download orchestration (user-scoped + idempotent)

**What:** create/ensure the download record first, then attempt aria2 RPC; reconciliation completes the lifecycle.

**When to use:** any operation that touches external systems (aria2, Xtream) and needs to be resilient + user-scoped.

**Trade-offs:** more bookkeeping, but prevents duplicate downloads, makes retries safe, and supports “aria2 unavailable” states.

**Example (conceptual):**

```php
// Controller
EnsureDownloadRecord::run($user, $media, $episode);
DispatchDownloadToAria2::dispatch($downloadId);
```

Key invariants:

- Unique download intent: `(user_id, media_type, media_id, downloadable_id)` unique.
- `gid` is just an external identifier; treat it as nullable until RPC succeeds.
- Status shown in UI is derived from (DB snapshot + live aria2 tellStatus when available).

### Pattern 2: Scheduled “fan-out” automation

**What:** scheduler runs a lightweight coordinator job; it queries subscriptions and dispatches per-subscription/per-series jobs.

**When to use:** auto-episode downloads (and future automation like “download next unwatched”).

**Trade-offs:** more jobs, but avoids long-running scheduled tasks and plays well with `withoutOverlapping`/unique locks.

### Pattern 3: Authorization as a boundary (RBAC gates + policies)

**What:** treat RBAC as part of controller boundary; domain actions should assume a validated/authorized actor.

**When to use:** settings pages (Xtream/aria2 config), bulk sync, automation management.

**Trade-offs:** keeps business code simpler; requires consistent controller usage.

## Data Flow

### Request Flow (browse + filter + paginate)

```text
User (React) changes filter/page
    ↓
Inertia request (partial reload: only=['movies'|'series'])
    ↓
Controller builds query (category_id, sort, perPage)
    ↓
Eloquent paginate + withQueryString
    ↓
Inertia props: { data, links, meta }
    ↓
Desktop: pagination UI / Mobile: infinite scroll merges pages
```

Pagination rule of thumb for this codebase:

- Always return a paginator (not a raw collection) from controllers for index pages.
- Always use `withQueryString()` when filters/sort/perPage exist.
- For mobile infinite scroll, keep the server pagination stable (same per-page, stable ordering) so page merging is deterministic.

### Request Flow (user-scoped download)

```text
User clicks “Download”
    ↓
Controller validates (media + episode) + authorizes
    ↓
Action: Build download URL (Xtream) + compute aria2 options (dir/out per user)
    ↓
Action: Ensure download record (user_id + media ref)
    ↓
Action/Job: Call aria2.addUri
    ↓
Persist gid + initial state
    ↓
UI lists downloads; client polls; server merges DB refs + aria2 tellStatus
```

### Scheduled Flow (auto episode downloads)

```text
Scheduler (every N minutes)
    ↓
RunAutoEpisodeDownloads (coordinator)
    ↓
For each enabled subscription (user_id + series_id)
    ↓
Fetch series info (episodes) from Xtream
    ↓
Determine “missing” episodes (not downloaded + not active)
    ↓
Enqueue per-episode download jobs (idempotent)
```

### Operational Flow (aria2 lifecycle hardening)

```text
Scheduler (every 1-5 minutes)
    ↓
ReconcileDownloads
    ↓
Find recent/active downloads in DB
    ↓
Batch tellStatus(gid[])
    ↓
Update snapshot fields + last_seen_at
    ↓
Detect anomalies (missing gid, errored, completed-but-missing-file)
    ↓
Take action (mark failed, retry, cleanup result, notify)
```

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-1k users | Monolith + queue + scheduler is sufficient; focus on idempotency and file ownership boundaries |
| 1k-100k users | Reduce Xtream API pressure (cache DTOs, avoid per-item info calls), batch aria2 status checks, tune polling and pagination |
| 100k+ users | Consider splitting download orchestration into a worker service, but only after DB model + idempotency are stable |

### Scaling Priorities

1. **First bottleneck:** Xtream API calls for series info/episodes (avoid N+1; cache per-series info for short TTL; fan-out jobs with rate limits).
2. **Second bottleneck:** aria2 RPC polling (batch `tellStatus`, increase interval, reconcile server-side instead of heavy client polling).

## Anti-Patterns

### Anti-Pattern 1: Treating aria2 as the source of truth

**What people do:** only store `gid` and rely on live RPC for everything.

**Why it’s wrong:** aria2 can be restarted, lose in-memory state, or be temporarily unreachable; UI becomes inconsistent and retries become unsafe.

**Do this instead:** persist “download intent” and minimal state in DB; treat RPC as best-effort and reconcile periodically.

### Anti-Pattern 2: Global (non-user-scoped) downloads in a multi-user app

**What people do:** keep downloads keyed only by media IDs and show them to everyone.

**Why it’s wrong:** violates access boundaries, breaks per-user automation, and makes cleanup ambiguous.

**Do this instead:** add `user_id` ownership on download refs + enforce it in queries, controllers, and policies.

### Anti-Pattern 3: Long-running scheduled tasks that do everything in-process

**What people do:** scheduler fetches episodes for all subscriptions and also calls aria2, in one run.

**Why it’s wrong:** overlaps, timeouts, partial failures, and hard-to-debug state.

**Do this instead:** coordinator schedules per-series/per-user jobs with unique locks + backoff.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Xtream Codes API | Saloon connector + request/response DTOs | Add category endpoints; cache DTOs; avoid per-item info calls in loops |
| aria2 JSON-RPC | Saloon connector + batch requests | Prefer batch calls; handle “RPC down” as a normal state |
| Meilisearch (Scout) | `Searchable` models | Reindex after sync; don’t block user requests on indexing |
| Sentry | Scheduler monitors + error reporting | Monitor scheduled jobs (sync, reconcile, auto-download) |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Catalog ↔ Categories | DB relationship (`category_id` → `categories`) | Keep category sync independent of media sync; UI filters depend on it |
| Catalog ↔ Downloads | DB refs + actions | Downloads should not call controllers; actions own orchestration |
| Downloads ↔ aria2 | Integration connector | Wrap in actions/jobs to centralize retry/backoff/hardening |
| Auto-download ↔ Downloads | Action calls | Auto-download produces “download intents”; download layer ensures idempotency |
| Settings/RBAC ↔ Everything | middleware/gates/policies | Protect settings/sync/automation routes; don’t sprinkle checks in actions |

## Suggested Build Order (Dependencies)

1. **RBAC foundation (minimal roles) + ownership boundaries**
   - Add a role concept (`admin` vs `user`) and gates for settings/sync/automation.
   - Add `user_id` ownership to download refs and enforce query scoping.

2. **Mobile pagination stability (quick wins)**
   - Standardize paginator usage + `withQueryString()` on index pages.
   - Ensure mobile infinite scroll merges stable-ordered pages.

3. **Categories (sync + filtering)**
   - Add category models/tables + Xtream category requests.
   - Add category filters to movies/series index controllers + UI.

4. **aria2 lifecycle hardening (reconcile + anomaly handling)**
   - Add reconciliation job + snapshot fields needed for robust UI.
   - Move “what status means” logic to server-side reconciliation (UI becomes thinner).

5. **Scheduled auto-episode downloads**
   - Add per-user series subscription model + UI toggle.
   - Implement coordinator + fan-out jobs; dedupe via download ownership constraints.

## Sources

- Laravel Scheduling: https://laravel.com/docs/12.x/scheduling
- Laravel Authorization (Gates/Policies): https://laravel.com/docs/12.x/authorization
- Inertia.js (Laravel adapter): https://inertiajs.com/server-side-setup
- aria2 RPC interface (manual): https://aria2.github.io/manual/en/html/aria2c.html#rpc-interface

---
*Architecture research for: Xtream VOD/Series companion app*
*Researched: 2026-02-25*
