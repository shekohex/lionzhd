# Phase 7: Auto Episodes (Schedules + Dedupe) - Research

**Researched:** 2026-02-27
**Domain:** Laravel 12 scheduling + queued jobs + Xtream series episode diffing + dedupe
**Confidence:** HIGH

## Summary

Phase 7 fits cleanly into the existing Laravel 12 + Inertia stack by adding **per-user/per-series monitoring config** persisted in DB, a **minute-level dispatcher scheduled in `routes/console.php`**, and **per-monitor scan jobs** that fetch Xtream series info (already implemented + cached) and diff episode IDs against a persisted “known episodes” set.

To plan this phase well, treat it as three coupled subsystems:

1) **Config + schedule math (user timezone):** store schedule definition and a computed `next_run_at` (UTC) per monitored series. Compute next run from *now in user TZ* (hourly on-the-hour, daily at preset times, weekly day+time w/ multiple days). Track `last_successful_check_at` (UTC) and use it as catch-up window start.

2) **Execution pipeline:** schedule a single **dispatcher job every minute** that finds due monitors and dispatches **scan jobs**. Each scan job is **idempotent** and uses **atomic locks (DB cache locks)** to avoid duplicate queue entries and to serialize per-monitor execution.

3) **Dedupe + activity:** persist known episode IDs (per user+series) and maintain a per-run activity log (queued / duplicate / deferred / error) so UI can show outcomes, last/next run, and duplicates.

**Primary recommendation:** Implement monitoring as a DB-backed “monitor config + next_run_at” system with a single scheduled dispatcher (`Schedule::job(...)->everyMinute()`), per-monitor scan jobs guarded by `Cache::lock`, and a dedicated `auto_episode_episode_states` table (unique per user+series+episode_id) to satisfy diffing + deferral.

## Standard Stack

### Core
| Library / Feature | Version (repo) | Purpose | Why Standard in this repo |
|---|---:|---|---|
| Laravel Framework | ^12.0 | Scheduling, queues, Eloquent, cache locks | App already uses `routes/console.php` schedules and DB queue/cache |
| Laravel Scheduler | (Laravel 12) | Run dispatcher every minute | Already used for `MonitorDownloads` + `RefreshMediaContents` |
| Laravel Queue (database) | default `database` | Run scan jobs async | Already used (`queue:listen` in `composer dev`) |
| Laravel Cache (database + cache_locks) | default `database` | Atomic locks for dedupe + overlap | `cache_locks` migration exists; works without Redis |
| Inertia.js (Laravel + React) | inertia-laravel ^2.0 | UI pages + actions | Existing pages for series/show, watchlist, settings/schedules |
| saloonphp/saloon | ^3.11 | Xtream API connector/requests | Existing `XtreamCodesConnector`, `GetSeriesInfoRequest` |
| spatie/laravel-data | ^4.14 | Request/response DTOs | Existing pattern for page props + request payloads |

### Supporting
| Library / Feature | Version (repo) | Purpose | When to Use |
|---|---:|---|---|
| `ShouldBeUnique` jobs | (Laravel 12) | prevent duplicate dispatcher enqueues | Use for dispatcher; per-monitor jobs use locks / middleware |
| `Illuminate\Queue\Middleware\WithoutOverlapping` | (Laravel 12) | prevent overlap keyed by monitor/user/series | Use to serialize per-monitor scan jobs if preferred over `Cache::lock` in handle |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|---|---|---|
| DB cache locks (`Cache::lock`) | Redis locks | Not available by default; repo uses DB cache + sqlite in dev |
| Storing `next_run_at` | Recompute on read | Harder to query “due” monitors; more schedule drift edge cases |

**Docs:**
- Laravel 12 scheduling is typically defined in `routes/console.php` and supports scheduling queued jobs: https://laravel.com/docs/12.x/scheduling
- Laravel 12 atomic locks via `Cache::lock` support the `database` store: https://laravel.com/docs/12.x/cache#atomic-locks
- Laravel 12 queues include unique jobs + overlap middleware: https://laravel.com/docs/12.x/queues

## Architecture Patterns

### Relevant Existing Code Patterns (copy/paste mental model)

**Xtream series episodes fetch + caching**
- `app/Http/Integrations/LionzTv/Requests/GetSeriesInfoRequest.php` caches series info for 10 minutes.
- `app/Http/Integrations/LionzTv/Responses/SeriesInformation.php` exposes `seasonsWithEpisodes`.

**Manual episode download queueing**
- `app/Http/Controllers/Series/SeriesDownloadController.php`:
  - fetch series info, select episode, build URL (`CreateXtreamcodesDownloadUrl`), add to aria2 (`DownloadMedia`), persist `MediaDownloadRef::fromSeriesAndEpisode(...)`.
  - dedupe-ish behavior uses `GetActiveDownloads::run(...)`.

**Job scheduling pattern**
- `routes/console.php` uses `Schedule::job(...)->everyMinute()->withoutOverlapping()->sentryMonitor();`.
- `app/Jobs/MonitorDownloads.php` implements `ShouldBeUnique` to avoid overlaps.

### Recommended Project Structure (Phase 7 additions)
```
app/
├── Actions/
│   └── AutoEpisodes/
│       ├── ComputeNextRunAt.php
│       ├── QueueEpisodeDownload.php
│       └── ScanSeriesForNewEpisodes.php
├── Data/
│   └── AutoEpisodes/
│       ├── MonitorConfigData.php
│       ├── MonitorScheduleData.php
│       └── RunNowRequestData.php
├── Http/Controllers/
│   └── AutoEpisodes/
│       ├── MonitoringPageController.php
│       ├── SeriesMonitoringController.php
│       └── SeriesMonitoringRunNowController.php
├── Jobs/
│   └── AutoEpisodes/
│       ├── DispatchDueMonitors.php
│       └── RunMonitorScan.php
└── Models/
    └── AutoEpisodes/
        ├── SeriesMonitor.php
        ├── SeriesMonitorRun.php
        ├── SeriesMonitorEpisode.php
        └── SeriesMonitorEvent.php
resources/js/
├── pages/
│   ├── series/show.tsx              # add monitoring entry point
│   ├── watchlist.tsx                # optional: management entry point
│   └── settings/schedules.tsx        # likely becomes monitoring management page
└── components/
    └── (new) schedule editor modal/drawer
```

### Pattern 1: “Minute Dispatcher + Per-Monitor Scan Job”

**What:** A single scheduled job runs every minute, queries DB for monitors with `next_run_at <= now()`, then dispatches a scan job for each.

**When to use:** Always. This avoids attempting to map every user’s timezone schedule into Laravel’s global scheduler.

**How:**

1) `Schedule::job(DispatchDueMonitors::class)->everyMinute()->withoutOverlapping()` in `routes/console.php`.
2) `DispatchDueMonitors` selects due monitors and dispatches `RunMonitorScan($monitorId)`.
3) `RunMonitorScan` holds a lock (`Cache::lock("auto:episodes:monitor:{$monitorId}")`) so only one run occurs per monitor.

**Source (repo):** `routes/console.php` + `app/Jobs/MonitorDownloads.php` (unique/overlap patterns)

### Pattern 2: Store `next_run_at` (UTC) + schedule definition (user timezone)

**What:** Persist schedule config as structured fields, and keep a computed `next_run_at` for due querying and UI.

**When to use:** Always for per-user/per-series schedules.

**Key fields (plan-ready):**
- `timezone` (IANA, e.g. `Europe/Cairo`)
- `schedule_type`: `hourly|daily|weekly`
- `daily_time`: one of preset times (e.g. `"08:00"`)
- `weekly_days`: JSON array of ints (0=Sun..6=Sat)
- `weekly_time`: preset time (same set as daily)
- `next_run_at` (UTC, immutable timestamp)
- `last_successful_check_at` (UTC) for catch-up window start
- `last_attempt_at` / `last_attempt_status` for UI and debugging

### Pattern 3: Episode state table is the “known episode IDs” set

**What:** Maintain a table with a **unique key per (user_id, series_id, episode_id)**.

**When to use:** Always. This is the simplest way to satisfy **AUTO-05** (diff Xtream episode IDs vs known).

**Why it matters:** It also enables deferral safely (cap per run) without “forgetting” episodes.

## Don’t Hand-Roll

| Problem | Don’t Build | Use Instead | Why |
|---|---|---|---|
| Cross-worker dedupe/locking | ad-hoc DB “running” flags only | `Cache::lock(...)` (DB cache locks) | atomic + battle-tested; works with sqlite/db cache | 
| Scheduler per user timezone | thousands of Laravel scheduled entries | single minute dispatcher + `next_run_at` | simpler, queryable, and testable |
| Job overlap prevention | custom mutex tables | `withoutOverlapping()` for scheduled dispatcher; `WithoutOverlapping` middleware or `Cache::lock` for scan jobs | standard Laravel patterns |

## Common Pitfalls

### Pitfall 1: User timezone & DST edge cases
**What goes wrong:** “daily at 02:00” may run twice or not run on DST transitions; hourly “on-the-hour” can drift if computed from last-run instead of from now.
**Why it happens:** Local time shifts + naive arithmetic.
**How to avoid:** Compute `next_run_at` from *now in user TZ* each time and store UTC. For DST, accept that some scheduled times may not exist / may repeat; log run metadata clearly.
**Warning signs:** Users report missing/double runs around DST; `next_run_at` oscillates.

### Pitfall 2: Episode ID type mismatch
**What goes wrong:** Xtream episode `id` is a **string** (`Episode::$id`), while `media_download_refs.downloadable_id` is an **unsigned integer**.
**Why it happens:** SQLite is lenient; MySQL can truncate/cast unexpectedly.
**How to avoid:** Normalize episode IDs consistently (recommend: store episode_id as string in automation tables; cast to int only when writing to `media_download_refs` if required).
**Warning signs:** Known-episode dedupe fails, or episode rows collide.

### Pitfall 3: Cached Xtream DTO hides brand-new episodes briefly
**What goes wrong:** `GetSeriesInfoRequest` caches for 10 minutes; a scan may not see a newly added episode until cache expires.
**How to avoid:** Accept small delay, or explicitly bust cache namespace when scan wants fresh data (only if needed). Plan UI copy accordingly.

### Pitfall 4: Duplicate aria2 downloads due to race
**What goes wrong:** Two scan jobs queue the same episode and create two aria2 GIDs.
**How to avoid:** Use atomic locks per (user_id, series_id, episode_id) around the “check existing + queue” critical section.
**Warning signs:** Two `media_download_refs` rows for same episode/user created within seconds.

### Pitfall 5: External-member UX vs current route authorization
**What goes wrong:** Current `/settings/schedules` is behind `can:auto-download-schedules`, so external members get 403, but Phase 7 decision requires UI visible but disabled.
**How to avoid:** Allow GET page for all auth users; gate only POST/PATCH actions with `Gate::authorize('auto-download-schedules')`. Update tests accordingly.
**Warning signs:** `tests/Feature/AccessControl/SchedulesAccessTest.php` fails.

## Code Examples

### Scheduling a dispatcher job (repo pattern + Laravel docs)
```php
// Source: routes/console.php (existing pattern) + Laravel docs https://laravel.com/docs/12.x/scheduling

use Illuminate\Support\Facades\Schedule;

Schedule::job(\App\Jobs\AutoEpisodes\DispatchDueMonitors::class)
    ->name('auto-episodes-dispatch')
    ->description('Dispatch due series monitoring scans')
    ->everyMinute()
    ->withoutOverlapping();
```

### Atomic lock for race-safe episode queueing
```php
// Source: Laravel docs https://laravel.com/docs/12.x/cache#atomic-locks

use Illuminate\Support\Facades\Cache;

$lockKey = "auto:episodes:queue:user:{$userId}:series:{$seriesId}:episode:{$episodeId}";

Cache::lock($lockKey, 120)->block(5, function () {
    // 1) re-check existing download ref / episode state
    // 2) queue download
    // 3) persist state + activity
});
```

### “On-the-hour” next run calculation (plan-ready pseudocode)
```php
use Carbon\CarbonImmutable;

function nextRunHourly(CarbonImmutable $nowUtc, string $tz): CarbonImmutable {
    $localNow = $nowUtc->setTimezone($tz);
    return $localNow->startOfHour()->addHour()->setTimezone('UTC');
}

function nextRunDailyAt(CarbonImmutable $nowUtc, string $tz, string $hhmm): CarbonImmutable {
    $localNow = $nowUtc->setTimezone($tz);
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    $candidate = $localNow->setTime($h, $m)->startOfMinute();
    if ($candidate->lte($localNow)) {
        $candidate = $candidate->addDay();
    }
    return $candidate->setTimezone('UTC');
}

function nextRunWeekly(CarbonImmutable $nowUtc, string $tz, array $days0Sun, string $hhmm): CarbonImmutable {
    $localNow = $nowUtc->setTimezone($tz);
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    $days = array_fill_keys($days0Sun, true);

    for ($i = 0; $i < 8; $i++) {
        $date = $localNow->addDays($i);
        $dow = (int) $date->dayOfWeek; // 0=Sun..6=Sat
        if (!isset($days[$dow])) continue;
        $candidate = $date->setTime($h, $m)->startOfMinute();
        if ($candidate->gt($localNow)) {
            return $candidate->setTimezone('UTC');
        }
    }

    return $localNow->addWeek()->startOfHour()->setTimezone('UTC');
}
```

## Plan-Critical Repository Touchpoints (Key Files)

### Scheduling / jobs
- `routes/console.php` (add the Phase 7 dispatcher schedule)
- `app/Jobs/MonitorDownloads.php` (pattern for `ShouldBeUnique` jobs)

### Xtream series/episode fetching
- `app/Http/Integrations/LionzTv/Requests/GetSeriesInfoRequest.php`
- `app/Http/Integrations/LionzTv/Responses/SeriesInformation.php`
- `app/Http/Integrations/LionzTv/Responses/Episode.php`

### Download queueing + dedupe precedent
- `app/Http/Controllers/Series/SeriesDownloadController.php`
- `app/Actions/GetActiveDownloads.php`
- `app/Models/MediaDownloadRef.php`

### Watchlist
- `app/Models/Watchlist.php`, `database/migrations/2025_03_11_225950_create_watchlists_table.php`
- `app/Http/Controllers/WatchlistController.php`
- `app/Http/Controllers/Series/SeriesWatchlistController.php`

### Access control (External restriction gate)
- `app/Providers/AppServiceProvider.php` (`Gate::define('auto-download-schedules', ...)`)
- `routes/settings.php` (current `/settings/schedules` gating)
- `tests/Feature/AccessControl/SchedulesAccessTest.php` (must change if page becomes visible-but-disabled)

### Frontend pages/components likely impacted
- `resources/js/pages/series/show.tsx` (monitor toggle + run now entry point)
- `resources/js/pages/watchlist.tsx` (potential management surface)
- `resources/js/pages/settings/schedules.tsx` (currently “Coming in Phase 7.”)
- `resources/js/pages/downloads.tsx` + `resources/js/components/download-info.tsx` (download annotations)

## Data Model Recommendations (Plan-Ready)

### 1) `series_monitors` (per user + series config)
**Goal:** source of truth for whether a watchlisted series is monitored, how, and when it runs next.

**Recommended columns:**
- `id`
- `user_id` (FK users)
- `series_id` (FK series.series_id)
- `watchlist_id` (FK watchlists.id, optional but recommended for cascade-delete on watchlist removal)
- `enabled` boolean
- `timezone` string (IANA)
- `schedule_type` enum/string: `hourly|daily|weekly`
- `schedule_daily_time` string nullable (preset HH:MM)
- `schedule_weekly_days` json nullable (ints 0..6)
- `schedule_weekly_time` string nullable (preset HH:MM)
- `monitored_seasons` json (ints)
- `per_run_cap` int (guardrail)
- `next_run_at` timestamp (UTC, indexed)
- `last_attempt_at` timestamp nullable
- `last_attempt_status` string nullable
- `last_successful_check_at` timestamp nullable
- `run_now_available_at` timestamp nullable (cooldown)
- timestamps

**Indexes/constraints:**
- unique (`user_id`, `series_id`)
- index (`enabled`, `next_run_at`)

### 2) `series_monitor_episodes` (known episode IDs + state)
**Goal:** satisfy AUTO-05 and support deferral + retry rules.

**Recommended columns:**
- `id`
- `user_id`
- `series_id`
- `episode_id` string (Xtream episode id)
- `season` int
- `episode_num` int
- `state` enum/string: `known|pending|queued|downloaded|failed|canceled|skipped`
- `first_seen_at`, `last_seen_at`
- `last_queued_at` nullable
- `last_download_ref_id` nullable (FK media_download_refs.id)
- timestamps

**Constraints:**
- unique (`user_id`, `series_id`, `episode_id`)  ← this is the “known IDs set”

### 3) `series_monitor_runs` + `series_monitor_events` (activity log)
**Goal:** UI needs last/next run, duplicates, deferred, errors.

**Runs:** one row per scan (scheduled or manual/run-now/backfill)
- `monitor_id`, `trigger` (`scheduled|manual|backfill`), `window_start_at`, `window_end_at`, `status`, counts, error text.

**Events:** per-episode outcomes
- `run_id`, `episode_id`, `type` (`queued|duplicate|deferred|skipped|error`), `reason`, metadata json.

### 4) Global pause / “since last seen” badges
**Plan-ready choices (fits current schema):**
- Add `users.auto_downloads_paused_at` nullable timestamp.
- Add `users.auto_downloads_last_seen_at` nullable timestamp (or a separate table if preferred).

## Job Architecture + Concurrency Strategy

### Dispatcher job
**Responsibilities:**
- early-exit if global system pause exists (optional)
- query due monitors in small batches (e.g. 100)
- for each: dispatch scan job and optimistically move `next_run_at` forward *only after scan completes* (recommended), or move it forward immediately and rely on `last_successful_check_at` for catch-up.

**Concurrency:**
- `DispatchDueMonitors` should be `ShouldBeUnique` + scheduled `withoutOverlapping()`.

### Per-monitor scan job
**Responsibilities:**
- lock per monitor (`Cache::lock` or `WithoutOverlapping($monitorId)->shared()`)
- determine `window_start_at = last_successful_check_at ?? baseline_start` and `window_end_at = now()`
- fetch Xtream series info (`GetSeriesInfoRequest`)
- filter by monitored seasons
- compute unknown episodes by diffing against `series_monitor_episodes`
- order oldest-first (season asc, episode_num asc)
- apply cap N:
  - queue first N (or mark duplicate if already queued/downloaded)
  - mark remainder as `pending` and log `deferred`
- on success: set `last_successful_check_at = window_end_at`, compute/store new `next_run_at` (UTC)
- on failure: store failure status + compute/store `next_run_at` (still advance schedule), keep `last_successful_check_at` unchanged so next run catch-up scans the gap.

### Race-safe dedupe for queueing
Use a second, tighter lock per episode key (user+series+episode_id) around:
1) check existing `media_download_refs` for same user+series+downloadable_id, and/or existing episode state
2) queue aria2 download + create `media_download_refs`
3) persist episode state + event

This prevents two jobs from queueing the same episode concurrently.

## Test Strategy (where + what)

### Where tests live (repo)
- Feature tests: `tests/Feature/...`
- Unit tests: `tests/Unit/...`
- Jobs tests exist (`tests/Feature/Jobs/...`)

### Must-have automated tests for Phase 7
1) **Schedule math (Unit):**
   - hourly on-the-hour next run
   - daily preset time next run (before/after time)
   - weekly multi-day next run
   - catch-up window uses `last_successful_check_at`
2) **Dedupe (Feature/Unit):**
   - unknown episode queued once; repeated scans do not create duplicates
   - when existing `MediaDownloadRef` exists for same episode/user, scan logs duplicate and does not enqueue
   - cap per run defers remainder (state stays pending; next run queues them)
3) **Access control (Feature):**
   - external can view monitoring UI but cannot POST/PATCH config or run-now (per locked decision)
   - ensure current `SchedulesAccessTest` is updated to match the new behavior

## State of the Art (in this repo)

| Area | Current state | Phase 7 implication |
|---|---|---|
| Scheduling | `routes/console.php` schedules jobs every minute/daily with `withoutOverlapping()` | Add a minute dispatcher job; don’t add per-user schedules |
| Locks | DB cache locks available (`cache_locks` migration) | Use `Cache::lock` for per-monitor + per-episode dedupe |
| Episode downloads | Manual episode download creates `MediaDownloadRef` and relies on `GetActiveDownloads` | Auto-queue should reuse same queueing primitives; avoid duplicate GIDs |
| External restrictions | gate `auto-download-schedules` exists; `/settings/schedules` currently forbidden | Must flip to “visible but disabled”, gate mutations only |

## Open Questions

1) **User timezone source of truth**
   - What we know: schedules must run in user timezone.
   - What’s unclear: user timezone isn’t stored today.
   - Recommendation: add `users.timezone` (IANA) and default from browser on first schedule interaction.

2) **Preset time list + per-run cap + run-now cooldown**
   - What we know: these are OpenCode discretion.
   - Recommendation: pick sensible defaults and codify as config constants so they’re easy to change.

3) **Definition of “recent only” backfill**
   - What we know: baseline should not queue full history.
   - What’s unclear: whether “recent” means “last N episodes” (season/order) or “added within last X days” (episode.added).
   - Recommendation: implement “last N episodes by season/episode order” first (predictable without parsing dates).

## Sources

### Primary (HIGH confidence)
- Repo source code (paths cited throughout)
- Laravel 12 Task Scheduling: https://laravel.com/docs/12.x/scheduling
- Laravel 12 Cache Atomic Locks: https://laravel.com/docs/12.x/cache#atomic-locks
- Laravel 12 Queues (unique jobs, WithoutOverlapping middleware): https://laravel.com/docs/12.x/queues

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH (pinned in `composer.json`, confirmed in repo)
- Architecture: HIGH (matches existing scheduling + queue patterns in repo + official docs)
- Pitfalls: MEDIUM/HIGH (type mismatch + caching + DST are verified risks; exact UX details depend on planning choices)

**Research date:** 2026-02-27
**Valid until:** 2026-03-27
