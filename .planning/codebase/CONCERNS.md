# Codebase Concerns

**Analysis Date:** 2026-02-24

## Tech Debt

**Singleton configuration stored as multi-row tables (silent fallback to env):**
- Issue: Configuration models assume a single row but tables allow multiple rows; `sole()` failures fall back to env-backed, unsaved models.
- Files: `app/Concerns/LoadsFromEnv.php`, `app/Providers/AppServiceProvider.php`, `app/Console/Commands/InitializeConfigurationsCommand.php`, `app/Http/Controllers/Settings/Aria2ConfigController.php`, `app/Http/Controllers/Settings/XtreamCodeConfigController.php`, `database/migrations/2025_03_04_224220_create_aria2_configs_table.php`, `database/migrations/2025_03_04_224225_create_xtream_codes_configs_table.php`
- Impact: Duplicate rows accumulate; UI can show env values instead of DB; updates can insert additional rows; runtime bindings silently ignore DB when duplicates exist.
- Fix approach: Enforce single-row invariant (DB unique constraint or fixed primary key like `id=1`); replace `sole()` + catch-all with explicit “first row” selection and a hard failure when duplicates exist.

**SQLite tuning is present but disabled:**
- Issue: SQLite PRAGMA tuning exists but is entirely commented out.
- Files: `database/migrations/0001_01_01_000000_asqlitedb_settings.php`, `app/Providers/AppServiceProvider.php`
- Impact: Higher likelihood of lock contention, slower writes, and poor behavior under concurrent queue/scheduler/app processes.
- Fix approach: Either remove the dead code or activate PRAGMAs via supported config (`config/database.php` sqlite options) / migration with verified statements.

**Aria2 `use_ssl` setting is persisted but not used to build endpoints:**
- Issue: `use_ssl` exists in DB/data layer but endpoint composition uses `host` directly.
- Files: `app/Models/Aria2Config.php`, `app/Data/Aria2ConfigData.php`, `database/migrations/2025_03_04_224220_create_aria2_configs_table.php`
- Impact: Settings UI/state suggests TLS toggle exists; runtime behavior ignores it.
- Fix approach: Normalize RPC endpoint from `use_ssl` + host/port (or delete `use_ssl` entirely and require scheme in `host`).

**Dead / unused code paths that encode incorrect assumptions:**
- Issue: `XtreamCodesConfig::getApiUrl()` hardcodes `http://` while `host` commonly includes a scheme; the method is unused and would produce invalid URLs when `host` contains `http(s)://`.
- Files: `app/Models/XtreamCodesConfig.php`, `config/services.php`
- Impact: Future use of `getApiUrl()` breaks requests; host normalization is unclear.
- Fix approach: Normalize `host` to be scheme-less and build URLs once, or require scheme and never prepend `http://`.

## Known Bugs

**Episode de-dup logic can collide across seasons:**
- Symptoms: `GetActiveDownloads` filters by `episode` but not `season` for series, allowing S01E01 to block S02E01.
- Files: `app/Actions/GetActiveDownloads.php`, `app/Models/MediaDownloadRef.php`, `database/migrations/2025_04_01_171803_create_media_download_refs_table.php`
- Trigger: Starting downloads for different seasons with the same `episodeNum`.
- Workaround: None detected.

**Series episode selection uses unsafe array indexing:**
- Symptoms: Undefined index notices / wrong episode selection when requested season/episode keys don’t exist.
- Files: `app/Http/Controllers/Series/SeriesDownloadController.php`
- Trigger: Invalid `{season}` / `{episode}` route params for `series.download.single` / `series.direct.single`.
- Workaround: None detected.

**Search pagination inputs are nullable but used as required ints:**
- Symptoms: Type errors when `per_page` is absent/null; inconsistent pagination limits.
- Files: `app/Data/SearchMediaData.php`, `app/Http/Controllers/SearchController.php`, `app/Http/Controllers/LightweightSearchController.php`
- Trigger: Requests missing `per_page`.
- Workaround: Frontend currently supplies defaults (e.g. `resources/js/pages/search.tsx` via `SearchInput`), but backend does not enforce.

**Responsive image component does not reset on source change:**
- Symptoms: Changing `src`/fallbacks does not reset attempt index/loading state; console logs in production.
- Files: `resources/js/components/responsive-image.tsx`
- Trigger: Re-render with different `src`/fallback props.
- Workaround: Full remount of component.

**Download record deletion can lose state on RPC failure:**
- Symptoms: DB record deleted even when Aria2 removal fails, leaving remote download entry orphaned from UI.
- Files: `app/Http/Controllers/MediaDownloadsController.php`
- Trigger: `downloads.destroy` when Aria2 JSON-RPC errors/timeouts.
- Workaround: None detected.

## Security Considerations

**Aria2 RPC is configured for wide exposure by default:**
- Risk: `rpc-listen-all=true` + `rpc-allow-origin-all=true` + no `rpc-secret` is an unauthenticated remote-control surface.
- Files: `aria2.conf`
- Current mitigation: Not detected in this config file.
- Recommendations: Require `rpc-secret`, disable `rpc-listen-all` (bind loopback), restrict CORS (`rpc-allow-origin-all=false`), and ensure the port is not exposed beyond a trusted network.

**Xtream Codes credentials are embedded into URLs and transmitted over HTTP by default:**
- Risk: Credentials appear in query params for API calls and in the path for download URLs; defaults use `http://`.
- Files: `config/services.php`, `app/Http/Integrations/LionzTv/XtreamCodesConnector.php`, `app/Actions/CreateXtreamcodesDownloadUrl.php`, `app/Models/XtreamCodesConfig.php`
- Current mitigation: `XtreamCodesConfig::$hidden` hides `password` for serialization; `password` uses encrypted cast (`app/Models/XtreamCodesConfig.php`).
- Recommendations: Require HTTPS for `host`, prefer a proxy/download relay to avoid exposing credentials to browsers, and prevent credential-bearing URLs from being logged.

**Signed direct-download flow leaks sensitive identifiers via browser referrer:**
- Risk: Navigating from `/dl/{token}?signature=...` to a third-party host can send `Referer` containing the signed URL.
- Files: `routes/web.php`, `app/Http/Controllers/DirectDownloadController.php`, `resources/views/direct-download/start.blade.php`, `app/Actions/CreateSignedDirectLink.php`
- Current mitigation: Link opens in same tab; no referrer policy configured.
- Recommendations: Set a strict `Referrer-Policy` for direct-download responses (e.g. `no-referrer`) and/or add `rel="noreferrer"` where applicable.

**File/path injection via unsanitized media titles:**
- Risk: `out` / directory paths are built from external API strings (`movie->name`, `series->name`, `episode->title`) without sanitization; path separators or `..` can escape intended folders.
- Files: `app/Actions/CreateDownloadOut.php`, `app/Actions/CreateDownloadDir.php`
- Current mitigation: Only collapses `//`.
- Recommendations: Sanitize to a safe filename component (strip slashes/control chars, collapse whitespace, enforce max length) before passing to Aria2.

## Performance Bottlenecks

**Daily media sync loads full datasets into memory before chunking:**
- Problem: `SyncMedia` fetches entire lists (`GetSeriesRequest`, `GetVodStreamsRequest`) into arrays, then chunks, then `gc_collect_cycles()`.
- Files: `app/Actions/SyncMedia.php`, `app/Jobs/RefreshMediaContents.php`, `routes/console.php`, `config/app.php`
- Cause: External API response is fully materialized.
- Improvement path: Stream/paginate the upstream API where possible; process incrementally without holding full arrays; add metrics around duration/memory.

**Long-lived cache entries can bloat the database cache store:**
- Problem: VOD info caching uses 30-day TTL and per-item keys; on large libraries, cache table growth impacts SQLite performance.
- Files: `app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php`, `config/cache.php`, `database/migrations/0001_01_01_000001_create_cache_table.php`
- Cause: Large payloads stored in DB-backed cache.
- Improvement path: Reduce TTL, namespace by config identity, use a dedicated cache store (Redis), or cache only the required subset.

**Destructive-command prohibition can conflict with scheduled sync:**
- Problem: Production prohibits destructive commands while sync uses full-table deletes.
- Files: `app/Providers/AppServiceProvider.php`, `app/Actions/SyncMedia.php`, `routes/console.php`
- Cause: `DB::prohibitDestructiveCommands(app()->isProduction())` combined with `Series::query()->delete()` / `VodStream::query()->delete()`.
- Improvement path: Wrap sync in an explicit allowlist scope (connection-level allowance) or replace with a safer sync strategy (diff-based updates).

**Inertia SSR is enabled unconditionally but SSR runtime is not started in container entrypoints:**
- Problem: SSR is enabled in config and points to `http://127.0.0.1:13714`.
- Files: `config/inertia.php`, `composer.json`, `docker/Dockerfile`, `docker/entrypoint.sh`, `docker-compose.yml`
- Cause: No production process starts `php artisan inertia:start-ssr` while SSR is enabled.
- Improvement path: Gate SSR with env config and ensure SSR server lifecycle is managed (separate service/container) when enabled.

## Fragile Areas

**Aria2 token injection assumes `params` is always present and secret is non-null:**
- Why fragile: Authenticator prepends `token:{secret}` to `params` without validating shape; a null/empty secret yields `token:`.
- Files: `app/Http/Integrations/Aria2/Auth/Aria2JsonRpcAuthenticator.php`, `app/Models/Aria2Config.php`, `config/services.php`, `docker-compose.yml`
- Safe modification: Make token injection conditional on a non-empty secret and validate `params` as a list.
- Test coverage: No direct unit tests detected for null/empty secret handling.

**Model casting vs schema type mismatch for time fields:**
- Why fragile: DB columns are strings but models cast to `immutable_datetime`.
- Files: `database/migrations/2025_03_05_143249_create_series_table.php`, `database/migrations/2025_03_05_154156_create_vod_streams_table.php`, `app/Models/Series.php`, `app/Models/VodStream.php`
- Safe modification: Store normalized timestamps (integer/datetime) and migrate existing values.
- Test coverage: Not detected.

## Scaling Limits

**SQLite + DB-backed sessions/cache/queue across multiple containers:**
- Current capacity: Single-writer limitations; contention increases with queue + scheduler + web workers.
- Limit: Frequent “database is locked” behavior and degraded latency under concurrent writes.
- Files: `docker-compose.yml`, `config/database.php`, `config/cache.php`, `config/session.php`, `config/queue.php`
- Scaling path: Move DB to MySQL/Postgres and move cache/queue to Redis; avoid DB-backed cache/session for high-traffic deployments.

## Dependencies at Risk

**Composer PHP requirement mismatch:**
- Risk: `require.php` is `^8.4` while `config.platform.php` is pinned to `8.3.6`.
- Impact: Dependency resolution can diverge between environments; CI/prod failures become non-reproducible.
- Files: `composer.json`, `docker/Dockerfile`
- Migration plan: Align `require.php` and `config.platform.php` (or remove platform pin) to match the runtime.

**Unpinned dev dependency from a VCS `dev-main` branch:**
- Risk: Non-deterministic updates.
- Impact: Tooling behavior changes without lockfile intent.
- Files: `composer.json` (repository `driftingly/rector-laravel` + `require-dev` `driftingly/rector-laravel: dev-main`)
- Migration plan: Pin to a tagged release or a specific commit.

## Missing Critical Features

**Rate limiting for link resolution and download endpoints:**
- Problem: `/dl/{token}` and download-trigger endpoints lack explicit throttling.
- Blocks: Protection against brute-force, abuse, and accidental hammering (especially when integrated with download managers).
- Files: `routes/web.php`, `app/Http/Controllers/DirectDownloadController.php`, `app/Http/Controllers/Series/SeriesDownloadController.php`, `app/Http/Controllers/VodStream/VodStreamDownloadController.php`

## Test Coverage Gaps

**Security/architecture checks explicitly ignore critical download paths:**
- What's not tested: Architecture/security presets skip integrations and download controllers.
- Files: `tests/Architecture/GeneralTest.php`
- Risk: Regressions in credential handling, redirects, and filesystem/path generation slip through.
- Priority: High

**No tests for season-aware de-dup and unsafe indexing edge cases:**
- What's not tested: Season filtering in `GetActiveDownloads`; invalid season/episode keys in `SeriesDownloadController`.
- Files: `app/Actions/GetActiveDownloads.php`, `app/Http/Controllers/Series/SeriesDownloadController.php`
- Risk: Incorrect de-dup prevents downloads; runtime notices/errors.
- Priority: High

**No tests for filename/path sanitization:**
- What's not tested: Titles containing path separators/control characters.
- Files: `app/Actions/CreateDownloadOut.php`, `app/Actions/CreateDownloadDir.php`
- Risk: Path traversal and broken downloads.
- Priority: High

---

*Concerns audit: 2026-02-24*
