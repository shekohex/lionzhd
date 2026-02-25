# Phase 1: Access Control - Research

**Researched:** 2026-02-25
**Domain:** Laravel 12 authorization (roles/subtypes) + Inertia React UX enforcement
**Confidence:** HIGH

## Summary

This phase is best planned as a small, explicit role/subtype system implemented with **database columns + PHP enums**, enforced via **Laravel Gates / `can` middleware**, surfaced to the UI via **Inertia shared props**, and backed by **Inertia-compatible 403 rendering** so unauthorized navigation returns an in-app 403 page (not a raw Blade error page).

Key domain constraints from CONTEXT.md: first registered user is Admin + initial super-admin; all other users default to Member+External; super-admin is transferable and protected; Members must be blocked from admin-only areas; External Members are direct-link-only and must be blocked from server-download actions and scheduling.

**Primary recommendation:** Implement access control via Laravel gates (returning `Response::deny(...)` messages), route-level `->can(...)` middleware for pages, and a global Inertia 403 handler in `bootstrap/app.php` using `Exceptions::respond`.

## Standard Stack

### Core
| Library / Feature | Version (repo) | Purpose | Why Standard |
|---|---:|---|---|
| Laravel Framework | ^12.0 (`composer.json`) | AuthN/AuthZ primitives (Gates, middleware, exceptions) | Built-in, testable, integrates with route middleware and exceptions |
| Inertia Laravel adapter | ^2.0 (`composer.json`) | Inertia SSR + shared props | Official adapter; supports shared props via middleware |
| `@inertiajs/react` | ^2.0.9 (`package.json`) | Client navigation + form submissions | Official client adapter |
| Laravel Gates / `can` middleware | built-in | Route/page authorization + messages | Standard Laravel authorization mechanism |
| Inertia error handling via `Exceptions::respond` | Inertia v2 docs | Render 403 page in app shell | Official pattern for production-grade error pages |

### Supporting
| Library | Version (repo) | Purpose | When to Use |
|---|---:|---|---|
| `Illuminate\Auth\Access\Response` | built-in | Deny with human message | Use for “Admin-only / External-only” messaging on 403 page |
| Spatie TypeScript Transformer | ^2.5 (`composer.json`) | Emit TS unions for PHP enums | Use `#[TypeScript]` on `UserRole` / `UserSubtype` |
| Radix/shadcn UI primitives (Dialog, Checkbox, etc.) | present in `resources/js/components/ui/*` | Confirm dialogs + help modal | Required by UI confirmations + header help modal |
| Sonner | ^2.0.3 (`package.json`) | Toast messaging | Use for disabled-action guidance (External restrictions) |
| Pest | ^3.8 (`composer.json`) | Feature tests for access boundaries | Standard repo test runner |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|---|---|---|
| DB columns + enums | `spatie/laravel-permission` | Overkill for 2 roles + 1 subtype; adds tables/caching/complexity not needed in this phase |
| Gate + route `->can(...)` | Per-controller `if (...) abort(403)` everywhere | Duplicates logic; harder to audit coverage and error messaging consistency |

**Installation:** no new packages recommended for Phase 1.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Enums/
│   ├── UserRole.php
│   └── UserSubtype.php
├── Actions/
│   └── Users/
│       ├── BootstrapFirstUserAsAdmin.php
│       ├── ChangeUserRole.php
│       ├── ChangeMemberSubtype.php
│       ├── TransferSuperAdmin.php
│       └── DeleteUser.php
├── Policies/ (optional)
├── Http/
│   ├── Controllers/
│   │   └── Settings/
│   │       └── UsersController.php
│   └── Middleware/
│       └── HandleInertiaRequests.php (share role/subtype + abilities)
resources/js/
├── pages/
│   ├── errors/forbidden.tsx
│   └── settings/users.tsx
└── components/
    └── access/
        ├── role-badge.tsx
        └── external-help-dialog.tsx
```

### Pattern 1: Use Gates that return messages (not booleans)
**What:** Define abilities like `admin-area`, `manage-users`, `system-settings`, `server-download`, etc. as gates returning `Illuminate\Auth\Access\Response` so denials carry a **user-facing reason**.

**When to use:** Every authorization check that should produce explicit UX (“Admin-only”, “External members can’t …”).

**Example:**
```php
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

Gate::define('edit-settings', function (User $user) {
    return $user->isAdmin
        ? Response::allow()
        : Response::deny('You must be an administrator.');
});
```
Source: https://laravel.com/docs/12.x/authorization#gate-responses

### Pattern 2: Enforce page access at the route level (`->can(...)` / `can:` middleware)
**What:** Attach `can` middleware to admin-only page routes so unauthorized users receive a 403 before controllers execute.

**When to use:** Inertia pages (user management, system settings, sync controls) and any admin-only endpoints.

**Example:**
```php
use App\Models\Post;

Route::put('/post/{post}', function (Post $post) {
    // ...
})->middleware('can:update,post');
```
Source: https://laravel.com/docs/12.x/authorization#via-middleware

### Pattern 3: Render 403 as an Inertia page via `Exceptions::respond`
**What:** Use a global exception response customization to return an Inertia page for 403 (and optionally 404/500), keeping the error inside the app shell.

**When to use:** Unauthorized navigation to admin-only routes must show an in-app 403 page.

**Example:**
```php
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
        if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
            return Inertia::render('ErrorPage', ['status' => $response->getStatusCode()])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        }

        return $response;
    });
})
```
Source: https://inertiajs.com/error-handling

**Phase-specific guidance:** for 403, pass `reason` from `$exception->getMessage()` (especially when using Gate `Response::deny('Admin-only')`).

### Pattern 4: Share auth + permissions in `HandleInertiaRequests`
**What:** Add `role`, `subtype`, `is_super_admin` and/or `auth.can.*` booleans to shared props so navigation/controls can be hidden/disabled without duplicating backend logic.

**When to use:** Sidebar items, Settings sidebar filtering, header badge, disabling download controls.

**Example:**
```php
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request)
    {
        return array_merge(parent::share($request), [
            'auth.user' => fn () => $request->user()
                ? $request->user()->only('id', 'name', 'email')
                : null,
        ]);
    }
}
```
Source: https://inertiajs.com/shared-data

### Anti-Patterns to Avoid
- **Scattered `if ($user->role === ...)` checks across controllers:** define gates once; call `Gate::authorize(...)` or use route `->can(...)`.
- **UI-only enforcement:** every restricted action must be blocked server-side too.
- **Relying on `user_id === 1` as “admin”:** breaks with seeded data / restored DB; replace with role/subtype.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---|---|---|---|
| Authorization layer | Custom permission engine | Laravel Gates + `can` middleware | Standard, testable, supports messages (`Response::deny`) |
| Inertia 403 rendering | Ad-hoc controller returns | `Exceptions::respond` returning `Inertia::render(...)` | Centralized, consistent across all routes |
| Confirmations/modals | bespoke modal state machine | Existing `resources/js/components/ui/dialog.tsx` | Already standardized UI primitives |

**Key insight:** Access control correctness is mostly about **coverage + consistency**; Gates + route middleware make it auditable.

## Common Pitfalls

### Pitfall 1: Race condition when assigning “first user = Admin”
**What goes wrong:** Two concurrent registrations both assign Admin.
**Why it happens:** Checking `User::count() === 0` before insert is not atomic.
**How to avoid:** Make the decision based on deterministic post-insert state (e.g., the first inserted row ID) or protect with a transactional lock/advisory lock.
**Warning signs:** Multiple admins created on day 1 without explicit promotion.

### Pitfall 2: “Zero-admin” state via self-delete
**What goes wrong:** Current `ProfileController::destroy` allows deleting the last Admin (and/or super-admin), violating Phase 1 constraints.
**How to avoid:** Enforce invariants in a single action (delete/demote) and use it from both admin UI and profile deletion.
**Warning signs:** After deleting an admin, nobody can access system settings or user management.

### Pitfall 3: External restrictions only on buttons
**What goes wrong:** External Members can still hit download endpoints (GET/POST/PATCH) and start server downloads.
**How to avoid:** Gate server-download endpoints (`movies.download`, `series.download.*`) and download operation endpoints (`downloads.edit`, `downloads.destroy`) server-side.
**Warning signs:** External user creates `MediaDownloadRef` rows or triggers aria2 calls.

### Pitfall 4: 403 shows default Blade error page (not app shell)
**What goes wrong:** Direct navigation to an admin route returns Laravel’s default HTML error page.
**How to avoid:** Implement Inertia error handling via `Exceptions::respond` for 403.
**Warning signs:** Missing sidebar/header, inconsistent “Back/Home” actions.

## Code Examples

### Gate denial with explicit message
```php
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

Gate::define('edit-settings', function (User $user) {
    return $user->isAdmin
        ? Response::allow()
        : Response::deny('Admin-only. Contact the super-admin.');
});
```
Source: https://laravel.com/docs/12.x/authorization#gate-responses

### Customizing middleware registration in Laravel 12 (`bootstrap/app.php`)
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'subscribed' => EnsureUserIsSubscribed::class,
    ]);
})
```
Source: https://laravel.com/docs/12.x/middleware#middleware-aliases

### Inertia production error page rendering (403 included)
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
        if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
            return Inertia::render('ErrorPage', ['status' => $response->getStatusCode()])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        }

        return $response;
    });
})
```
Source: https://inertiajs.com/error-handling

## State of the Art

| Old Approach (current repo) | Current Approach (Phase 1) | When Changed | Impact |
|---|---|---|---|
| `viewTelescope` / `viewPulse` gate checks `user->id === 1` | Gate checks based on `UserRole::Admin` (and possibly `is_super_admin` for special actions) | Phase 1 | Multi-admin support + consistent access control |
| Default HTML error pages for 403 | Inertia-rendered 403 page in app shell | Phase 1 | Meets UX requirement for direct URL navigation |

## Open Questions

1. **Download operations scope before Phase 2 ownership**
   - What we know: External Members must be read-only and blocked from server-download actions; ownership scoping is Phase 2.
   - What's unclear: Whether Internal Members should be allowed to pause/cancel/retry/remove downloads before ownership is implemented.
   - Recommendation: In Phase 1, make `downloads.edit`/`downloads.destroy` **Admin-only** (to avoid cross-user interference) while allowing Internal Members to start server downloads; revisit once Phase 2 adds ownership.

2. **Subtype on Admin → Member demotion**
   - What we know: Subtype “does not apply” to Admin accounts; new users default External.
   - What's unclear: On demotion, should subtype default to External, or restore a prior stored subtype.
   - Recommendation: Keep `subtype` persisted even for Admin rows but ignored by logic/UI while role=Admin; on demotion it becomes active again.

## Sources

### Primary (HIGH confidence)
- Repo evidence:
  - `/composer.json` (Laravel 12, inertia-laravel 2.x)
  - `/package.json` (Inertia React 2.x, React 19)
  - `/routes/settings.php` (system settings routes that must be admin-only)
  - `/app/Providers/TelescopeServiceProvider.php` + `/app/Providers/PulseServiceProvider.php` (current id==1 gating)
  - `/app/Http/Middleware/HandleInertiaRequests.php` (shared props entry point)
  - `/app/Http/Controllers/Settings/ProfileController.php` (self-delete path that can create zero-admin state)

### Primary (HIGH confidence, official docs)
- Laravel 12 Authorization (gates, responses, middleware): https://laravel.com/docs/12.x/authorization
- Laravel 12 Middleware registration in `bootstrap/app.php`: https://laravel.com/docs/12.x/middleware
- Laravel 12 Error handling (`withExceptions`, `respond`): https://laravel.com/docs/12.x/errors
- Laravel 12 Pulse authorization (`viewPulse` gate): https://laravel.com/docs/12.x/pulse
- Laravel 12 Telescope authorization (`viewTelescope` gate): https://laravel.com/docs/12.x/telescope
- Inertia Error handling (Laravel `Exceptions::respond` example): https://inertiajs.com/error-handling
- Inertia Shared data (middleware `share` pattern): https://inertiajs.com/shared-data

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - derived from repo `composer.json`/`package.json` and official docs.
- Architecture: HIGH - aligned with existing repo patterns (Inertia middleware + settings routes) and official Laravel/Inertia guidance.
- Pitfalls: MEDIUM - some are repo-verified (profile deletion), others are concurrency/coverage risks typical to this domain.

**Research date:** 2026-02-25
**Valid until:** 2026-03-25
