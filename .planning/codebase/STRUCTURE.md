# Codebase Structure

**Analysis Date:** 2026-02-24

## Directory Layout

```
[project-root]/
├── app/                     # Laravel application code (controllers, models, actions, integrations)
├── bootstrap/               # Laravel bootstrap + app configuration entry
├── config/                  # Laravel configuration files
├── database/                # SQLite DB file, migrations, factories, seeders
├── docker/                  # Container build definitions
├── public/                  # Web server document root (index.php)
├── resources/               # Frontend code (React/Inertia), views, CSS, images
├── routes/                  # Route definitions (web/auth/settings/console)
├── storage/                 # Runtime storage (cache/logs/sessions) + framework files
├── tests/                   # Pest test suite
├── vendor/                  # Composer dependencies
├── node_modules/            # Node dependencies (dev only)
├── docker-compose.yml       # Multi-service deployment (app/queue/scheduler)
├── composer.json            # PHP dependencies + scripts
├── package.json             # Frontend dependencies + scripts
├── vite.config.ts           # Vite build configuration (React + Inertia)
├── tsconfig.json            # TypeScript configuration (path aliases)
└── .planning/codebase/      # GSD codebase mapping output (this folder)
```

## Directory Purposes

**`app/`:**
- Purpose: All PHP application logic.
- Contains: feature controllers, domain actions, DTOs, integrations, models.
- Key subdirectories:
  - `app/Http/Controllers/**`: HTTP controllers (e.g., `app/Http/Controllers/VodStream/VodStreamController.php`).
  - `app/Http/Middleware/**`: request middleware (e.g., `app/Http/Middleware/HandleInertiaRequests.php`).
  - `app/Http/Requests/**`: FormRequest validation (e.g., `app/Http/Requests/Auth/LoginRequest.php`).
  - `app/Http/Integrations/**`: Saloon connectors + request/response DTOs for external services.
    - Xtream Codes: `app/Http/Integrations/LionzTv/**`.
    - aria2 JSON-RPC: `app/Http/Integrations/Aria2/**`.
  - `app/Actions/**`: application-layer operations (e.g., `app/Actions/SyncMedia.php`, `app/Actions/DownloadMedia.php`).
  - `app/Data/**`: backend DTOs (Spatie Laravel Data) and TS-exported shapes (e.g., `app/Data/SearchMediaData.php`).
  - `app/Models/**`: Eloquent models (e.g., `app/Models/VodStream.php`, `app/Models/MediaDownloadRef.php`).
  - `app/Jobs/**`: queued jobs (e.g., `app/Jobs/RefreshMediaContents.php`).
  - `app/Console/Commands/**`: artisan commands (e.g., `app/Console/Commands/SyncMediaCommand.php`).
  - `app/Providers/**`: container bindings + boot-time config (e.g., `app/Providers/AppServiceProvider.php`).

**`routes/`:**
- Purpose: URL → controller mapping.
- Key files:
  - `routes/web.php`: main web routes + route names consumed by Ziggy/JS.
  - `routes/auth.php`: auth-related routes required by `routes/web.php`.
  - `routes/settings.php`: settings routes required by `routes/web.php`.
  - `routes/console.php`: scheduler declarations (e.g., `Schedule::job(RefreshMediaContents::class)`).

**`resources/`:**
- Purpose: Frontend + Blade templates.
- Key locations:
  - `resources/views/app.blade.php`: Inertia root view used by `app/Http/Middleware/HandleInertiaRequests.php`.
  - `resources/views/direct-download/start.blade.php`: direct-download redirect/UX page.
  - `resources/js/app.tsx`: Inertia client entry.
  - `resources/js/ssr.tsx`: Inertia SSR entry.
  - `resources/js/pages/**`: Inertia pages matched by `Inertia::render('<page>')` strings.
    - Example: `Inertia::render('movies/show')` → `resources/js/pages/movies/show.tsx`.
  - `resources/js/components/**`: reusable components.
  - `resources/js/components/ui/**`: shared UI primitives.
  - `resources/js/layouts/**`: layout components (e.g., `resources/js/layouts/app-layout.tsx`).
  - `resources/js/hooks/**`: hooks and client utilities.
  - `resources/js/types/**`: TS types, including generated `resources/js/types/generated.d.ts` from `config/typescript-transformer.php`.

**`database/`:**
- Purpose: Database definition and local sqlite file.
- Contains:
  - `database/migrations/**`: schema migrations.
  - `database/factories/**`: model factories.
  - `database/seeders/**`: seeders.
  - `database/database.sqlite`: local SQLite database file.

**`config/`:**
- Purpose: Application configuration.
- Key files:
  - `config/inertia.php`: SSR + Inertia testing paths.
  - `config/services.php`: external service connection settings (aria2 + Xtream Codes).
  - `config/scout.php`: Scout driver and Meilisearch index settings.
  - `config/features.php`: feature flags (e.g., direct download links).

**`public/`:**
- Purpose: Web server entry point.
- Key file: `public/index.php`.

**`bootstrap/`:**
- Purpose: Framework bootstrapping.
- Key file: `bootstrap/app.php`.

**`tests/`:**
- Purpose: Automated tests.
- Key files:
  - `tests/Pest.php`: Pest bootstrap.
  - `tests/TestCase.php`: base test case.
  - `tests/Feature/**`: HTTP/feature tests.
  - `tests/Unit/**`: unit tests.

**`docker/` + `docker-compose.yml`:**
- Purpose: Container build and deployment.
- `docker-compose.yml` defines `application`, `queue`, and `scheduler` services.

## Key File Locations

**Entry Points:**
- `public/index.php`: HTTP entry point.
- `bootstrap/app.php`: application configuration + middleware + routing wiring.
- `resources/js/app.tsx`: frontend entry point.
- `resources/js/ssr.tsx`: SSR entry point.

**Configuration:**
- `config/services.php`: aria2 + Xtream Codes endpoints/credentials.
- `config/scout.php`: search indexing settings.
- `config/inertia.php`: SSR enablement and server URL.
- `vite.config.ts`: Vite build inputs + SSR bundle config.
- `tsconfig.json`: TS settings and path alias (`@/*` → `resources/js/*`).

**Core Logic:**
- Actions: `app/Actions/**` (business operations).
- Integrations: `app/Http/Integrations/**` (external IO).
- Models: `app/Models/**`.
- DTOs: `app/Data/**` and integration DTOs in `app/Http/Integrations/**/Responses/**`.

**Testing:**
- Pest entry: `tests/Pest.php`.
- Feature tests: `tests/Feature/**`.

## Naming Conventions

**PHP (Laravel):**
- Classes use `StudlyCase` filenames matching class names (e.g., `app/Actions/SyncMedia.php`, `app/Models/VodStream.php`).
- Namespaces map to paths via PSR-4 (`App\\` → `app/`) defined in `composer.json`.

**Frontend (React/TypeScript):**
- Pages are path-based and lowercase: `resources/js/pages/movies/show.tsx`, `resources/js/pages/settings/profile.tsx`.
- Reusable components typically use kebab-case: `resources/js/components/media-hero-section.tsx`, `resources/js/components/watchlist-button.tsx`.
- Imports prefer the `@/*` alias configured in `tsconfig.json` (e.g., `import AppLayout from '@/layouts/app-layout';` in `resources/js/pages/movies/show.tsx`).

## Where to Add New Code

**New HTTP endpoint (page/action):**
- Route: add to `routes/web.php` (or `routes/settings.php` / `routes/auth.php` if it matches that area).
- Controller: implement in `app/Http/Controllers/**`.
- Page: return `Inertia::render('<path>')` from controller and create the matching file in `resources/js/pages/<path>.tsx`.

**New business operation:**
- Action: add an invokable action in `app/Actions/**` and use `App\Concerns\AsAction` (`app/Concerns/AsAction.php`) if it should be callable as `::run()`.

**New external service integration:**
- Connector: add a connector under `app/Http/Integrations/<Service>/*Connector.php`.
- Requests: place request classes under `app/Http/Integrations/<Service>/Requests/**`.
- DTO responses: place immutable DTOs under `app/Http/Integrations/<Service>/Responses/**` and mark with `#[TypeScript]` when frontend type generation is required (see `app/Http/Integrations/LionzTv/Responses/VodInformation.php`).

**New shared data shape between backend + frontend:**
- Backend DTO: add to `app/Data/**` and mark with `#[TypeScript]` if it should generate TS types (see `app/Data/SearchMediaData.php`).
- Frontend types: consume from `resources/js/types/generated.d.ts` or add a local wrapper in `resources/js/types/*.ts`.

**New UI component:**
- Component: `resources/js/components/<name>.tsx`.
- UI primitive: `resources/js/components/ui/<name>.tsx`.
- Layout: `resources/js/layouts/**`.

**New database table/change:**
- Migration: `database/migrations/**`.
- Model: `app/Models/**`.
- If searchable: add Scout `Searchable` and update `config/scout.php` index settings for the model.

## Special Directories

**`storage/`:**
- Purpose: runtime (logs, cache, sessions, framework files).
- Generated: Yes.
- Committed: Partially (directory structure), contents are runtime.

**`vendor/` and `node_modules/`:**
- Purpose: dependency installs.
- Generated: Yes.
- Committed: No.

**`.planning/codebase/`:**
- Purpose: generated architecture/structure mapping docs used by GSD planning/execution.
- Generated: Yes.
- Committed: Repository-specific.

---

*Structure analysis: 2026-02-24*
