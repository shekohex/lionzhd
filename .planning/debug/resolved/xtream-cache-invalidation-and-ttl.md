---
status: resolved
trigger: "Investigate issue: xtream-cache-invalidation-and-ttl"
created: 2026-02-24T14:47:03+00:00
updated: 2026-02-24T14:55:21+00:00
---

## Current Focus

hypothesis: Verification phase: ensure regression tests prove scope isolation, namespace busting, and updated TTL behavior.
test: Validate targeted unit/feature tests covering cache keys and sync behavior.
expecting: all targeted tests pass with no stale-cache behavior regression.
next_action: archived; report debug completion.

## Symptoms

expected: Redis caches for remote movies/series lists and DTOs should have correct TTL/size/scope, invalidate action should reliably purge relevant keys and force fresh remote fetch, and new episodes should appear without full reset.
actual: Cached data persists incorrectly and/or is over-stored; invalidate cache button does not work most of the time; newly released episodes are frequently not visible until full server and Redis reset.
errors: No explicit runtime error reported by user.
reproduction: Run media sync from remote Xtream Codes host to cache movies/series and DTOs in Redis; wait for remote catalog update (especially series with frequent episode releases); trigger cache invalidation button; observe stale data remains and refetch is inconsistent.
started: Ongoing current issue; exact start time unknown.

## Eliminated

- hypothesis: Invalidate button fails due route model-key mismatch between frontend and backend.
  evidence: Series/Vod models use primary keys series_id/stream_id and frontend sends info.seriesId/info.vodId; route-model binding aligns.
  timestamp: 2026-02-24T14:49:38+00:00

## Evidence

- timestamp: 2026-02-24T14:49:38+00:00
  checked: app/Http/Integrations/LionzTv/Requests/GetSeriesInfoRequest.php
  found: cache key is "series_info_{id}" and TTL is 86400s (1 day).
  implication: frequent episode updates can remain stale up to a full day unless key is force-invalidated.

- timestamp: 2026-02-24T14:49:38+00:00
  checked: app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php
  found: cache key is "vod_info_{id}" and TTL is 2592000s (30 days).
  implication: VOD metadata can stay stale for long periods and cache growth is high.

- timestamp: 2026-02-24T14:49:38+00:00
  checked: app/Actions/SyncMedia.php
  found: sync deletes/reimports DB records but never clears Redis DTO caches.
  implication: catalog refresh does not invalidate cached details, so old episodes/details persist.

- timestamp: 2026-02-24T14:49:38+00:00
  checked: app/Models/Series.php, app/Models/VodStream.php, routes/web.php, resources/js/pages/*/show.tsx
  found: route-model binding keys match Xtream ids, so invalidate route targets correct model ids.
  implication: primary invalidation issue is not route parameter mismatch.

- timestamp: 2026-02-24T14:51:31+00:00
  checked: vendor/saloonphp/cache-plugin/src/Traits/HasCaching.php and Http/Middleware/CacheMiddleware.php
  found: cache key is sha256(custom cacheKey); invalidateCache refreshes only that exact key.
  implication: using unscoped custom keys means data scope depends entirely on our key format; current id-only format is insufficient.

- timestamp: 2026-02-24T14:54:34+00:00
  checked: app/Http/Integrations/LionzTv/Requests/GetSeriesInfoRequest.php and GetVodInfoRequest.php
  found: cache keys now include namespace version + connector scope hash(baseUrl|username) + stream id; TTL lowered to 10m (series) and 12h (vod).
  implication: stale lifetime reduced and cross-host/user cache collisions are prevented.

- timestamp: 2026-02-24T14:54:34+00:00
  checked: app/Actions/SyncMedia.php
  found: sync now increments xtream:dto:cache:namespace after refresh.
  implication: each successful sync invalidates all prior DTO cache namespace keys without global Redis reset.

- timestamp: 2026-02-24T14:54:34+00:00
  checked: tests/Unit/Http/Integrations/LionzTv/Requests/RequestCachingTest.php and tests/Feature/Jobs/RefreshMediaContentsTest.php
  found: added regression tests for cache scope isolation, TTL expectations, and sync namespace bump.
  implication: behavior is now test-covered and less likely to regress.

- timestamp: 2026-02-24T14:55:04+00:00
  checked: php artisan test tests/Unit/Http/Integrations/LionzTv/Requests/RequestCachingTest.php tests/Feature/Jobs/RefreshMediaContentsTest.php
  found: 6 tests passed (11 assertions).
  implication: implemented cache scoping/TTL/namespace bust logic behaves as intended for covered scenarios.

## Resolution

root_cause:
  Xtream DTO caching used static keys (series_info_{id}/vod_info_{id}) without host/user scope and with long TTLs (1d/30d), while SyncMedia refresh never invalidated DTO caches. This allowed stale detail payloads and cross-scope cache contamination to survive until manual Redis reset.
fix:
  Added scoped/versioned Xtream DTO cache keys (baseUrl+username+namespace), reduced TTLs for volatile DTOs, and bumped DTO cache namespace after each successful SyncMedia run.
verification:
  Targeted regression suite passed: RequestCachingTest + RefreshMediaContentsTest (6 passing tests, 11 assertions).
files_changed:
  - app/Actions/SyncMedia.php
  - app/Http/Integrations/LionzTv/XtreamCodesConnector.php
  - app/Http/Integrations/LionzTv/Requests/GetSeriesInfoRequest.php
  - app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php
  - tests/Feature/Jobs/RefreshMediaContentsTest.php
  - tests/Unit/Http/Integrations/LionzTv/Requests/RequestCachingTest.php
