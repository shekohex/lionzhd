# Direct Download Links — Feature Plan

This document specifies the “Direct Download Links” feature for Lionz IPTV Downloader. It is a hand‑off plan for the development team and aligns with CLAUDE.md conventions (Laravel 12, Inertia v2, Pest).

## Summary
- Provide users with a way to download content directly in their browsers or external download managers.
- Use a signed, time‑limited wrapper URL that 302‑redirects to the upstream provider URL (Xtream Codes).
- Keep Inertia v2 flows (no JSON API). Support a text file of signed links for batch series downloads.

## Goals
- Add “Direct Download” for movies and for single series episodes.
- Add “Get Direct Links” (text file) for series batch selection.
- Keep feature behind a toggle; minimal logging; no HEAD validation.

## Non‑Goals
- Proxying or streaming through our server.
- Hiding provider credentials at the final destination (they are exposed by the provider’s URL after 302; we only hide them in our app URLs).
- Movies batch multi‑select (series‑only batch for now).

## Final Decisions (from PM/Stakeholder)
- Tokenized wrapper: use Laravel signed routes (temporary signed URLs) and store remote URL in cache keyed by token.
- Inertia v2 only: no JSON endpoints; model routes/UI after existing patterns.
- Batch format: downloadable `.txt` file containing signed app URLs (not raw provider URLs).
- No HEAD requests.
- Any authenticated user can generate direct links (no extra roles).
- Logging: minimal, to normal Laravel logs; do not log full remote URLs.
- Link lifetime: 4 hours.
- UX: split button is preferred (primary: server download; secondary: direct download).
- Resolver route must be publicly accessible (no auth) so external download managers can use the signed link.
- Invalid/expired signed links return 404.

## Feature Flag
- Config: `features.direct_download_links` (ENV `FEATURE_DIRECT_DOWNLOAD_LINKS=false`).
- All public entry routes return 404 when disabled.

## Backend Design

### Actions
- `app/Actions/CreateSignedDirectLink.php`
  - Input: `VodInformation|Episode`.
  - Uses `CreateXtreamcodesDownloadUrl` to generate the upstream URL.
  - Generates `token` (ULID/UUID).
  - Stores `{token => remoteUrl}` in cache at `direct:link:{token}` with TTL 4h.
  - Returns `URL::temporarySignedRoute('direct.resolve', now()->addHours(4), ['token' => $token])`.
  - Logs: user id (if available), content type/id, token (no remote URL).

- `app/Actions/BatchCreateSignedDirectLinks.php`
  - Input: `Episode[]`.
  - Calls `CreateSignedDirectLink` for each; returns array/collection of signed URLs.

### Controllers & Routes
All routes remain under the existing `auth`+`verified` group except the resolver, which is public and signed-only.

- Movies (single direct):
  - Route: `GET /movies/{model}/direct` → name: `movies.direct`.
  - Controller: `VodStreamDownloadController@direct` (new method).
  - Behavior: guard by feature flag; fetch VOD info; call `CreateSignedDirectLink`; `return redirect()->to($signedUrl)`.

- Series (single direct):
  - Route: `GET /series/{model}/{season}/{episode}/direct` → name: `series.direct.single`.
  - Controller: `SeriesDownloadController@direct` (new method).
  - Behavior: guard by feature flag; fetch series info; pick episode; call `CreateSignedDirectLink`; redirect to signed URL.

- Series (batch direct, text file):
  - Route: `GET /series/{model}/direct.txt` → name: `series.direct.batch`.
  - Controller: `SeriesDownloadController@batchDirectTxt` (new method).
  - Query: `selected[]=season-episodeIndex` (episodeIndex is current zero‑based index used in batch POST).
  - Behavior: produce `text/plain` attachment with one signed URL per line.

- Resolver (public signed route):
  - Route: `GET /dl/{token}` → name: `direct.resolve`.
  - Controller: `DirectDownloadController@show` (new controller).
  - Middleware: `signed` only (no auth), so external download managers can fetch links.
  - Behavior: lookup `direct:link:{token}` in cache; if miss/expired → 404; else `redirect()->away($remoteUrl)`.

### Caching
- Key: `direct:link:{token}` → value: upstream URL.
- TTL: 4 hours.
- Multiple uses permitted within TTL.

### Logging
- Generation: `info` log with user id, model type/id, token, TTL.
- Resolve: `info` log with token and result (hit/miss). Do not log upstream URL.

## Frontend (Inertia v2)

### Movies (`resources/js/pages/movies/show.tsx`)
- Replace Download with split button:
  - Primary: “Download” → existing server download (`movies.download`).
  - Secondary: “Direct Download” → `router.visit(route('movies.direct', { model }))`.
- Optional first-use tooltip explaining final URL visibility at provider.

### Series (`resources/js/pages/series/show.tsx`, `resources/js/components/episode-list.tsx`)
- Per-episode: add “Direct Download” action → `router.visit(route('series.direct.single', { model, season, episode }))`.
- Batch: add “Get Direct Links” button.
  - Build query `selected[]=season-episodeIndex`.
  - Trigger a normal navigation for text download (not XHR) so browser downloads `links.txt`:
    - `window.location.assign(route('series.direct.batch', { model, selected }))`.

## Validation & Errors
- Not found series/episode → back with error (consistent with current controllers).
- Feature disabled → 404 (uniform response across endpoints).
- Resolver invalid/expired signature or cache miss → 404.

## Security & Privacy
- App URLs hide provider credentials.
- Final redirect exposes provider URL to the client (by design, acceptable).
- Resolver is public but signed and time‑limited; links are safe to share with download managers.
- No rate limiting initially; can add `ThrottleRequests` later if needed.

## Testing (Pest)
- Unit
  - `CreateSignedDirectLinkTest`: caches remote, generates signed URL, TTL ≈ 4h.
  - `BatchCreateSignedDirectLinksTest`.
- Feature
  - Movies direct: enabled → 302 to `/dl/{token}` then 302 away; disabled → 404.
  - Series direct single: happy path + episode not found.
  - Series batch text: returns `text/plain` with N signed URLs; invalid selection returns error.
  - Resolver: valid signed + cache hit → 302 away; invalid/expired → 404; cache miss → 404.
- Follow CLAUDE.md: run minimal filtered tests locally; then optional full suite.

## Rollout
1) Ship behind `FEATURE_DIRECT_DOWNLOAD_LINKS=false`.
2) Enable on staging; verify flows with common download managers (302 follow + text list import).
3) Enable in production; monitor logs for errors/abuse.

## Estimates
- Backend (actions, routes, resolver, logging): 1–2 days.
- Frontend (split buttons, per‑episode, batch text flow): 1–2 days.
- Tests + polish: 0.5–1 day.

## Task Breakdown

### Backend
- [ ] Config flag + wiring.
- [ ] `CreateSignedDirectLink` action.
- [ ] `BatchCreateSignedDirectLinks` action.
- [ ] `VodStreamDownloadController@direct` method.
- [ ] `SeriesDownloadController@direct` and `@batchDirectTxt` methods.
- [ ] `DirectDownloadController` with `show()` and signed middleware.
- [ ] Routes in `routes/web.php` (names: `movies.direct`, `series.direct.single`, `series.direct.batch`, `direct.resolve`).
- [ ] Cache keys + TTL handling.
- [ ] Logging.
- [ ] Pest tests (unit + feature).

### Frontend
- [ ] Movies split button (primary server download, secondary direct download).
- [ ] Series episode “Direct Download” action.
- [ ] Series batch “Get Direct Links” button and text download flow.
- [ ] Optional first‑use tooltip/copy tweaks.

## Acceptance Criteria
- When feature is enabled:
  - Users can direct‑download a movie via a signed link that expires in 4 hours.
  - Users can direct‑download any episode via a signed link that expires in 4 hours.
  - Users can export a `.txt` file of signed links for selected episodes.
  - Resolver works without authentication and follows 302 to provider URL.
- When feature is disabled: all related endpoints return 404.
- No raw provider URLs are logged or displayed in app URLs.

## Risks & Mitigations
- Upstream URL visibility at destination → expected/accepted.
- Link sharing externally → mitigated by signed + TTL.
- Provider rate limits or failures → surfaced as user‑facing errors; no HEAD prechecks to reduce overhead.

## Out of Scope (v1)
- Proxying/streaming through our server.
- Additional role‑based restrictions.
- HEAD/metadata validation.
- Movies multi‑select batch.

