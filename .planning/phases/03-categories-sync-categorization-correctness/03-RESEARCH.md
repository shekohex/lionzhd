# Phase 3: Categories Sync & Categorization Correctness - Research

**Researched:** 2026-02-25
**Domain:** Laravel 12 + Saloon Xtream integration; category persistence + rerunnable sync correctness; Inertia (React) admin UX
**Confidence:** HIGH (repo-verified architecture + constraints); MEDIUM on Xtream category payload shape (handled defensively)

## Summary

This codebase already has an admin-triggered, queued “media library sync” (`/settings/syncmedia` -> `RefreshMediaContents` -> `SyncMedia`) that fetches **Series** and **VOD streams** from Xtream and upserts them into `series` / `vod_streams`. Those media tables already store `category_id` as **nullable string** (Xtream provider category id), but there is **no categories table** and **no category sync** yet.

Phase 03 should be planned as: (1) add a `categories` table keyed by provider category id (shared across VOD+Series) plus two persisted system Uncategorized categories (per-type), (2) add `category_sync_runs` history persistence, and (3) implement a deterministic, rerunnable sync that fetches **VOD+Series categories only** (no Live), applies changes with **partial success** semantics, includes an **empty-source destructive safeguard**, and maintains content correctness via **auto-move to per-type Uncategorized** + **auto-remap on reappearance** using a `previous_category_id` field on media.

**Primary recommendation:** model category identity as `categories.provider_id` (string unique), keep `series.category_id` / `vod_streams.category_id` as provider id strings (not FK int), and implement category sync as `SyncCategories` action + `SyncCategories` queued job + admin Settings UI + history page.

---

## 1) Existing architecture touchpoints

### Backend sync patterns (source-of-truth)

- **Admin settings route + controller pattern**:
  - Routes: `routes/settings.php` (all admin settings behind `Route::middleware('can:admin')`)
  - Example controller: `app/Http/Controllers/Settings/SyncMediaController.php` (`edit` renders, `update` dispatches job, redirects back with `success`)
  - Example feature test: `tests/Feature/Controllers/SyncMediaControllerTest.php`

- **Queued job pattern**:
  - `app/Jobs/RefreshMediaContents.php` runs `SyncMedia::run()`.
  - NOTE: it implements `ShouldBeUnique`, which *prevents duplicate dispatch* while a job is pending/running.

- **Action pattern**:
  - `app/Concerns/AsAction.php` provides `::run()` -> `handle()` -> `__invoke()`.
  - Example action: `app/Actions/SyncMedia.php`.

- **Xtream integration boundary (Saloon)**:
  - Connector: `app/Http/Integrations/LionzTv/XtreamCodesConnector.php`.
  - Request classes: `app/Http/Integrations/LionzTv/Requests/*`.
  - Existing list requests (`GetSeriesRequest`, `GetVodStreamsRequest`) are defensive: “if json is not array -> return []”, “filter non-arrays”.
  - Tests mock connector with `MockClient`: `tests/Feature/Jobs/RefreshMediaContentsTest.php`.

### Frontend/admin patterns

- **Settings sidebar nav**: `resources/js/layouts/settings/layout.tsx` filters `adminOnly` items using `auth.user.role`.
- **Settings action pages**:
  - `resources/js/pages/settings/syncmedia.tsx` uses `useForm().patch(route('syncmedia.update'))` and uses `sonner` toast loading.
- **Flash contract**:
  - `app/Http/Middleware/HandleInertiaRequests.php` shares only `flash.success` and `flash.warning`.
  - `resources/js/components/app-shell.tsx` auto-toasts `flash.success`/`flash.warning` and also toasts every validation error.

### Existing data contract mismatch to fix during Phase 03

- DB schema stores media `category_id` as **string**:
  - `database/migrations/2025_03_05_143249_create_series_table.php`
  - `database/migrations/2025_03_05_154156_create_vod_streams_table.php`
- But `VodStreamData::$category_id` is typed as `?int` and TS output is `category_id?: number`:
  - `app/Data/VodStreamData.php`
  - `resources/js/types/generated.d.ts`

Planning must include aligning DTO typing (`?string`) and regenerating TS types (`php artisan typescript:transform`).

---

## 2) Data model implications and migration strategy

### What exists today

- `vod_streams.category_id` (nullable string provider category id)
- `series.category_id` (nullable string provider category id)
- No `categories` persistence.

### What Phase 03 needs to add

#### A) `categories` table (provider-id identity + per-scope flags)

Prescriptive schema (fits decisions):

- `id` (pk)
- `provider_id` (string, **unique**) — canonical identity (decision)
- `name` (string)
- `in_vod` (bool) — provider says category appears in VOD category list
- `in_series` (bool) — provider says category appears in Series category list
- `is_system` (bool) — protects Uncategorized from deletion
- timestamps

System Uncategorized records (persisted, per-type; decision):

- provider_id `__uncategorized_vod__` with `in_vod=true`, `in_series=false`, `is_system=true`
- provider_id `__uncategorized_series__` with `in_vod=false`, `in_series=true`, `is_system=true`

Recommended indices:

- Unique index on `provider_id`.
- Index on (`in_vod`), (`in_series`) for later filtering.

#### B) `category_sync_runs` table (admin-visible history; decision)

Prescriptive schema:

- `id` (pk)
- `requested_by_user_id` (nullable FK to users, `nullOnDelete`)
- `status` (string enum backing: running/success/success_with_warnings/failed)
- `started_at`, `finished_at` (nullable)
- `summary` (json) — counts + key metrics
- `top_issues` (json) — small array of issue strings/objects
- timestamps

#### C) Media “previous category” for auto-remap

Add `previous_category_id` (nullable string) to **both**:

- `vod_streams.previous_category_id`
- `series.previous_category_id`

Semantics:

- When moving a row to Uncategorized, set `previous_category_id` = old `category_id` (if any), then set `category_id` to the correct uncategorized provider id.
- When remapping back, set `category_id` to `previous_category_id` and clear `previous_category_id`.

### Migration strategy (safe sequencing)

1) Create `categories` + insert the 2 system Uncategorized rows (idempotent via `insertOrIgnore` / `upsert`).
2) Create `category_sync_runs`.
3) Add `previous_category_id` columns.
4) Align DTOs and regenerate TS types.

Do **not** change existing `category_id` column types (already string) in this phase.

---

## 3) Sync algorithm risks and guardrails (partial success, empty-source confirmation)

### Required semantics (from Phase 03 decisions)

- Combined sync for **VOD + Series** together.
- Live excluded (achieved by only calling the Xtream category endpoints for VOD+Series).
- Partial success allowed: if one source fails, apply the other source and record success-with-warnings.
- Provider category id is canonical identity.
- Missing categories are hard removed (only when it is safe to be destructive).
- Content invalid/unmapped category ids are moved to the correct per-type Uncategorized.
- If removed category reappears later, auto-remap back.
- Empty-source safeguard: if provider returns **0 categories** for a source, warn and require explicit confirmation before applying anything destructive for that source.

### Guardrails to prevent correctness regressions

1) **Two-level empty-source protection (controller + action)**
   - Controller preflight should detect empty lists and refuse to queue unless explicit confirmation is provided.
   - Action must independently refuse destructive changes for an empty list unless force flags are present (defense-in-depth).

2) **Per-source isolation**
   - Fetch VOD + Series lists independently (try/catch per request) so one failure doesn’t block the other.
   - Apply changes per-source only when that source succeeded and is allowed (not blocked by empty confirmation).

3) **Only perform global hard-delete cleanup on complete, confirmed data**
   - Hard-deleting categories based on “missing in union” must only run when **both** source lists are successfully fetched and not blocked by empty confirmation.
   - Otherwise, treat run as partial and keep existing categories to avoid accidental mass deletion.

4) **Uncategorized is always valid**
   - When computing “valid category ids for VOD/Series”, include the corresponding system Uncategorized provider id; otherwise uncategorized rows will be repeatedly treated as invalid.

5) **Don’t overwrite `previous_category_id` once set**
   - Move-to-uncategorized query should avoid changing `previous_category_id` for rows already in uncategorized (prevents losing the original prior category).

6) **Avoid `WHERE IN (...)` scaling traps on SQLite** (MEDIUM)
   - Production default DB is SQLite (`config/database.php`). Large `whereIn/whereNotIn` lists can hit SQLite bind limits.
   - Prefer `whereNotExists` subqueries against the `categories` table when marking invalid content, or chunk provider id lists.

### Suggested (repo-aligned) algorithm outline

- Create a `CategorySyncRun` row early (status=running, started_at now).
- Fetch categories:
  - VOD: `action=get_vod_categories`
  - Series: `action=get_series_categories`
- Normalize rows defensively (skip invalid ids; warn):
  - provider_id = `(string)($row['category_id'] ?? $row['id'] ?? '')`
  - name = `(string)($row['category_name'] ?? $row['name'] ?? '')`
- Upsert categories by `provider_id`:
  - Update `name` when changed.
  - Set `in_vod` / `in_series` based on which successful source(s) included the id.
- When safe (both sources succeeded + empty allowed if applicable):
  - Hard delete non-system categories missing from union.
  - Move content to per-type Uncategorized when `category_id` is null/empty or maps to no valid category in that scope.
  - Remap back from uncategorized when `previous_category_id` now exists as a valid category for that scope.
- Finalize run with status:
  - `success` if no issues
  - `success_with_warnings` if any non-fatal issues (partial success, invalid rows skipped, empty-source requires confirmation but run otherwise applied for other side)
  - `failed` if both sources failed or fatal exception

---

## 4) UI/admin flow touchpoints

### Required admin entry points (consistent with existing Settings UX)

- Add settings routes under `routes/settings.php` within `can:admin` group (mirrors syncmedia):
  - GET `/settings/synccategories` -> `SyncCategoriesController@edit`
  - PATCH `/settings/synccategories` -> `SyncCategoriesController@update` (combined action)
  - GET `/settings/synccategories/history` -> `CategorySyncRunsController@index`

### Empty-source confirmation UX (must be explicit)

Repo constraints:

- Only `flash.success` and `flash.warning` are shared globally.
- Validation errors are auto-toasted as errors.
- Settings pages commonly use `window.confirm(...)` for confirmation (see `resources/js/pages/settings/users.tsx`).

Prescriptive approach that fits current plumbing:

1) On PATCH `/settings/synccategories`, the controller performs a **preflight fetch** of both category lists.
2) If VOD or Series returns 0 and the request is not forced:
   - redirect back with `warning` explaining which source(s) were empty and that confirmation is required.
   - return a structured hint for the UI (needs one of the following planned changes):
     - **Preferred:** extend Inertia shared flash to include `flash.meta` / `flash.confirm` (object), OR
     - use validation errors with predictable keys (e.g., `empty_vod`, `empty_series`) and accept they toast as errors.
3) UI shows a confirmation prompt and, if confirmed, re-submits PATCH with `forceEmptyVod=1` / `forceEmptySeries=1`.
4) On success, controller dispatches queued job and redirects back with `success`.

### History UX

- Render `settings/synccategories-history` with recent `CategorySyncRun` rows (paginate). Keep display minimal: started_at, status badge, summary counts, top issues.
- “Stay on same page after sync completes” is already satisfied by redirect-back + toast; there is no progress UI in this codebase today.

---

## 5) Test strategy and highest-value regression cases

### Testing constraints in this repo

- Pest is used (`composer.json`), tests are under `tests/Feature` and `tests/Unit`.
- In tests, queue is `sync` and cache is `array` (see `phpunit.xml`).
- Xtream integration tests use Saloon `MockClient` by binding `XtreamCodesConnector` in the container (`RefreshMediaContentsTest`).
- There are **no factories** for `VodStream` / `Series` today (only `UserFactory`). Tests will need direct `::query()->create([...])` with required fields.

### Highest-value regression cases (must-have)

Action/job correctness (feature tests, RefreshDatabase):

1) **Provider-id identity + rename update**
   - Seed category provider_id X name Old.
   - Mock Xtream returns X name New.
   - Assert updated name and no duplicate row.

2) **Hard removal + content moved to Uncategorized + previous_category_id set**
   - Seed VodStream/Series with category_id = missing.
   - Mock categories union not containing missing.
   - Assert category is removed (if existed) and content is moved to correct uncategorized provider id and previous_category_id records missing.

3) **Reappearance remaps back**
   - After (2), mock categories includes missing again.
   - Assert content remaps back and previous_category_id cleared.

4) **Partial success**
   - Mock one source fetch fails (500/throw) and the other succeeds.
   - Assert successful-side updates apply.
   - Assert destructive union cleanup did not execute.
   - Assert run status `success_with_warnings` and issue recorded.

5) **Empty-source safeguard**
   - Mock VOD categories returns empty array.
   - Run without force flag: assert VOD destructive operations are skipped and run records issue.
   - Run with force flag: assert apply path runs.

Controller UX (feature tests):

- PATCH without force when preflight empty should:
  - not dispatch job
  - return warning/confirm signal
- PATCH with force should dispatch.

### Recommended mocking pattern (repo-aligned)

- Mirror `tests/Feature/Jobs/RefreshMediaContentsTest.php` container binding:
  - bind `XtreamCodesConfig` and `XtreamCodesConnector`.
  - attach `MockClient` mapping `GetVodCategoriesRequest::class` / `GetSeriesCategoriesRequest::class`.

---

## 6) Recommended plan decomposition (waves)

This phase is best planned as 3–4 small, independently verifiable plans, matching existing repo conventions:

### Wave 1 — Xtream category integration boundary

- Add Saloon requests:
  - `GetVodCategoriesRequest` (action=`get_vod_categories`)
  - `GetSeriesCategoriesRequest` (action=`get_series_categories`)
- Unit tests for defensive parsing.

### Wave 2 — Persistence foundations

- Migrations:
  - `categories` table + 2 system uncategorized rows
  - `category_sync_runs` table
  - add `previous_category_id` to `vod_streams` and `series`
- Models:
  - `Category`, `CategorySyncRun`, `CategorySyncRunStatus` enum
- Fix DTO typing mismatch (`category_id` as `?string`) and regenerate TS types.

### Wave 3 — Core sync logic + job serialization

- `SyncCategories` action implementing correctness rules + run recording.
- `SyncCategories` job:
  - must allow multiple dispatches
  - must serialize execution via cache lock + `release()` (do not use `ShouldBeUnique` if it drops queued reruns).
- Feature tests for rerun safety, partial success, empty-source safeguard, remap behavior.

### Wave 4 — Admin UI + history

- Settings routes/controllers + Inertia pages.
- Empty-source confirmation UX.
- History list UX with pagination.

---

## Standard Stack

### Core
| Library / Feature | Version (repo) | Purpose | Why Standard here |
|---|---:|---|---|
| Laravel Framework | ^12.0 (`composer.json`) | Jobs, Eloquent, migrations, cache locks | Existing app baseline |
| Saloon | ^3.11 (`composer.json`) | Xtream HTTP integration | Existing integration boundary |
| Saloon MockClient | present | Deterministic Xtream tests | Used in existing job tests |
| Inertia Laravel + React | ^2.0 / ^2.0.9 | Admin settings pages | Existing UI stack |
| Pest | ^3.8 | Regression tests | Existing test runner |
| Spatie Laravel Data + TS transformer | ^4.14 / ^2.5 | Server DTOs + TS types | Existing typing pipeline |

### Supporting
| Library / Feature | Version (repo) | Purpose | When to use |
|---|---:|---|---|
| Cache database driver | default (`config/cache.php`) | Locks + small state | Use `Cache::lock` for sync serialization |
| Sonner | ^2.0.3 | Toasts for success/warning/errors | Use for sync queued + warnings |

**Installation:** no new packages expected.

## Architecture Patterns

### Pattern: Settings controller -> dispatch job -> action

- Existing pattern: `SyncMediaController@update` -> `RefreshMediaContents::dispatch()` -> `SyncMedia::run()`.
- Category sync should mirror this shape for consistency.

### Pattern: Serialize execution with cache locks (not unique jobs)

The phase decision requires “if a sync is already running, queue the next run” (no dropping). That is incompatible with `ShouldBeUnique` semantics.

Use cache locks for serialization (Laravel cache atomic locks): https://laravel.com/docs/12.x/cache#atomic-locks

### Pattern: Defensive Saloon request parsing

- Mirror `GetSeriesRequest` / `GetVodStreamsRequest` parsing style for category list requests.

## Don't Hand-Roll

| Problem | Don’t build | Use instead | Why |
|---|---|---|---|
| Xtream HTTP client | custom Guzzle wrapper | Saloon requests + connector | Existing integration stack |
| Sync serialization | ad-hoc DB flags | `Cache::lock` + `release()` | Correct under retries; fits DB cache driver |
| Upsert-by-identity | manual select+update loops | Eloquent `upsert` | Deterministic; fewer queries (see docs: https://laravel.com/docs/12.x/eloquent#upserts) |
| History storage | log-only | `category_sync_runs` table | Required admin history UX |

## Common Pitfalls

### Pitfall: Using `ShouldBeUnique` for category sync
**What goes wrong:** duplicate admin clicks are silently dropped instead of queued.
**How to avoid:** allow multiple dispatches; serialize execution with locks.
**Source:** Laravel unique jobs docs describe non-dispatch while unique lock exists (https://laravel.com/docs/12.x/queues#unique-jobs).

### Pitfall: Empty-source treated as authoritative
**What goes wrong:** a transient provider glitch returning `[]` triggers hard deletes + mass uncategorization.
**How to avoid:** block destructive operations unless explicitly forced for that source.

### Pitfall: DTO/category_id type mismatch
**What goes wrong:** frontend sees `category_id` as `number` but DB and provider ids are strings; later filtering breaks.
**How to avoid:** switch DTO typing to `?string` and regenerate `resources/js/types/generated.d.ts`.

### Pitfall: Overwriting `previous_category_id`
**What goes wrong:** repeated uncategorization overwrites history; remap cannot restore correct prior category.
**How to avoid:** only set `previous_category_id` when transitioning from a non-uncategorized category.

## Code Examples (repo-aligned)

### Example: Saloon category list request shape (mirrors existing list requests)
```php
// app/Http/Integrations/LionzTv/Requests/GetVodStreamsRequest.php (pattern)
public function resolveEndpoint(): string
{
    return '/player_api.php';
}

protected function defaultQuery(): array
{
    return [
        'action' => 'get_vod_streams',
    ];
}
```

### Example: Cache lock pattern for serialization
```php
// Laravel docs: https://laravel.com/docs/12.x/cache#atomic-locks
$lock = Cache::lock('sync:categories', 60 * 30);

if (! $lock->get()) {
    return $this->release(15);
}

try {
    SyncCategories::run(...);
} finally {
    $lock->release();
}
```

### Example: Mock Xtream connector with Saloon MockClient
```php
// tests/Feature/Jobs/RefreshMediaContentsTest.php (pattern)
app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
    $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

    return $connector->withMockClient(new MockClient([
        GetSeriesRequest::class => MockResponse::make([], 200),
        GetVodStreamsRequest::class => MockResponse::make([], 200),
    ]));
});
```

## Open Questions

1) **Xtream category payload key names** (MEDIUM)
   - What we know: request actions likely `get_vod_categories` / `get_series_categories`; response is an array of objects.
   - What’s unclear: exact keys (`category_id`/`category_name` vs `id`/`name`).
   - Recommendation: implement normalization with fallbacks and warn on missing keys.

2) **How to return structured “empty source(s)” confirmation state to the UI** (MEDIUM)
   - What we know: shared flash currently only has `success` and `warning`.
   - Recommendation: plan an explicit mechanism (extend shared flash to include structured meta OR use predictable validation error keys).

## Sources

### Primary (HIGH confidence)
- Repo touchpoints:
  - `app/Actions/SyncMedia.php`
  - `app/Jobs/RefreshMediaContents.php`
  - `app/Http/Controllers/Settings/SyncMediaController.php`
  - `routes/settings.php`
  - `app/Http/Integrations/LionzTv/XtreamCodesConnector.php`
  - `app/Http/Integrations/LionzTv/Requests/GetSeriesRequest.php`
  - `app/Http/Integrations/LionzTv/Requests/GetVodStreamsRequest.php`
  - `tests/Feature/Jobs/RefreshMediaContentsTest.php`
  - `tests/Feature/Controllers/SyncMediaControllerTest.php`
  - `app/Http/Middleware/HandleInertiaRequests.php`
  - `resources/js/components/app-shell.tsx`
  - `database/migrations/2025_03_05_143249_create_series_table.php`
  - `database/migrations/2025_03_05_154156_create_vod_streams_table.php`
  - `app/Data/VodStreamData.php` + `resources/js/types/generated.d.ts`
- Phase constraints:
  - `.planning/phases/03-categories-sync-categorization-correctness/03-CONTEXT.md`

### Official docs (verification)
- Laravel 12 Cache atomic locks: https://laravel.com/docs/12.x/cache#atomic-locks
- Laravel 12 Queue unique jobs: https://laravel.com/docs/12.x/queues#unique-jobs
- Laravel 12 Eloquent upserts: https://laravel.com/docs/12.x/eloquent#upserts

## Metadata

**Confidence breakdown:**
- Existing touchpoints: HIGH (repo)
- Data model + migrations: HIGH (repo + Laravel standard)
- Sync algorithm guardrails: HIGH (directly from Phase 03 decisions)
- UI touchpoints: HIGH (repo)
- Xtream category payload shape: MEDIUM (not verified from provider docs; handled defensively)

**Valid until:** 2026-03-25 (30 days)
