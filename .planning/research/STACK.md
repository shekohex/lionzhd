# Stack Research

**Domain:** Xtream-based VOD/series streaming companion app (Laravel 12 + Inertia React brownfield)
**Researched:** 2026-02-25
**Confidence:** MEDIUM

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Redis OSS | 7.4.8 | Queue + scheduler coordination + Reverb scaling | Standard Laravel queue backend; required for Horizon and commonly used by Reverb for pub/sub. **Confidence: MEDIUM** (run latest patch in your chosen Redis major/minor; 7.2.x is also common). |
| Laravel Horizon | v5.45.0 | Queue observability + worker config (Redis queues) | Best-supported queue dashboard in Laravel ecosystem; essential when adding background ingest + auto-download + aria2 orchestration. **Confidence: HIGH** |
| Laravel Reverb | v1.8.0 | First-party WebSocket server (Pusher protocol) | Most direct/maintained route to realtime aria2 progress + abort/resume UX without third-party WS servers. **Confidence: HIGH** |
| aria2c | 1.37.0 | Download engine + pause/resume + segmented HTTP(S) | Mature CLI/RPC download manager; supports resumable downloads and rich status via JSON-RPC. **Confidence: HIGH** |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| spatie/laravel-permission | ^6.24.1 | Roles/permissions backing tables + Gate integration | Implement admin/member roles and fine-grained abilities without rolling your own RBAC. **Confidence: HIGH** |
| spatie/laravel-query-builder | ^6.4.3 | Safe, allow-listed filtering/sorting/includes | Power VOD/series/category filtering UX (querystring-driven) without SQL-injection / “filter-any-column” footguns. **Confidence: HIGH** |
| spatie/laravel-data | ^4.19.1 | DTO/resource layer + validation + TS types (optional) | Normalize Xtream ingest payloads and Inertia props; reduce “array-shape drift”. **Confidence: MEDIUM** (optional; depends on existing patterns). |
| spatie/laravel-schedule-monitor | ^4.2.0 | Detect/diagnose missed/late scheduled tasks | Hardens watchlist auto-download scheduling (hourly/daily/weekly) and surfaces cron/scheduler failures. **Confidence: HIGH** |
| spatie/laravel-health | ^1.38.0 | Health checks + notifications | Add checks for Horizon running, Redis reachable, disk space for downloads, scheduler heartbeat. **Confidence: HIGH** |
| laravel/pulse | ^1.6.0 | Lightweight APM-style dashboards | Useful when fixing mobile infinite scroll performance / N+1 / slow queries in filtering screens. **Confidence: MEDIUM** (optional). |
| spatie/laravel-rate-limited-job-middleware | ^2.8.2 | Job middleware for rate limiting | Prevent API hammering during category/metadata ingest and avoid “thundering herd” on hourly scans. **Confidence: HIGH** |
| @inertiajs/react | 2.3.16 | Inertia React adapter | Already baseline; ensure new infinite-scroll/filter UX stays compatible with current adapter. **Confidence: HIGH** |
| laravel-echo | 2.3.0 | Client-side WS subscriptions (Pusher protocol) | Realtime aria2 progress updates (per-user channels) and download-control events. **Confidence: HIGH** |
| pusher-js | 8.4.0 | WS client used by Echo/Reverb | Required by Echo for Pusher-protocol connections (works with Reverb). **Confidence: HIGH** |
| @tanstack/react-query | 5.90.21 | Client cache + polling + infinite queries | Infinite scroll and “new episodes” UX where partial page reloads are brittle; supports cursor-based pagination + background refresh. **Confidence: HIGH** |
| @tanstack/react-virtual | 3.13.19 | Virtualized lists | Fix mobile infinite scroll boundary bugs and reduce DOM size for large VOD/series lists. **Confidence: HIGH** |
| react-intersection-observer | 10.0.3 | Stable intersection triggers | Reliable “load next page” triggers across mobile browsers when not using full virtualization. **Confidence: HIGH** |
| zod | 4.3.6 | Runtime schema validation | Validate/sanitize filter query params client-side (esp. on deep-linked URLs) and keep UI state consistent. **Confidence: HIGH** |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Laravel Horizon UI | Queue visibility | Run in non-local envs that execute downloads/ingest; protects against silent worker death. |
| Laravel Schedule Monitor CLI/UI | Scheduler visibility | Run `schedule-monitor:sync` during deploy; name critical tasks and use grace times. |

## Installation

```bash
# Backend (Composer)
composer require \
  laravel/horizon \
  laravel/reverb \
  spatie/laravel-permission:^6.24.1 \
  spatie/laravel-query-builder:^6.4.3 \
  spatie/laravel-data:^4.19.1 \
  spatie/laravel-schedule-monitor:^4.2.0 \
  spatie/laravel-health:^1.38.0 \
  laravel/pulse:^1.6.0 \
  spatie/laravel-rate-limited-job-middleware:^2.8.2

# Frontend (npm)
npm install \
  laravel-echo@^2.3.0 \
  pusher-js@^8.4.0 \
  @tanstack/react-query@^5.90.21 \
  @tanstack/react-virtual@^3.13.19 \
  react-intersection-observer@^10.0.3 \
  zod@^4.3.6
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| laravel/reverb | Pusher (hosted) | If you want managed infra + global edge WS; pay for it; simplest ops. |
| laravel/reverb | soketi | If you want Pusher-protocol WS but prefer a dedicated Go/Node server; good for container stacks. |
| spatie/laravel-permission | josephsilber/bouncer | If you prefer ability-based models over explicit permissions tables; good DX, less “RBAC admin UI” oriented. |
| spatie/laravel-query-builder | Hand-rolled filters | Only if the filtering surface is tiny and you can keep strict allow-lists manually. |
| @tanstack/react-query | swr | If you want a smaller API surface and you don’t need complex cache invalidation / infinite query primitives. |
| @tanstack/react-virtual | react-virtuoso | If you prefer higher-level list primitives (less DIY) and accept a heavier dependency. |
| spatie/laravel-data | Laravel API Resources + Form Requests | If you prefer core-only Laravel (no DTO package) and can tolerate duplicated shape/validation definitions. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| @inertiajs/inertia | Deprecated package on npm; wrong dependency line for modern Inertia (2.x uses @inertiajs/core). | @inertiajs/react (and its @inertiajs/core dependency). |
| daijie/aria2, viharm/php-aria2-rpc, scolib/aria2 | Stale clients (2017–2020, PHP 5/7 targets, weak maintenance); risky for reliability hardening work. | A small internal JSON-RPC client built on Laravel HTTP client **or** (experimental) manyou/aria2 (0.1.x-dev) if you accept pre-1.0 risk. |
| beyondcode/laravel-websockets | Extra moving parts vs first-party Reverb for new builds; maintenance/compat churn risk. | laravel/reverb (or hosted Pusher). |
| “Filter any request param into query” patterns | Easy to accidentally expose columns/relations; perf + security footguns. | spatie/laravel-query-builder with strict `allowedFilters/allowedIncludes/allowedSorts`. |

## Stack Patterns by Variant

**If you need realtime aria2 progress UI (recommended for abort/resume confidence):**
- Use `laravel/reverb` + `laravel-echo` + `pusher-js`
- Because per-user channel updates remove aggressive polling and reduce “stale progress” confusion

**If you can accept polling-only progress (simpler, less infra):**
- Skip Reverb; poll aria2 status via queued jobs + store progress snapshots
- Because scheduler/queues are already baseline and reduce WS operational concerns

**If you are using Redis heavily (Horizon + Reverb):**
- Prefer PHP `ext-redis` (phpredis) over Predis where possible
- Because it reduces CPU/memory overhead under sustained job throughput

**If you are on PHP < 8.4:**
- Use `spatie/laravel-permission:^6.24` (not 7.x)
- Because 7.x requires PHP ^8.4

**If you want a state machine for downloads (recommended for reliability hardening):**
- Use `spatie/laravel-model-states:2.12.1` (PHP ^7.4|^8.0; Illuminate ^10|^11|^12)
- Because it models download lifecycle transitions explicitly (queued → active → paused → complete/error) and reduces “impossible state” bugs

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| spatie/laravel-permission@7.2.3 | PHP ^8.4 + Laravel 12 | Use only after upgrading runtime to PHP 8.4. |
| spatie/laravel-permission@6.24.1 | PHP ^8.0 + Laravel 12 | Safe default for most Laravel 12 apps on PHP 8.2/8.3. |
| spatie/laravel-model-states@2.12.1 | PHP ^7.4\|^8.0 + Laravel 12 | Last known release line supporting Laravel 12 without PHP 8.4 requirement. |

## Sources

- https://packagist.org/packages/laravel/horizon — version v5.45.0 (verified)
- https://packagist.org/packages/laravel/reverb — version v1.8.0 (verified)
- https://packagist.org/packages/laravel/pulse — version v1.6.0 (verified)
- https://packagist.org/packages/spatie/laravel-permission — versions 6.24.1 / 7.2.3 (verified)
- https://repo.packagist.org/p2/spatie/laravel-permission.json — 6.24.1 PHP/Laravel requirements (verified)
- https://packagist.org/packages/spatie/laravel-query-builder — version 6.4.3 (verified)
- https://packagist.org/packages/spatie/laravel-data — version 4.19.1 (verified)
- https://packagist.org/packages/spatie/laravel-schedule-monitor — version 4.2.0 (verified)
- https://packagist.org/packages/spatie/laravel-health — version 1.38.0 (verified)
- https://packagist.org/packages/spatie/laravel-rate-limited-job-middleware — version 2.8.2 (verified)
- https://packagist.org/packages/spatie/laravel-model-states — latest version line + constraints (verified)
- https://repo.packagist.org/p2/spatie/laravel-model-states.json — 2.12.1 PHP/Laravel requirements (verified)
- https://github.com/aria2/aria2/releases — aria2c 1.37.0 (verified)
- https://github.com/redis/redis/releases — Redis 7.2.x/7.4.x line + current security releases (verified)
- https://registry.npmjs.org/laravel-echo/latest — laravel-echo 2.3.0 (verified)
- https://registry.npmjs.org/pusher-js/latest — pusher-js 8.4.0 (verified)
- https://registry.npmjs.org/@tanstack/react-query/latest — @tanstack/react-query 5.90.21 (verified)
- https://registry.npmjs.org/@tanstack/react-virtual/latest — @tanstack/react-virtual 3.13.19 (verified)
- https://registry.npmjs.org/react-intersection-observer/latest — react-intersection-observer 10.0.3 (verified)
- https://registry.npmjs.org/zod/latest — zod 4.3.6 (verified)
- https://registry.npmjs.org/@inertiajs/react/latest — @inertiajs/react 2.3.16 (verified)
- https://registry.npmjs.org/@inertiajs/inertia/latest — @inertiajs/inertia is deprecated (verified)

---
*Stack research for: Xtream-based VOD/series companion app (Laravel 12 + Inertia React)*
*Researched: 2026-02-25*
