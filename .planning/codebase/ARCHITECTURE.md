# Architecture

**Analysis Date:** 2026-02-24

## Pattern Overview

**Overall:** Laravel 12 monolith (server-side routing + MVC) with Inertia.js + React SPA frontend, backed by an explicit application-layer “Action” pattern (`app/Actions/*`) and typed DTOs (`app/Data/*`, `app/Http/Integrations/**/Responses/*`).

**Key Characteristics:**
- Server-driven UI: controllers return `Inertia::render(...)` (e.g., `app/Http/Controllers/VodStream/VodStreamController.php`) mapped to React pages in `resources/js/pages/**` via `resources/views/app.blade.php` and `resources/js/app.tsx`.
- Application logic is centralized in invokable “actions” using `App\Concerns\AsAction` (`app/Concerns/AsAction.php`) and consumed from controllers/jobs/commands (e.g., `app/Actions/SyncMedia.php`, `app/Actions/DownloadMedia.php`).
- External service calls are organized as Saloon connectors + request/response DTOs (`app/Http/Integrations/**`) with caching controls and DTO hydration (e.g., `app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php`).

## Layers

**HTTP / Delivery Layer:**
- Purpose: Define routes, apply middleware, handle request lifecycle, and return Inertia/Blade responses.
- Location: `routes/*.php`, `app/Http/Controllers/**`, `app/Http/Middleware/**`, `app/Http/Requests/**`.
- Contains:
  - Web routes: `routes/web.php` (includes `routes/settings.php` + `routes/auth.php`).
  - Middleware:
    - Inertia root + shared props: `app/Http/Middleware/HandleInertiaRequests.php`.
    - Theme cookie → Blade shared var: `app/Http/Middleware/HandleAppearance.php`.
  - Controllers for core features:
    - Media browsing: `app/Http/Controllers/VodStream/VodStreamController.php`, `app/Http/Controllers/Series/SeriesController.php`.
    - Search: `app/Http/Controllers/SearchController.php`, `app/Http/Controllers/LightweightSearchController.php`.
    - Downloads: `app/Http/Controllers/VodStream/VodStreamDownloadController.php`, `app/Http/Controllers/Series/SeriesDownloadController.php`, `app/Http/Controllers/MediaDownloadsController.php`.
    - Watchlist: `app/Http/Controllers/WatchlistController.php`, `app/Http/Controllers/VodStream/VodStreamWatchlistController.php`, `app/Http/Controllers/Series/SeriesWatchlistController.php`.
- Depends on: Eloquent models (`app/Models/**`), actions (`app/Actions/**`), integration connectors (`app/Http/Integrations/**`), DTOs (`app/Data/**`).

**Application Layer (Actions + Pipelines):**
- Purpose: Encapsulate business operations as invokable units, callable from multiple entry points.
- Location: `app/Actions/**`, `app/Filters/**`, `app/Concerns/AsAction.php`.
- Contains:
  - “Command-style” orchestration actions:
    - Media sync: `app/Actions/SyncMedia.php`.
    - Download orchestration: `app/Actions/DownloadMedia.php`, `app/Actions/BatchDownloadMedia.php`, `app/Actions/CreateSignedDirectLink.php`.
  - Query/search actions implemented as Scout pipelines:
    - Movies search: `app/Actions/SearchMovies.php` with filters in `app/Filters/MoviesSortByFilter.php`, `app/Filters/PaginatorFilter.php`, `app/Filters/LightweightSearchFilter.php`.
- Depends on: integrations (`app/Http/Integrations/**`), models (`app/Models/**`), Scout (`Laravel\Scout\Builder` used in `app/Filters/*`).
- Used by: controllers (`app/Http/Controllers/**`), jobs (`app/Jobs/**`), console commands (`app/Console/Commands/**`).

**Data / DTO Layer (Backend + Shared Types):**
- Purpose: Provide typed payloads for request binding, response shaping, and shared TypeScript type generation.
- Location: `app/Data/**`, `app/Http/Integrations/**/Responses/**`, TypeScript output `resources/js/types/generated.d.ts`.
- Contains:
  - Request-bound data objects (Spatie Laravel Data): `app/Data/SearchMediaData.php`, `app/Data/BatchDownloadEpisodesData.php`, `app/Data/EditMediaDownloadData.php`.
  - Response DTOs from external APIs: `app/Http/Integrations/LionzTv/Responses/VodInformation.php`, `app/Http/Integrations/LionzTv/Responses/SeriesInformation.php`.
  - TypeScript generation markers: `#[TypeScript]` on PHP DTOs (e.g., `app/Data/SearchMediaData.php`, `app/Http/Integrations/LionzTv/Responses/SeriesInformation.php`) producing `resources/js/types/generated.d.ts`.

**Persistence Layer (Eloquent + Migrations):**
- Purpose: Store application state: users, media catalog, watchlist, download references, and settings.
- Location: `app/Models/**`, `database/migrations/**`.
- Examples:
  - Media models with Scout search: `app/Models/VodStream.php`, `app/Models/Series.php`.
  - Download reference model: `app/Models/MediaDownloadRef.php`.
  - Watchlist model: `app/Models/Watchlist.php`.
  - Migrations: `database/migrations/2025_03_05_154156_create_vod_streams_table.php`, `database/migrations/2025_03_05_143249_create_series_table.php`, `database/migrations/2025_03_11_225950_create_watchlists_table.php`, `database/migrations/2025_04_01_171803_create_media_download_refs_table.php`.

**Integration Layer (External Services):**
- Purpose: Encapsulate all network IO through connectors, typed request classes, and typed response DTOs.
- Location: `app/Http/Integrations/**`.
- Lionz/Xtream Codes API:
  - Connector: `app/Http/Integrations/LionzTv/XtreamCodesConnector.php`.
  - Requests + caching: `app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php`, `app/Http/Integrations/LionzTv/Requests/GetSeriesInfoRequest.php`.
  - DTO responses: `app/Http/Integrations/LionzTv/Responses/*`.
- aria2 JSON-RPC:
  - Connector + error handling: `app/Http/Integrations/Aria2/JsonRpcConnector.php`, `app/Http/Integrations/Aria2/JsonRpcException.php`.
  - JSON-RPC request envelope: `app/Http/Integrations/Aria2/Requests/JsonRpcRequest.php`, batch: `app/Http/Integrations/Aria2/Requests/JsonRpcBatchRequest.php`.
  - Response wrappers: `app/Http/Integrations/Aria2/Responses/JsonRpcResponse.php`, `app/Http/Integrations/Aria2/Responses/JsonRpcBatchResponse.php`.

**Background Processing Layer (Queue + Scheduler + Console):**
- Purpose: Run long-running tasks off-request and periodically.
- Location: `app/Jobs/**`, `routes/console.php`, `app/Console/Commands/**`.
- Examples:
  - Scheduled job: `routes/console.php` schedules `App\Jobs\RefreshMediaContents`.
  - Queue job: `app/Jobs/RefreshMediaContents.php` → calls `SyncMedia::run()`.
  - Console commands:
    - Media sync: `app/Console/Commands/SyncMediaCommand.php`.
    - Initialize configs: `app/Console/Commands/InitializeConfigurationsCommand.php`.

**Frontend Layer (Inertia + React):**
- Purpose: UI/UX implemented as React pages and reusable components, driven by server-provided props.
- Location: `resources/js/**`, root template `resources/views/app.blade.php`.
- Key parts:
  - Inertia client entry: `resources/js/app.tsx`.
  - Inertia SSR entry: `resources/js/ssr.tsx` (used by `config/inertia.php`).
  - Pages (match `Inertia::render('<page>')` strings):
    - `Inertia::render('movies/show')` → `resources/js/pages/movies/show.tsx`.
    - `Inertia::render('series/show')` → `resources/js/pages/series/show.tsx`.
    - `Inertia::render('downloads')` → `resources/js/pages/downloads.tsx`.
  - Components: `resources/js/components/**` and shared UI primitives in `resources/js/components/ui/**`.
  - Layouts: `resources/js/layouts/**`.
  - Types: hand-written `resources/js/types/*.ts` plus generated `resources/js/types/generated.d.ts`.

## Data Flow

**HTTP request → Inertia page render (typical page navigation):**

1. Web request enters Laravel via `public/index.php`.
2. App is configured and routed via `bootstrap/app.php` → `routes/web.php`.
3. Middleware runs:
   - `app/Http/Middleware/HandleAppearance.php` shares `appearance` with Blade views.
   - `app/Http/Middleware/HandleInertiaRequests.php` sets root view `resources/views/app.blade.php` and shares global props (auth + flash + ziggy).
4. Controller executes domain logic and returns `Inertia::render(...)`:
   - Example: `app/Http/Controllers/VodStream/VodStreamController.php::index()` returns `Inertia::render('movies/index', ['movies' => $movies])`.
5. Root template `resources/views/app.blade.php` loads:
   - `resources/js/app.tsx` (Inertia client bootstrap)
   - `resources/js/pages/{component}.tsx` (requested page)
6. Frontend renders the page component, using shared props and page props.

**Search flow (Scout + Pipeline filters):**

1. GET `/search` hits `routes/web.php` → `app/Http/Controllers/SearchController.php::show(SearchMediaData $search)`.
2. `SearchMediaData` is constructed from request inputs (`app/Data/SearchMediaData.php`).
3. Controller calls search actions:
   - `app/Actions/SearchMovies.php` and `app/Actions/SearchSeries.php`.
4. Search actions use Scout builder (`VodStream::search($query)` in `app/Actions/SearchMovies.php`) piped through filters:
   - Sorting: `app/Filters/MoviesSortByFilter.php`.
   - Pagination: `app/Filters/PaginatorFilter.php`.
5. Results are returned to `Inertia::render('search', ...)` and displayed by `resources/js/pages/search.tsx`.

**Lightweight search flow (partial Inertia component render):**

1. POST `/search` hits `routes/web.php` → `app/Http/Controllers/LightweightSearchController.php::show(...)`.
2. Controller reads Inertia partial component header (`Inertia\Support\Header::PARTIAL_COMPONENT`) and renders that component with lightweight results.
3. Results are shaped into `App\Data\LightweightSearchData` and consumed in the frontend (e.g., overlay components in `resources/js/components/search-overlay.tsx`).

**Media library sync (scheduler/queue → external API → DB + index):**

1. Scheduler runs `routes/console.php` scheduled task → dispatches `App\Jobs\RefreshMediaContents`.
2. Job `app/Jobs/RefreshMediaContents.php` calls `SyncMedia::run()`.
3. `app/Actions/SyncMedia.php`:
   - Clears Scout indexes (best-effort): `VodStream::removeAllFromSearch()` and `Series::removeAllFromSearch()`.
   - Deletes and upserts catalog models: `app/Models/Series.php`, `app/Models/VodStream.php`.
   - Uses Saloon connector `app/Http/Integrations/LionzTv/XtreamCodesConnector.php` with requests:
     - `app/Http/Integrations/LionzTv/Requests/GetSeriesRequest.php`
     - `app/Http/Integrations/LionzTv/Requests/GetVodStreamsRequest.php`
   - Rebuilds Scout indexes (best-effort): `Series::makeAllSearchable(...)`, `VodStream::makeAllSearchable(...)`.

**Download flow (UI → action orchestration → aria2):**

1. User triggers download from UI (e.g., `resources/js/pages/movies/show.tsx` uses `route('movies.download', ...)`).
2. Controller route: `routes/web.php` → `app/Http/Controllers/VodStream/VodStreamDownloadController.php::create(...)`.
3. Controller fetches metadata from Xtream Codes (cached) using:
   - `app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php` → `VodInformation` DTO.
4. Controller builds a remote URL with `app/Actions/CreateXtreamcodesDownloadUrl.php`.
5. Controller starts aria2 download via `app/Actions/DownloadMedia.php` → `app/Http/Integrations/Aria2/Requests/AddUriRequest.php`.
6. Controller persists reference for tracking in `app/Models/MediaDownloadRef.php`.
7. Downloads page (`resources/js/pages/downloads.tsx`) polls and triggers status/actions against:
   - `app/Http/Controllers/MediaDownloadsController.php` using `app/Actions/GetDownloadStatus.php`.

**Direct download link flow (signed route + cache token):**

1. UI triggers “direct” route (e.g., `route('movies.direct', ...)` from `resources/js/pages/movies/show.tsx`).
2. Controller creates signed link:
   - `app/Actions/CreateSignedDirectLink.php` stores remote URL under `direct:link:{token}` in cache and returns `URL::temporarySignedRoute('direct.resolve', ...)`.
3. Start page renders a small Blade view `resources/views/direct-download/start.blade.php` that navigates to the signed route.
4. Resolver endpoint `routes/web.php` → `app/Http/Controllers/DirectDownloadController.php::show(...)`:
   - Validates feature flag `config/features.php`.
   - Reads cache token and redirects to remote URL (or uses `Inertia::location(...)` for XHR).

## Key Abstractions

**Actions (`AsAction`):**
- Purpose: Make domain operations invokable + container-resolved (`::make()`/`::run()`), usable from controllers/jobs/commands.
- Implementation: `app/Concerns/AsAction.php`.
- Examples:
  - `app/Actions/SyncMedia.php` (catalog sync).
  - `app/Actions/DownloadMedia.php` (aria2 download).
  - `app/Actions/GetDownloadStatus.php` (status polling).

**DTOs (Spatie Laravel Data + TypeScript generation):**
- Purpose: Typed, serializable request/response objects; automatic TS definitions.
- Examples:
  - Request-bound: `app/Data/SearchMediaData.php`.
  - Frontend TS output: `resources/js/types/generated.d.ts`.
  - Integration DTOs: `app/Http/Integrations/LionzTv/Responses/SeriesInformation.php`.

**Integrations (Saloon connector + request/response classes):**
- Purpose: Standardize external IO, caching, auth, and DTO conversion.
- Patterns:
  - Connector holds base URL + auth: `app/Http/Integrations/LionzTv/XtreamCodesConnector.php`.
  - Request defines endpoint + query + caching: `app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php`.
  - Response DTOs are immutable and TS-exported: `app/Http/Integrations/LionzTv/Responses/VodInformation.php`.

**Config-from-DB with env fallback:**
- Purpose: Runtime configuration stored in DB but loadable from `.env` when DB row is missing.
- Implementation:
  - Contract: `app/Contracts/Models/EnvConfigurable.php`.
  - Trait: `app/Concerns/LoadsFromEnv.php` provides `firstOrFromEnv()`.
  - Models: `app/Models/XtreamCodesConfig.php`, `app/Models/Aria2Config.php`.
  - Container bindings: `app/Providers/AppServiceProvider.php` binds config models to `firstOrFromEnv()`.

## Entry Points

**HTTP:**
- `public/index.php` bootstraps the framework and calls `bootstrap/app.php`.
- `bootstrap/app.php` registers routing for `routes/web.php` and `routes/console.php`, configures middleware, and wires Sentry exception handling.

**Routing:**
- `routes/web.php` defines all HTTP endpoints and route names used by the frontend via Ziggy (`app/Http/Middleware/HandleInertiaRequests.php`).
- `routes/auth.php` and `routes/settings.php` are required from `routes/web.php`.

**Frontend:**
- `resources/views/app.blade.php` is the Inertia root view (`$rootView = 'app'` in `app/Http/Middleware/HandleInertiaRequests.php`).
- `resources/js/app.tsx` boots the Inertia client and resolves pages from `resources/js/pages/**`.
- `resources/js/ssr.tsx` boots Inertia SSR (configured in `config/inertia.php`).

**Console / Background:**
- `artisan` runs console commands.
- Scheduled tasks are declared in `routes/console.php`.
- Commands are implemented in `app/Console/Commands/**`.
- Jobs are in `app/Jobs/**`.

## Error Handling

**Strategy:** Prefer framework exceptions for hard failures, return validation/action errors via redirects with errors for UX, and centralize external API failures at connector/request boundaries.

**Patterns:**
- External service errors:
  - aria2 connector uses `Saloon\Traits\Plugins\AlwaysThrowOnErrors` and custom exception mapping in `app/Http/Integrations/Aria2/JsonRpcConnector.php`.
  - Batch RPC calls normalize error payloads in `app/Actions/GetDownloadStatus.php` and `app/Actions/BatchDownloadMedia.php`.
- Feature gating uses config + abort:
  - Direct links: `config/features.php` checked in `app/Http/Controllers/DirectDownloadController.php`, `app/Http/Controllers/VodStream/VodStreamDownloadController.php`, `app/Http/Controllers/Series/SeriesDownloadController.php`.

## Cross-Cutting Concerns

**Logging:** `Illuminate\Support\Facades\Log` used throughout (e.g., `app/Actions/SyncMedia.php`, `app/Http/Controllers/DirectDownloadController.php`, `routes/console.php`).

**Validation:**
- FormRequests for classic request validation: `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Requests/StoreWatchlistRequest.php`.
- Spatie Data for typed payload binding + validation: `app/Data/SearchMediaData.php`, `app/Data/EditMediaDownloadData.php`.

**Authentication:**
- Route middleware: `routes/web.php` uses `auth` + `verified` groups.
- Current user injection: `#[CurrentUser]` in controllers (e.g., `app/Http/Controllers/DiscoverController.php`).

---

*Architecture analysis: 2026-02-24*
