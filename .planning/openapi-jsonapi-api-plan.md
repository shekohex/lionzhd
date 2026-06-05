# JSON:API OpenAPI External API — Unified Plan

> **Epic**: Expose lionz.tv functionality over a versioned, spec-compliant JSON:API with auto-generated OpenAPI documentation and Sanctum API-token authentication for external automations.
> **Target**: lionz.tv v1.1.0
> **Laravel**: Upgrade 12.53 → 13.x (required for native `JsonApiResource`)

---

## Epic Overview

Build a complete `/api/v1/` JSON:API surface that mirrors all existing web functionality. External consumers authenticate with Sanctum tokens and receive JSON:API-compliant responses. Documentation is auto-generated via Scramble OpenAPI. Users manage tokens via a new Settings page.

---

## Technology Stack

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| Framework | Laravel 13.x | Native `JsonApiResource` (stable since 13.0); sparse fieldsets; compound documents. See [Laravel 13 JSON:API Resources docs](https://laravel.com/docs/13.x/eloquent-resources#json-api-resources) |
| API Auth | Laravel Sanctum | Token lifecycle, scopes, hashing, revocation — all native |
| OpenAPI Docs | `dedoc/scramble` | Zero-annotation static analysis; understands `JsonApiResource`, FormRequest, enums |
| Data Layer | `spatie/laravel-data` (existing) | Reuse all existing DTOs in API resources |
| Rate Limiting | Laravel `RateLimiter` (native) | Per-user/token throttling |
| Frontend | React + Inertia (existing) | New `/settings/tokens` page |

### Packages
```bash
composer require laravel/framework:^13.0 --update-with-dependencies
composer require laravel/sanctum
composer require dedoc/scramble
```

---

## Reference Links

- [Laravel 13.x Release Notes](https://laravel.com/docs/13.x/releases)
- [Laravel 13 JSON:API Resources](https://laravel.com/docs/13.x/eloquent-resources#json-api-resources)
- [Laravel Sanctum Docs](https://laravel.com/docs/13.x/sanctum)
- [Dedoc Scramble Docs](https://scramble.dedoc.co/)
- [JSON:API Specification](https://jsonapi.org/)

---

## Tickets

---

### 🔧 INFRA-1: Upgrade Laravel 12.53 → 13.x
**Type**: Technical Task
**Priority**: Blocker
**Points**: 3

**Description**
Upgrade the Laravel framework to v13.x to unlock native `JsonApiResource` support.

**Acceptance Criteria**
- [x] `composer require laravel/framework:^13.0 --update-with-dependencies` succeeds
- [x] `php artisan --version` reports 13.x
- [x] All existing Pest tests pass (`./vendor/bin/pest`)
- [x] No Inertia/UI regressions
- [x] `php artisan make:resource --json-api` works

**Sub-tasks**
1. Run upgrade command and resolve any dependency conflicts
2. Run full test suite
3. Smoke-test critical web flows (login, search, download)
4. Document any breaking changes in upgrade notes

---

### 🔧 INFRA-2: Install & Configure Sanctum
**Type**: Technical Task
**Priority**: Blocker
**Points**: 2

**Description**
Install Laravel Sanctum for API token authentication.

**Acceptance Criteria**
- [x] `composer require laravel/sanctum` installed
- [x] Config published (`vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`)
- [x] Migration run (`personal_access_tokens` table exists)
- [x] Sanctum middleware registered in `bootstrap/app.php` or kernel
- [x] `config/sanctum.php` configured for stateless API usage
- [x] `User` model uses `HasApiTokens` trait

---

### 🔧 INFRA-3: Install & Configure Scramble (OpenAPI)
**Type**: Technical Task
**Priority**: Blocker
**Points**: 2

**Description**
Install `dedoc/scramble` for automatic OpenAPI 3.1 spec generation.

**Acceptance Criteria**
- [x] `composer require dedoc/scramble` installed
- [x] Config published (`vendor:publish --tag="scramble-config"`)
- [x] Scramble routes registered (`/docs/api` and `/docs/api.json` respond)
- [x] Bearer token security scheme configured in OpenAPI spec
- [x] `JsonApiResponseExtension` registered to wrap responses in JSON:API envelope

---

### 🔧 INFRA-4: Bootstrap API Routing & Middleware Pipeline
**Type**: Technical Task
**Priority**: Blocker
**Points**: 2

**Description**
Create the API route file and middleware pipeline for v1.

**Acceptance Criteria**
- [x] `routes/api.php` created and loaded in bootstrap
- [x] All API routes prefixed with `/api/v1/`
- [x] Route group uses `['auth:sanctum', 'throttle:api', 'AcceptJsonApi']`
- [x] `RateLimiter::for('api')` registered (120 req/min per user)
- [x] API route returns 401 for unauthenticated requests
- [x] API route returns JSON:API `Content-Type` header

---

### 🔧 INFRA-5: Create Base JSON:API Resource + FormRequest Patterns
**Type**: Technical Task
**Priority**: Blocker
**Points**: 3

**Description**
Establish the patterns for native `JsonApiResource` classes and API `FormRequest` validation that all endpoints will follow.

**Acceptance Criteria**
- [x] Example `MovieResource` extends native `JsonApiResource`
- [x] `toAttributes()` delegates to `$model->getData()->toArray()`
- [x] `toId()` and `toType()` implemented correctly
- [x] `JsonApiResourceCollection` pattern defined for paginated lists
- [x] Base API `FormRequest` with JSON:API error formatting established
- [x] One test endpoint working end-to-end (`GET /api/v1/movies`)

---

---

### ⚙️ API-1: Discovery, Search & Categories Endpoints
**Type**: Feature
**Priority**: High
**Points**: 5

**User Story**
> As an external API consumer, I want to discover featured media, search the catalog, and browse categories so that I can find content programmatically.

**Endpoints**
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/discover` | Featured/discover media (top 15 movies + series) |
| POST | `/api/v1/search` | Full-text search (Scout/Meilisearch) |
| POST | `/api/v1/search/lightweight` | Lightweight search |
| GET | `/api/v1/categories` | List all categories (`?for=movie\|series` filter) |
| GET | `/api/v1/categories/{id}` | Category details |

**Notes**
- `GET /categories` returns categories the user is allowed to see (respects hidden/ignored preferences)
- Movie/Series index endpoints already accept `?category=` query param for filtering
- Categories endpoint powers the browse sidebar equivalent for API consumers

**Acceptance Criteria**
- [x] All endpoints return proper `JsonApiResource` / `JsonApiResourceCollection`
- [x] Search supports pagination, filters, and sorting
- [x] Categories respect user preferences (hidden/ignored)
- [x] Scramble generates correct OpenAPI schemas for request/response
- [x] Pest feature tests cover happy path + validation errors

---

### ⚙️ API-2: Movies (VOD Streams) Endpoints
**Type**: Feature
**Priority**: High
**Points**: 5

**User Story**
> As an external API consumer, I want to browse movies, view extended details, manage my watchlist, trigger downloads, and clear cache so that I can automate my media workflow.

**Endpoints**
| Method | Path | Description | Ability |
|--------|------|-------------|---------|
| GET | `/api/v1/movies` | Paginated list + filters (`?category=`) | `read` |
| GET | `/api/v1/movies/{id}` | Movie details. Supports `?include=vod-info` for XtreamCodes extended data | `read` |
| POST | `/api/v1/movies/{id}/watchlist` | Add to watchlist | `read` |
| DELETE | `/api/v1/movies/{id}/watchlist` | Remove | `read` |
| POST | `/api/v1/movies/{id}/download` | Queue server download | `server-download` |
| GET | `/api/v1/movies/{id}/direct` | Generate signed direct download URL | `read` |
| DELETE | `/api/v1/movies/{id}/cache` | Clear XtreamCodes cache for this movie | `admin` |

**Notes**
- Detail endpoint reuses `GetVodInfoRequest` to fetch extended info when `?include=vod-info` is requested
- Cache clearing reuses existing `VodStreamCacheController::destroy` logic
- Direct link returns a signed URL that the consumer follows directly (no API resolution needed)

**Acceptance Criteria**
- [x] Reuses existing `VodStreamController` / `VodStreamCacheController` logic
- [x] Watchlist endpoints use existing `AddToWatchlist` / `RemoveFromWatchlist` actions
- [x] Download endpoint respects `can:server-download` gate
- [x] Cache clearing respects `can:admin` gate
- [x] Error responses are JSON:API compliant
- [x] All endpoints tested with Pest

---

### ⚙️ API-3: Series Endpoints
**Type**: Feature
**Priority**: High
**Points**: 5

**User Story**
> As an external API consumer, I want to browse series, view episodes, manage watchlists, download individual episodes or batches, and clear cache so that I can automate series management.

**Endpoints**
| Method | Path | Description | Ability |
|--------|------|-------------|---------|
| GET | `/api/v1/series` | Paginated list + filters (`?category=`) | `read` |
| GET | `/api/v1/series/{id}` | Series details. Supports `?include=episodes` for season/episode data | `read` |
| POST | `/api/v1/series/{id}/watchlist` | Add | `read` |
| DELETE | `/api/v1/series/{id}/watchlist` | Remove | `read` |
| POST | `/api/v1/series/{id}/seasons/{season}/episodes/{episode}/download` | Episode download | `server-download` |
| POST | `/api/v1/series/{id}/download` | Batch download selected episodes | `server-download` |
| GET | `/api/v1/series/{id}/seasons/{season}/episodes/{episode}/direct` | Direct link | `read` |
| POST | `/api/v1/series/{id}/direct.txt` | Batch direct links as plain text | `read` |
| DELETE | `/api/v1/series/{id}/cache` | Clear XtreamCodes cache | `admin` |

**Notes**
- Detail endpoint reuses `GetSeriesInfoRequest` to fetch episode data when `?include=episodes` is requested
- Includes `monitor` relationship when series has active monitoring
- Includes `preset_times`, `backfill_preset_counts`, `run_now_cooldown_seconds` in meta when available
- Cache clearing reuses existing `SeriesCacheController::destroy` logic

**Acceptance Criteria**
- [x] Series detail includes episodes/seasons via `?include=episodes`
- [x] Batch download accepts episode selection payload
- [x] Direct link endpoints return signed URLs
- [x] Cache clearing respects `can:admin` gate
- [x] All endpoints reuse existing Actions/Policies
- [x] Pest tests for all paths

---

### ⚙️ API-4: Watchlist Endpoints
**Type**: Feature
**Priority**: High
**Points**: 3

**User Story**
> As an external API consumer, I want to list, add, and remove items from my watchlist so that I can sync watchlist state with external tools.

**Endpoints**
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/watchlist` | List current user watchlist. Supports `?filter=movies\|series` |
| POST | `/api/v1/watchlist` | Add item (movie or series) |
| DELETE | `/api/v1/watchlist/{id}` | Remove item |

**Acceptance Criteria**
- [x] Watchlist entries include polymorphic relationship to movie/series
- [x] POST validates `media_type` (`movie` or `series`) and `media_id`
- [x] Returns proper JSON:API relationship data
- [x] Pest tests

---

### ⚙️ API-5: Downloads Endpoints
**Type**: Feature
**Priority**: Medium
**Points**: 3

**User Story**
> As an external API consumer, I want to view and control my downloads so that I can monitor and manage server downloads remotely.

**Endpoints**
| Method | Path | Description | Ability |
|--------|------|-------------|---------|
| GET | `/api/v1/downloads` | List active / recent downloads. Admin: `?owners=1,2,3` filter | `read` |
| PATCH | `/api/v1/downloads/{id}` | Pause / resume / retry / remove | `download-operations` |
| DELETE | `/api/v1/downloads/{id}` | Cancel & optionally delete files | `download-operations` |

**Notes**
- Download status from aria2 is merged into response in real-time
- Admin can filter by owner IDs via `?owners=` query param
- PATCH body includes `action` enum: `pause`, `resume`, `retry`, `remove`, `cancel`
- `retry` supports optional `restart_from_zero` flag
- `cancel` supports optional `delete_partial` flag

**Acceptance Criteria**
- [x] List returns `MediaDownloadRefData` via `JsonApiResource` with real-time aria2 status
- [x] Admin sees `ownerOptions` and can filter by `?owners=`
- [x] Update supports all action types with proper validation
- [x] Destroy respects `can:download-operations` with model binding
- [x] Pest tests

---

### ⚙️ API-6: Auto-Episode Monitoring Endpoints
**Type**: Feature
**Priority**: Medium
**Points**: 4

**User Story**
> As an external API consumer with monitoring permissions, I want to configure and trigger series monitoring so that I can automate episode downloads via API.

**Endpoints**
| Method | Path | Description | Ability |
|--------|------|-------------|---------|
| GET | `/api/v1/series/{id}/monitoring` | Get monitoring config | `read` |
| POST | `/api/v1/series/{id}/monitoring` | Start monitoring | `monitoring:admin` |
| PATCH | `/api/v1/series/{id}/monitoring` | Update config | `monitoring:admin` |
| DELETE | `/api/v1/series/{id}/monitoring` | Stop monitoring | `monitoring:admin` |
| POST | `/api/v1/series/{id}/monitoring/run-now` | Trigger immediate scan | `monitoring:admin` |
| POST | `/api/v1/series/{id}/monitoring/backfill` | Backfill past episodes | `monitoring:admin` |

**Acceptance Criteria**
- [x] All endpoints reuse existing monitoring Actions/Controllers
- [x] `monitoring:admin` ability enforced via Sanctum scopes
- [x] Config response includes next run time, preset, paused state, etc.
- [x] Pest tests

---

### ⚙️ API-7: Settings / Admin Endpoints
**Type**: Feature
**Priority**: Medium
**Points**: 5

**User Story**
> As an admin API consumer, I want to manage system settings and users via API so that I can automate server configuration.

**Endpoints**
| Method | Path | Description | Ability |
|--------|------|-------------|---------|
| GET | `/api/v1/settings/aria2` | Get aria2 config | `admin` |
| PATCH | `/api/v1/settings/aria2` | Update aria2 config | `admin` |
| GET | `/api/v1/settings/xtreamcodes` | Get XtreamCodes config | `admin` |
| PATCH | `/api/v1/settings/xtreamcodes` | Update | `admin` |
| GET | `/api/v1/settings/sync-media` | Get sync-media config | `admin` |
| PATCH | `/api/v1/settings/sync-media` | Trigger sync | `admin` |
| GET | `/api/v1/settings/sync-categories` | Get category sync config | `admin` |
| PATCH | `/api/v1/settings/sync-categories` | Trigger sync | `admin` |
| GET | `/api/v1/settings/sync-categories/history` | Run history | `admin` |
| GET | `/api/v1/settings/users` | List users | `admin` |
| PATCH | `/api/v1/settings/users/{id}/subtype` | Change subtype | `admin` |
| PATCH | `/api/v1/settings/users/{id}/role` | Change role | `super-admin` |
| GET | `/api/v1/settings/schedules` | Monitoring schedules overview | `monitoring:admin` |
| PATCH | `/api/v1/settings/schedules/bulk-apply` | Bulk apply preset | `monitoring:admin` |
| PATCH | `/api/v1/settings/schedules/pause` | Pause/resume all auto-episodes | `monitoring:admin` |

**Acceptance Criteria**
- [ ] All endpoints gated by existing `can:admin` / `can:super-admin` policies
- [ ] Config endpoints return typed data via existing Config models
- [ ] Users list supports pagination
- [ ] Sync endpoints trigger existing console commands / jobs
- [ ] Pest tests

---

### ⚙️ API-8: Category Preferences Endpoints
**Type**: Feature
**Priority**: Low
**Points**: 2

**User Story**
> As an external API consumer, I want to update my category preferences so that my discover/search results are personalized.

**Endpoints**
| Method | Path | Description |
|--------|------|-------------|
| PATCH | `/api/v1/preferences/categories/{mediaType}` | Update preferences (hidden/ignored categories) |
| DELETE | `/api/v1/preferences/categories/{mediaType}` | Reset preferences |

**Acceptance Criteria**
- [x] Reuses existing `CategoryPreferenceController` logic
- [x] Validates `mediaType` enum (`movie` or `series`)
- [x] Body accepts array of category IDs with `hidden` / `ignored` flags
- [x] Pest tests

---

### ⚙️ API-9: Token Management Endpoints
**Type**: Feature
**Priority**: High
**Points**: 3

**User Story**
> As a user, I want to create and revoke API tokens so that I can give external tools scoped access to my account.

**Endpoints**
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/me` | Current authenticated user |
| GET | `/api/v1/tokens` | List my tokens (name, abilities, last used — no plain token) |
| POST | `/api/v1/tokens` | Create new token (returns plain token once) |
| DELETE | `/api/v1/tokens/{id}` | Revoke token |

**Acceptance Criteria**
- [x] `POST /tokens` accepts `name` and optional `abilities` array
- [x] Plain token returned only in creation response
- [x] `GET /tokens` shows masked/hashed info only
- [x] Revocation immediately invalidates token
- [x] Token abilities respected by Sanctum middleware
- [x] Pest tests

---

---

### 🎨 UI-1: Settings Page — API Token Management
**Type**: Feature
**Priority**: High
**Points**: 3

**User Story**
> As a user, I want a settings page where I can create, view, and revoke API tokens so that I can manage external tool access without admin help.

**Design**
- New nav item in `SettingsLayout`: "API Tokens" (icon: `KeyRound` from lucide-react)
- Route: `/settings/tokens` (Inertia-rendered)

**Page Components**
- **Token List**: Table showing name, abilities badges, last used, expires at, created at, revoke button
- **Create Form**: Name input + ability checkboxes (`read`, `server-download`, `monitoring:admin`, `download-operations`)
- **Display Once**: Alert/dialog showing plain token with copy-to-clipboard (disappears on refresh)
- **Usage Help**: Collapsible section showing `Authorization: Bearer <token>` example

**Acceptance Criteria**
- [ ] Page accessible at `/settings/tokens`
- [ ] Only authenticated users can access
- [ ] Create form validates name is required
- [ ] Copy-to-clipboard works for generated token
- [ ] Revoke asks for confirmation
- [ ] Mobile responsive

---

---

### 🧪 TEST-1: API Feature Test Suite
**Type**: Technical Task
**Priority**: High
**Points**: 5

**Description**
Write Pest feature tests covering all API endpoints.

**Coverage Requirements**
- [ ] Authentication: 401 without token, 403 with insufficient scope
- [ ] Each endpoint: happy path, validation errors, not found
- [ ] JSON:API structure validation: `data.id`, `data.type`, `data.attributes`
- [ ] Pagination: `links`, `meta` present on collection endpoints
- [ ] Rate limiting: throttled after 120 req/min
- [ ] Policy enforcement: admin-only endpoints reject non-admins
- [ ] Token lifecycle: create, use, revoke, verify revoked token fails
- [ ] `?include=episodes` and `?include=vod-info` relationship inclusion
- [ ] Sparse fieldsets: `?fields[movies]=name,rating` returns only requested fields

---

### 🧪 TEST-2: OpenAPI Spec Validation
**Type**: Technical Task
**Priority**: Medium
**Points**: 2

**Description**
Validate the generated OpenAPI spec is complete and correct.

**Acceptance Criteria**
- [ ] `GET /docs/api.json` returns valid OpenAPI 3.1 (validate with `swagger-parser` or online)
- [ ] All endpoints documented with correct request/response schemas
- [ ] Bearer auth security scheme present
- [ ] JSON:API `Content-Type` documented
- [ ] No missing schemas or broken references

---

---

### 📖 DOC-1: API Developer Documentation
**Type**: Documentation
**Priority**: Medium
**Points**: 2

**Description**
Write consumer-facing documentation for the API.

**Deliverables**
- [ ] `README.md` section: "External API" with base URL, auth header, content-type
- [ ] Quick start: generate token → first request example (curl)
- [ ] Endpoint summary table linking to `/docs/api` for details
- [ ] Error codes reference (400, 401, 403, 404, 422, 429)
- [ ] Rate limiting explanation
- [ ] Sparse fieldsets and relationship inclusion examples

---

### 📖 DOC-2: Internal API Architecture Docs
**Type**: Documentation
**Priority**: Low
**Points**: 1

**Description**
Document internal patterns for future developers.

**Deliverables**
- [ ] `docs/api-architecture.md`: How `JsonApiResource` delegates to laravel-data
- [ ] `docs/api-auth.md`: Sanctum scopes, ability mapping, middleware flow
- [ ] `docs/adding-endpoints.md`: Checklist for adding new API resources

---

---

## What Is Intentionally NOT Exposed

The following web-only functionality is deliberately kept out of the API surface:

| Web Route | Why Not Exposed |
|-----------|-----------------|
| `GET /` (Welcome) | Landing page is UI-only |
| `GET /login`, `POST /login` | API uses Sanctum tokens, not session auth |
| `GET /register`, `POST /register` | Account creation stays web-only |
| `POST /logout` | Token revocation handles API logout |
| `GET /verify-email/*` | Email verification is web flow |
| `GET /forgot-password`, `POST /forgot-password` | Password reset is web flow |
| `GET /reset-password/*` | Password reset is web flow |
| `GET /settings/profile` + `PATCH /settings/profile` | Profile update stays web-only |
| `DELETE /settings/profile` | Account deletion stays web-only |
| `GET /settings/password` + `PUT /settings/password` | Password change stays web-only |
| `GET /dl/{token}` | Signed direct link **resolution** is web-only; API only **generates** signed URLs via `/direct` endpoints |

---

## Sprint / Phase Allocation

### Phase 0: Upgrade & Foundation
| Ticket | Points |
|--------|--------|
| INFRA-1: Laravel 13 Upgrade | 3 |
| INFRA-2: Install & Configure Sanctum | 2 |
| INFRA-3: Install & Configure Scramble | 2 |
| INFRA-4: API Routing & Middleware | 2 |
| INFRA-5: Base Resource Patterns | 3 |
| **Total** | **12** |

### Phase 1: Core Read API + Token UI
| Ticket | Points |
|--------|--------|
| API-1: Discovery, Search & Categories | 5 |
| API-2: Movies | 5 |
| API-3: Series | 5 |
| API-4: Watchlist | 3 |
| UI-1: Settings Tokens Page | 3 |
| **Total** | **21** |

### Phase 2: Write & Admin API
| Ticket | Points |
|--------|--------|
| API-5: Downloads | 3 |
| API-6: Auto-Episode Monitoring | 4 |
| API-7: Settings / Admin | 5 |
| API-8: Category Preferences | 2 |
| API-9: Token Management | 3 |
| **Total** | **17** |

### Phase 3: Quality & Documentation
| Ticket | Points |
|--------|--------|
| TEST-1: API Feature Tests | 5 |
| TEST-2: OpenAPI Validation | 2 |
| DOC-1: Developer Docs | 2 |
| DOC-2: Internal Architecture Docs | 1 |
| **Total** | **10** |

**Grand Total: 60 points**

---

## Risk Register

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Laravel 13 upgrade breaks Inertia SSR | High | Low | Run full test suite; Laravel 13 is minor bump |
| Sanctum token scopes not matching existing gates | Medium | Low | Map scopes 1:1 to gate names; test each gate |
| Scramble misinfers JSON:API envelope | Medium | Medium | Build `JsonApiResponseExtension` early; validate spec |
| Token leaked in logs/frontend | High | Low | Never log plain tokens; show once in UI only |
| Rate limits too aggressive for batch tools | Low | Medium | Make limit configurable; default 120/min |
| Cache clear endpoints misused to DoS XtreamCodes | Medium | Low | Gate behind `admin`; add per-resource rate limit |

---

## Definition of Done (per ticket)

- [ ] Code written and type-safe (PHPStan level 8)
- [ ] Pest tests passing
- [ ] Scramble OpenAPI spec updated (if endpoint changes)
- [ ] No breaking changes to existing web UI
- [ ] Reviewed and approved
