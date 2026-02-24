# External Integrations

**Analysis Date:** 2026-02-24

## APIs & External Services

**IPTV provider (Xtream Codes API):**
- Xtream Codes-compatible API - primary content source (Series/VOD metadata)
  - Client: Saloon connector `app/Http/Integrations/LionzTv/XtreamCodesConnector.php`
  - Requests: `app/Http/Integrations/LionzTv/Requests/GetSeriesRequest.php`, `app/Http/Integrations/LionzTv/Requests/GetVodStreamsRequest.php`, `app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php`
  - Endpoint shape: `.../player_api.php` with action query params (e.g. `action=get_series` in `app/Http/Integrations/LionzTv/Requests/GetSeriesRequest.php`)
  - Auth: query-string credentials set in `XtreamCodesConnector` via `QueryAuthenticator` (username/password)
  - Config/env:
    - `XTREAM_CODES_API_HOST`, `XTREAM_CODES_API_PORT`, `XTREAM_CODES_API_USER`, `XTREAM_CODES_API_PASS` (`config/services.php` `xtream`)
    - `HTTP_CLIENT_USER_AGENT` (`config/services.php`, `config/saloon.php`)

**Download manager (aria2 JSON-RPC):**
- aria2 RPC - remote download execution/control
  - Client: Saloon connector `app/Http/Integrations/Aria2/JsonRpcConnector.php`
  - Auth: token injected into JSON-RPC `params` by `app/Http/Integrations/Aria2/Auth/Aria2JsonRpcAuthenticator.php`
  - Example request: `app/Http/Integrations/Aria2/Requests/AddUriRequest.php` (JSON-RPC method `addUri`)
  - Service config/env: `ARIA2_RPC_HOST`, `ARIA2_RPC_PORT`, `ARIA2_RPC_SECRET` (`config/services.php` `aria2`)
  - Local daemon config example: `aria2.conf` (RPC enabled; download dir `./storage/app/public/downloads`)

**Search engine (Meilisearch via Scout):**
- Meilisearch - search + indexing
  - Laravel integration: Scout config `config/scout.php`
  - Indexed models: `app/Models/Series.php`, `app/Models/VodStream.php` (use `Laravel\Scout\Searchable`)
  - Env:
    - `SCOUT_DRIVER` (typically `meilisearch`), `SCOUT_QUEUE`
    - `MEILISEARCH_HOST`, `MEILISEARCH_KEY` (`config/scout.php`; also documented in `README.md`)

**Error tracking & performance (Sentry):**
- Sentry - exception capture + tracing
  - Laravel integration: `bootstrap/app.php` (`Sentry\Laravel\Integration::handles(...)`)
  - Config: `config/sentry.php`
  - Scheduled monitor: `routes/console.php` uses `->sentryMonitor()` on scheduled job
  - Env (common): `SENTRY_DSN` / `SENTRY_LARAVEL_DSN`, `SENTRY_RELEASE`, `SENTRY_ENVIRONMENT`, `SENTRY_TRACES_SAMPLE_RATE` (`config/sentry.php`)

**Notifications / chat ops (Slack):**
- Slack notifications config - `config/services.php` (`slack.notifications.*`)
  - Env: `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL`
- Slack logging channel (webhook) - `config/logging.php` `channels.slack`
  - Env: `LOG_SLACK_WEBHOOK_URL`, `LOG_SLACK_USERNAME`, `LOG_SLACK_EMOJI`

**Email providers (optional transports):**
- Postmark - `config/services.php` `postmark.token` → `POSTMARK_TOKEN`
- AWS SES - `config/services.php` `ses.*` uses `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`
- Resend - `config/services.php` `resend.key` → `RESEND_KEY`
- Mail transports are selected in `config/mail.php` via `MAIL_MAILER` and related `MAIL_*` env vars.

## Data Storage

**Databases:**
- SQLite (default) - `DB_CONNECTION=sqlite` default in `config/database.php` and `.env.example`
  - Default file: `database/database.sqlite` (`config/database.php`)
  - Container runtime file: `/data/database/database.sqlite` (created in `docker/entrypoint.sh`; mounted in `docker-compose.yml` via `./data/database:/data/database`)
- Supported (configured, not necessarily used): MySQL/MariaDB/PostgreSQL/SQL Server (`config/database.php`).

**File Storage:**
- Local filesystem:
  - Private: `storage_path('app/private')` (`config/filesystems.php` `disks.local`)
  - Public: `storage_path('app/public')` (`config/filesystems.php` `disks.public`)
- S3-compatible storage (optional): `config/filesystems.php` `disks.s3`
  - Env: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, plus optional `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT`.

**Caching:**
- Default store: database (`config/cache.php` uses `CACHE_STORE`, default `database`; `.env.example` sets `CACHE_STORE=database`).
- Redis supported: client + connections configured in `config/database.php` (`redis.*`) and `config/cache.php` store `redis`.
  - Env: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_CLIENT`, etc (`.env.example`, `config/database.php`).
- DynamoDB cache supported (optional): `config/cache.php` store `dynamodb` (uses `AWS_*`, `DYNAMODB_CACHE_TABLE`, `DYNAMODB_ENDPOINT`).

## Authentication & Identity

**Auth Provider:**
- Laravel built-in session auth (no external IdP detected)
  - Routes: `routes/auth.php` (register/login/password reset/email verification)
  - Guard/provider config: `config/auth.php` (guard `web`, provider `eloquent` model `App\Models\User`).

## Monitoring & Observability

**Error Tracking:**
- Sentry (`config/sentry.php`, `bootstrap/app.php`).

**In-app dashboards:**
- Telescope: `config/telescope.php` (enabled via `TELESCOPE_ENABLED`).
- Pulse: `config/pulse.php` (enabled via `PULSE_ENABLED`; can ingest via Redis if configured).

**Logs:**
- Laravel/Monolog channels configured in `config/logging.php` (file logs, stderr, Slack webhook, Sentry driver).

## CI/CD & Deployment

**Hosting / runtime packaging:**
- Docker images built from `docker/Dockerfile` and pushed to GitHub Container Registry (GHCR) by `.github/workflows/release.yml`.
- Compose reference deployment: `docker-compose.yml` defines `app`, `queue`, `scheduler` targets.

**CI Pipeline:**
- GitHub Actions:
  - Lint/format: `.github/workflows/lint.yml` (Composer + pnpm, Pint, Prettier, ESLint)
  - Tests: `.github/workflows/tests.yml` (build assets with pnpm, run Pest)

## Environment Configuration

**Required env vars (core app):**
- `APP_KEY`, `APP_URL`, `APP_ENV`, `APP_DEBUG` (`.env.example`).

**Required env vars (core integrations):**
- Xtream Codes API: `XTREAM_CODES_API_HOST`, `XTREAM_CODES_API_PORT`, `XTREAM_CODES_API_USER`, `XTREAM_CODES_API_PASS` (`config/services.php`; also documented in `README.md`).
- Meilisearch: `SCOUT_DRIVER=meilisearch`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` (`config/scout.php`; `README.md`).
- aria2 RPC: `ARIA2_RPC_HOST`, `ARIA2_RPC_PORT`, `ARIA2_RPC_SECRET` (`config/services.php`; `README.md`).

**Optional env vars (common):**
- AWS/S3/SES: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` (`.env.example`, `config/filesystems.php`, `config/services.php`).
- Sentry: `SENTRY_DSN`/`SENTRY_LARAVEL_DSN` (`config/sentry.php`).
- Slack logging: `LOG_SLACK_WEBHOOK_URL` (`config/logging.php`).

**Secrets location:**
- Local/runtime secrets: `.env` (template `.env.example`).
- CI secrets: GitHub Actions uses `${{ secrets.GITHUB_TOKEN }}` for GHCR push (`.github/workflows/release.yml`).

## Webhooks & Callbacks

**Incoming:**
- None detected (no webhook routes found under `routes/`).

**Outgoing:**
- Slack webhook for logging if configured (`config/logging.php` `channels.slack.url`).

---

*Integration audit: 2026-02-24*
