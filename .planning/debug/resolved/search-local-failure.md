---
status: resolved
trigger: "Start a debugging session for the local `/search` failure in this repo."
created: 2026-03-22T16:05:01+00:00
updated: 2026-03-23T00:47:06+00:00
---

## Current Focus

hypothesis: Confirmed: `/search` fails because Scout is configured for Meilisearch but no Meilisearch server is listening at `MEILISEARCH_HOST`; `hljs` is only emitted by the exception renderer page after the backend exception.
test: Diagnosis complete.
expecting: N/A
next_action: report root cause and recommended local fix path

## Symptoms

expected: Searching for `superman` on `/search` should render search results.
actual: Searching for `superman` renders an error page showing missing `laravel-exceptions-renderer` components.
errors: `Meilisearch\\Exceptions\\CommunicationException`; `cURL error 7: Failed to connect to localhost port 7700`; browser noise `ReferenceError: hljs is not defined` on `/search`.
reproduction: Open `http://127.0.0.1:8000/search`, search for `superman`, observe server 500 and error page.
started: unknown

## Eliminated

- hypothesis: The `hljs` browser error is the primary cause of the `/search` failure.
  evidence: Server-side logs and direct Scout invocation fail first with `Meilisearch\\Exceptions\\CommunicationException`; `hljs` appears only in cached exception-renderer views, not app search code.
  timestamp: 2026-03-22T16:09:31+00:00

## Evidence

- timestamp: 2026-03-22T16:06:02+00:00
  checked: `app/Http/Controllers/SearchController.php`, `app/Actions/SearchMovies.php`, `app/Actions/SearchSeries.php`
  found: `/search` always calls `VodStream::search($query)` and/or `Series::search($query)` through Laravel Scout once a query is present.
  implication: Any live search request depends on the configured Scout driver before the page can render results.

- timestamp: 2026-03-22T16:06:02+00:00
  checked: `config/scout.php` and `.env`
  found: Scout driver is `meilisearch`; host is `http://localhost:7700`.
  implication: Local `/search` requests are expected to call a Meilisearch instance on port 7700.

- timestamp: 2026-03-22T16:06:54+00:00
  checked: running PTY logs and `curl http://127.0.0.1:7700/health`
  found: `/search` logs `Meilisearch\\Exceptions\\CommunicationException`; health check to port 7700 fails with connection refused.
  implication: The active local blocker is unavailable Meilisearch, not application query logic.

- timestamp: 2026-03-22T16:07:22+00:00
  checked: `composer.json` and `README.md`
  found: `composer dev` starts Laravel server, queue, pail, and Vite only; README lists Meilisearch as a prerequisite service.
  implication: The current dev stack does not automatically start Meilisearch, so local search breaks unless the service is started separately or Scout driver is changed.

- timestamp: 2026-03-22T16:08:11+00:00
  checked: `php artisan test tests/Feature/Controllers/SearchControllerTest.php`
  found: Search controller tests pass because they explicitly set `config()->set('scout.driver', 'database')`.
  implication: Search page logic is valid; tests avoid the local Meilisearch dependency and therefore do not catch the missing-service failure.

- timestamp: 2026-03-22T16:08:42+00:00
  checked: `php artisan tinker` with current config vs overridden `scout.driver=database`
  found: Default config reproduces the same `CommunicationException`; forcing `database` driver returns results successfully.
  implication: Meilisearch availability/config is the primary blocker, and switching to the database driver is a safe local fallback.

- timestamp: 2026-03-22T16:09:05+00:00
  checked: codebase search for `hljs`
  found: `hljs` appears only in cached exception renderer views under `storage/framework/views`, alongside `laravel-exceptions-renderer` components.
  implication: The browser `hljs` error is incidental noise from rendering the exception page after the backend failure, not the cause of the `/search` 500.

## Resolution

root_cause: Local `/search` uses Laravel Scout with `SCOUT_DRIVER=meilisearch`, but no Meilisearch instance is reachable at `MEILISEARCH_HOST=http://localhost:7700`, so the first search query throws `Meilisearch\\Exceptions\\CommunicationException` before Inertia can render results.
fix: No code fix applied. Safe local remediation is either start/configure Meilisearch on port 7700 and ensure indexes are populated, or use `SCOUT_DRIVER=database` for local development/tests when external search is not running.
verification: Verified by reproducing the exception with current config, confirming port 7700 is unavailable, and confirming the same search path succeeds when Scout is forced to `database`.
files_changed: []
