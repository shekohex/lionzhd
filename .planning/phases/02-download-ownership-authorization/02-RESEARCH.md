# Phase 2: Download Ownership & Authorization - Research

**Researched:** 2026-02-25
**Domain:** Laravel 12 multi-user ownership + authorization for downloads (Inertia React UI)
**Confidence:** HIGH

## Summary

The current downloads feature is implemented as a simple `media_download_refs` table keyed by `gid` (aria2), rendered via a single Inertia page (`/downloads`), and mutated via two protected endpoints (`PATCH /downloads/{id}`, `DELETE /downloads/{id}`) that are **admin-only today**. Download creation is also **admin-only today** via `can:server-download` on movie/series “server download” routes.

Phase 2 should be planned as (1) adding **explicit ownership** (`user_id`) to `media_download_refs`, (2) enforcing **member own-only list scoping** in the list query, (3) enforcing **member own-only operations** using `can` middleware with a **model-aware gate** that returns **404** when access should be hidden, and (4) ensuring **new download refs are created with the initiating user_id** in all server-download flows.

The best insertion points are already present: gates are centralized in `app/Providers/AppServiceProvider.php` and routes already use `can:` middleware (`routes/web.php`). The key change is to make `download-operations` (and likely `server-download`) model/role-aware rather than admin-only.

**Primary recommendation:** Add `media_download_refs.user_id` + eager-load owner for admin views; scope member lists by `user_id`; change downloads operation routes to `can:download-operations,model` and implement the gate to `denyAsNotFound()` for member cross-user access.

## Standard Stack

### Core
| Library / Feature | Version (repo) | Purpose | Why Standard |
|---|---:|---|---|
| Laravel Framework | ^12.0 (`composer.json`) | AuthZ via Gates + `can` middleware; Eloquent ownership | Existing app baseline; supports gate responses + status overrides |
| Inertia Laravel adapter | ^2.0 (`composer.json`) | Inertia page responses and XHR navigation | Existing page stack |
| `@inertiajs/react` | ^2.0.9 (`package.json`) | Client navigation + mutations + polling | Existing page stack |
| Spatie Laravel Data | ^4.14 (`composer.json`) | Typed server → client DTOs for downloads | Already used for downloads payload |
| Pest | ^3.8 (`composer.json`) | Feature tests for ownership boundaries | Existing repo test runner |

### Supporting
| Library | Version (repo) | Purpose | When to Use |
|---|---:|---|---|
| Saloon MockClient | present (tests) | Fake aria2 + XtreamCodes connectors | For controller/operation tests without network |
| `nuqs` | ^2.4.3 (`package.json`) | Query-string state (filters) | Admin owner filters + “My downloads” toggle |
| Sonner | ^2.0.3 (`package.json`) | Toasts | Auto-refresh + “item disappeared” feedback |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|---|---|---|
| Gate `download-operations` returning `Response` | Policy class for `MediaDownloadRef` | Policies are fine, but this repo already centralizes these gates in `AppServiceProvider` |
| Gate `denyAsNotFound()` for member cross-user | Manual `abort(404)` checks in controllers | Controller checks are easy to miss and don’t protect future endpoints by default |
| Controller-level list scoping | Eloquent global scope based on `auth()` | Global scopes tied to auth context are harder to reason about and easy to break in jobs/CLI |

**Installation:** no new packages.

## Architecture Patterns

### Existing Codebase Locations (source-of-truth)

**Download persistence (DB):**
- `database/migrations/2025_04_01_171803_create_media_download_refs_table.php` (no owner column today)
- `app/Models/MediaDownloadRef.php` (static constructors `fromVodStream`, `fromSeriesAndEpisode`)

**Download creation (server-download flows):**
- `app/Http/Controllers/VodStream/VodStreamDownloadController.php` (`create`) -> `MediaDownloadRef::fromVodStream(...)->saveOrFail()`
- `app/Http/Controllers/Series/SeriesDownloadController.php` (`create`, `store`) -> `MediaDownloadRef::fromSeriesAndEpisode(...)->saveOrFail()`

**Downloads page (list/read):**
- `app/Http/Controllers/MediaDownloadsController.php@index` -> `MediaDownloadRef::query()->with('media')->orderByDesc(...)->paginate(...)`
- `resources/js/pages/downloads.tsx` (polling + actions)
- `resources/js/components/download-info.tsx` (row UI)

**Download operations (pause/resume/remove/retry/delete):**
- `app/Http/Controllers/MediaDownloadsController.php@edit` (PATCH) and `@destroy` (DELETE)
- Aria2 RPC requests: `app/Http/Integrations/Aria2/Requests/*` (Pause/UnPause/Remove/RemoveDownloadResult)

**Authorization (centralized gates):**
- `app/Providers/AppServiceProvider.php` defines `server-download` and `download-operations` as Admin-only today
- `routes/web.php` applies `middleware('can:server-download')` and `middleware('can:download-operations')`

**Front-end server-download visibility controls (Phase 1 behavior):**
- `resources/js/pages/movies/show.tsx` + `resources/js/pages/series/show.tsx` compute `serverDownloadVisibility` (admin enabled, external disabled, internal hidden)
- `resources/js/components/media-hero-section.tsx` and `resources/js/components/episode-list.tsx` implement enabled/disabled/hidden behavior

### Recommended Project Structure (Phase 2 touchpoints)
```
app/
├── Models/MediaDownloadRef.php           # add owner relationship + helpers
├── Providers/AppServiceProvider.php      # update server-download + download-operations gates
├── Http/Controllers/
│   ├── MediaDownloadsController.php      # list scoping + admin filters
│   ├── VodStream/VodStreamDownloadController.php  # set owner on create
│   └── Series/SeriesDownloadController.php        # set owner on create/store
routes/web.php                            # pass model into can middleware
resources/js/pages/downloads.tsx           # member operations + admin filter UX
resources/js/pages/movies/show.tsx         # allow internal member server-download
resources/js/pages/series/show.tsx         # allow internal member server-download
tests/Feature/Downloads/*                  # new ownership regression tests
```

### Pattern 1: Model-aware `can` middleware for download operations
**What:** Keep download authorization centralized, but make it model-aware and pass the bound model to the `can:` middleware.

**Best insertion point:** `routes/web.php` downloads routes.

**Example:**
```php
// routes/web.php
Route::patch('{model}', 'edit')
    ->whereNumber('model')
    ->middleware('can:download-operations,model')
    ->name('downloads.edit');
```
Source: Laravel docs (via middleware): https://laravel.com/docs/12.x/authorization#via-middleware

### Pattern 2: Gate responses that return 404 for hidden resources
**What:** For member cross-user access, return a 404 without leaking existence using `Response::denyAsNotFound()`.

**Best insertion point:** `app/Providers/AppServiceProvider.php` where gates already live.

**Example:**
```php
use Apprüň\Models\MediaDownloadRef;
use App\Enums\UserRole;
use App\Enums\UserSubtype;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

Gate::define('download-operations', static function (User $user, MediaDownloadRef $download): Response {
    if ($user->role === UserRole::Admin) {
        return Response::allow();
    }

    if ($user->role !== UserRole::Member) {
        return Response::deny('Forbidden');
    }

    if ($user->subtype === UserSubtype::External) {
        return Response::deny('External accounts cannot perform download operations. Use Direct Download.');
    }

    return $download->user_id === $user->id
        ? Response::allow()
        : Response::denyAsNotFound();
});
```
Source: Laravel docs (gate responses + 404): https://laravel.com/docs/12.x/authorization#gate-responses

### Pattern 3: Ownership scoping in list queries (not global scopes)
**What:** Apply member scoping in the list query (`WHERE user_id = current_user`) so list endpoints never leak cross-user rows.

**Best insertion point:** `MediaDownloadsController@index`.

**Example:**
```php
$user = $request->user();

$downloads = MediaDownloadRef::query()
    ->when($user->role === UserRole::Member, fn ($q) => $q->where('user_id', $user->id))
    ->with('media')
    ->orderByDesc('created_at')
    ->paginate(10)
    ->withQueryString();
```
Source (repo evidence): `app/Http/Controllers/MediaDownloadsController.php@index` currently builds this query without scoping.

### Anti-Patterns to Avoid
- **UI-only ownership enforcement:** must be server-enforced (query scoping + `can` middleware).
- **Gate without model argument:** will reintroduce “member can operate on any download” if later loosened.
- **Cross-user dedupe leaks:** avoid logic that detects other users’ downloads and redirects members to those GIDs.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---|---|---|---|
| Authorization framework | custom “isAdmin / isOwner” checks everywhere | Laravel Gates + `can` middleware | Auditable, centralized, supports deny messages + 404 hides (`denyAsNotFound`) |
| 404-for-unauthorized | controller-level ad-hoc `abort(404)` | Gate `Response::denyAsNotFound()` | Ensures consistent behavior across endpoints | 
| Filter/query state persistence | custom URL parsing | existing `nuqs` query state + `withQueryString()` paginator | Prevents “lost filters/page after action” regressions |

## Common Pitfalls

### Pitfall 1: Forgetting to pass the model to `can` middleware
**What goes wrong:** You define a model-aware gate but routes still use `can:download-operations` without the model → gate never receives download context.
**How to avoid:** Update routes to `can:download-operations,model` (or `->can('download-operations', 'model')`).
**Warning signs:** Members can hit `/downloads/{id}` operations regardless of ownership.

### Pitfall 2: Member download creation blocked (or silently redirected)
**What goes wrong:** `server-download` gate remains admin-only while Phase 2 expects owned downloads created by members (at least Internal).
**How to avoid:** Align `server-download` gate with Phase 2 policy and update UI visibility rules in `movies/show.tsx` and `series/show.tsx`.
**Warning signs:** Internal members never create `media_download_refs` rows; server-download controls remain hidden.

### Pitfall 3: Cross-user “already active” logic leaks or breaks UX
**What goes wrong:** `GetActiveDownloads` is currently global (no user scoping). After list scoping, a member can be redirected to `/downloads?gid=<someone-else>` but the list won’t include it.
**How to avoid:** Make “already active” checks ownership-aware (member-scoped), or explicitly handle the “active but owned by someone else” case without redirecting to an invisible row.
**Warning signs:** Member clicks Download → redirected to Downloads page with empty list + confusing success banner.

### Pitfall 4: Retry flow loses admin filters/page context
**What goes wrong:** `MediaDownloadsController@edit` currently redirects to `movies.download` / `series.download.single` on retry, which resets `/downloads` query state.
**How to avoid:** For admin UX, ensure retry can return back to the same `/downloads?...` context, or preserve context through redirect params.
**Warning signs:** After retry, admin loses owner filter chips / search selection / page number.

### Pitfall 5: Migration/backfill ambiguity for existing rows
**What goes wrong:** Adding a non-null `user_id` fails migration or leaves existing downloads orphaned.
**How to avoid:** Add `user_id` nullable, backfill (e.g., to current admin) or accept null-but-admin-visible, then enforce non-null when safe.
**Warning signs:** Production migration fails; old downloads disappear for everyone.

## Code Examples

### Download operation route protection (repo-aligned)
```php
// routes/web.php
Route::controller(MediaDownloadsController::class)->prefix('downloads')->group(static function (): void {
    Route::get('/', 'index')->name('downloads');

    Route::patch('{model}', 'edit')
        ->whereNumber('model')
        ->middleware('can:download-operations,model')
        ->name('downloads.edit');

    Route::delete('{model}', 'destroy')
        ->whereNumber('model')
        ->middleware('can:download-operations,model')
        ->name('downloads.destroy');
});
```
Source: repo `routes/web.php` (downloads group) + Laravel docs: https://laravel.com/docs/12.x/authorization#via-middleware

### 404 for member cross-user access via gate response
```php
// app/Providers/AppServiceProvider.php
Gate::define('download-operations', static function (User $user, MediaDownloadRef $download): Response {
    // ...
    return Response::denyAsNotFound();
});
```
Source: Laravel docs (denyAsNotFound): https://laravel.com/docs/12.x/authorization#gate-responses

### Test hook: boundary test that does NOT hit aria2
```php
// Pest feature test idea: if gate deniesAsNotFound, middleware returns 404 before controller.
$member = User::factory()->memberInternal()->create();
$otherUser = User::factory()->memberInternal()->create();

$download = MediaDownloadRef::query()->create([
    'gid' => 'gid-1',
    'media_id' => 1001,
    'media_type' => VodStream::class,
    'downloadable_id' => 1001,
    'user_id' => $otherUser->id,
]);

$this->actingAs($member)
    ->patch(route('downloads.edit', ['model' => $download->id]), ['action' => 'pause'])
    ->assertNotFound();
```
Source: repo patterns in `tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php`.

## State of the Art

| Old Approach (current repo) | Current Approach (Phase 2 target) | Impact |
|---|---|---|
| `media_download_refs` has no owner | `media_download_refs.user_id` defines owner | Enables member scoping + admin cross-user visibility |
| `download-operations` gate is Admin-only (no model) | model-aware gate: Admin-any, Member-own-only, 404 for cross-user | Eliminates cross-user interference and leakage |
| Internal members can’t server-download (UI hidden, gate admin-only) | Internal members can server-download (owned) while External remains restricted | Meets DOWN-04 and practical multi-user downloads |

## Open Questions

1. **Should External members ever have server downloads / operations?**
   - What we know: ACCS-05 + Phase 1 enforced External = direct-link only (server-download blocked). Phase 2 decisions didn’t override this.
   - Recommendation: keep External blocked from server-download and download operations (403 with message), but still allow viewing their own download rows if they can exist.

2. **How to handle “already active download” when owned by another user?**
   - What we know: `GetActiveDownloads` is global today and drives redirect-to-download highlight.
   - Recommendation: scope “already active” checks per-owner for members to avoid cross-user leakage and broken redirects; document/accept file collision risk if duplicates are allowed.

3. **“Details view” requirement for ownership metadata**
   - What we know: current UI has only list rows (no dedicated download details page).
   - Recommendation: treat row expansion/modal as “details” if adding a new page is too large; keep owner visible there for admin.

## Sources

### Primary (HIGH confidence)
- Repo evidence (key files):
  - `database/migrations/2025_04_01_171803_create_media_download_refs_table.php`
  - `app/Models/MediaDownloadRef.php`
  - `app/Http/Controllers/MediaDownloadsController.php`
  - `app/Http/Controllers/VodStream/VodStreamDownloadController.php`
  - `app/Http/Controllers/Series/SeriesDownloadController.php`
  - `routes/web.php`
  - `app/Providers/AppServiceProvider.php`
  - `resources/js/pages/downloads.tsx`
- Laravel 12.x docs:
  - Authorization (gates, responses, 404 hiding): https://laravel.com/docs/12.x/authorization
  - Routing (route model binding customization): https://laravel.com/docs/12.x/routing#route-model-binding

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH (repo-verified versions)
- Architecture: HIGH (repo patterns + Laravel official docs)
- Pitfalls: MEDIUM (some depend on product policy choices like dedupe semantics)

**Valid until:** 2026-03-25 (30 days)
