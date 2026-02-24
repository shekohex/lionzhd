# Technology Stack

**Analysis Date:** 2026-02-24

## Languages

**Primary:**
- PHP 8.4 - Laravel app code in `app/`, config in `config/`, routes in `routes/`, tests in `tests/` (version constraints in `composer.json`, runtime image in `docker/Dockerfile`).

**Secondary:**
- TypeScript/TSX - React/Inertia frontend in `resources/js/` (compiler config in `tsconfig.json`, Vite entrypoints in `vite.config.ts`).
- JavaScript (ESM) - tooling configs like `eslint.config.js` and any non-TS assets (package mode set by `package.json` `"type": "module"`).
- Shell - container bootstrapping in `docker/entrypoint.sh`.
- Nix - dev environment in `flake.nix` (loaded via direnv in `.envrc`).

## Runtime

**Environment:**
- PHP: 8.4 (required by `composer.json` `require.php`, CI sets `php-version: '8.4'` in `.github/workflows/*.yml`, Docker base `dunglas/frankenphp:1.5.0-php8.4.5-bookworm` in `docker/Dockerfile`).
- Web server: FrankenPHP + Caddy (Caddy config in `docker/Caddyfile`; Octane command in `docker/Dockerfile` runs `php artisan octane:frankenphp ...`).
- Node.js: used for Vite/SSR build and dev server (Docker frontend stage uses `node:22-alpine` in `docker/Dockerfile`; CI uses Node 20 in `.github/workflows/lint.yml` and `.github/workflows/tests.yml`).

**Package Manager:**
- PHP: Composer v2 (lockfile: `composer.lock`).
- Node: pnpm (declared in `package.json` `"npm.packageManager": "pnpm"`; CI installs pnpm 10 in `.github/workflows/*.yml`; Docker installs pnpm globally in `docker/Dockerfile`).
- Bun: used for local DX scripts (dev scripts call `bun`/`bunx` in `composer.json` `scripts.dev*`; Bun lockfile present: `bun.lock`).
- Node lockfile: `pnpm-lock.yaml` not detected in repo root (Docker expects it in `docker/Dockerfile` `COPY package*.json pnpm-lock.yaml ./`).

## Frameworks

**Core:**
- Laravel 12 - backend framework (`composer.json` `laravel/framework: ^12.0`; bootstrapping in `bootstrap/app.php`).
- Inertia.js - server-driven SPA routing (`composer.json` `inertiajs/inertia-laravel`; frontend app init in `resources/js/app.tsx`).
- React 19 - UI layer (`package.json` `react`, `react-dom`; SSR entry in `resources/js/ssr.tsx`).
- Vite 6 - frontend bundler/dev server (`package.json` `vite`; config in `vite.config.ts` with `laravel-vite-plugin`).
- Tailwind CSS 4 - styling (`package.json` `tailwindcss`, `@tailwindcss/vite`; main stylesheet `resources/css/app.css`).

**Testing:**
- Pest 3 - PHP tests (`composer.json` `pestphp/pest`, `pestphp/pest-plugin-laravel`; CI runs `./vendor/bin/pest` in `.github/workflows/tests.yml`).
- PHPUnit config: `phpunit.xml` (defines Unit/Feature/Architecture suites; sets test env defaults like `DB_DATABASE=:memory:`).

**Build/Dev:**
- Laravel Octane - long-running app server (`composer.json` `laravel/octane`; config `config/octane.php`; production command in `docker/Dockerfile`).
- Inertia SSR - Node SSR server on `http://127.0.0.1:13714` (`config/inertia.php`; SSR build entrypoint `resources/js/ssr.tsx`; dev script `composer.json` `scripts.dev:ssr` runs `php artisan inertia:start-ssr`).
- shadcn/ui conventions - component generator config (`components.json` with TSX + Tailwind + aliases).

## Key Dependencies

**Critical (backend):**
- `saloonphp/saloon` + Laravel plugin/sender/cache plugin - typed HTTP integrations (`composer.json`; examples in `app/Http/Integrations/**`).
- `laravel/scout` + `meilisearch/meilisearch-php` - search indexing/querying (`composer.json`; config `config/scout.php`; models use `Laravel\Scout\Searchable` in `app/Models/Series.php` and `app/Models/VodStream.php`).
- `sentry/sentry-laravel` - error reporting + performance (`composer.json`; wired in `bootstrap/app.php` and configured in `config/sentry.php`; scheduled monitor in `routes/console.php`).
- `laravel/telescope` + `laravel/pulse` - local observability dashboards (`composer.json`; config `config/telescope.php`, `config/pulse.php`).
- `spatie/laravel-data` - DTO/transform layer (`composer.json`; config `config/data.php`).

**Critical (frontend):**
- `@inertiajs/react` - client adapter (`resources/js/app.tsx`, `resources/js/ssr.tsx`).
- `axios` - HTTP client (`package.json`; usage expected in `resources/js/**` where needed).
- Radix UI + Headless UI - primitives (`package.json` `@radix-ui/*`, `@headlessui/react`).

**Infrastructure/tooling:**
- ESLint flat config + TypeScript-ESLint + React rules (`eslint.config.js`).
- Prettier + import organization + Tailwind plugin (`.prettierrc`; scripts in `package.json`).
- Pint (PHP formatter) (`composer.json` `laravel/pint`; config `pint.json`; CI runs `vendor/bin/pint` in `.github/workflows/lint.yml`).
- PHPStan/Larastan (`phpstan.neon`; enabled Octane compatibility checks).
- Rector (Laravel sets) (`rector.php`).
- Nix-based dev shell includes services/tools (`flake.nix` includes `meilisearch`, `aria2`, `valkey`, `caddy`, `frankenphp`, `php84`, `nodejs_22`, `pnpm`).

## Configuration

**Environment:**
- Primary env file: `.env` (runtime) with template `.env.example`.
- Service credentials convention: `config/*.php` reads env vars via `env('...')` (examples: `config/services.php`, `config/scout.php`, `config/sentry.php`).
- Direnv + nix-direnv loads Nix flake and `.env` if present (`.envrc`).

**Build:**
- Frontend build: Vite (`vite.config.ts`) and scripts in `package.json`.
- TS config and path aliases: `tsconfig.json` maps `@/*` to `resources/js/*`.
- SSR build: `package.json` `build:ssr` and Vite SSR entry `resources/js/ssr.tsx`.

## Platform Requirements

**Development:**
- PHP 8.4 + Composer (`composer.json`, `README.md`).
- Node + pnpm (`package.json`, `README.md`).
- External services typically required while running the app:
  - Meilisearch (`config/scout.php`; `README.md` prerequisites).
  - aria2 RPC (`config/services.php` `aria2`; `README.md` prerequisites; local daemon config `aria2.conf`).
- One-command DX: `composer dev` runs `php artisan serve`, `queue:listen`, `pail`, and `bun run dev` via `bunx concurrently` (`composer.json` scripts).

**Production:**
- Containerized deployment via FrankenPHP + Caddy (`docker/Dockerfile`, `docker/Caddyfile`) with separate targets `app`, `queue`, `scheduler` (`docker-compose.yml`).
- GitHub Actions builds and publishes images to GHCR (`.github/workflows/release.yml`).

---

*Stack analysis: 2026-02-24*
