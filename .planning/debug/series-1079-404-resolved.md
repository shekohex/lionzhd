---
status: resolved
trigger: "Investigate issue: series-1079-404\n\nSummary: GET /series/1079 crashes because `SeriesController::show` calls `dtoOrFail()` on a failed Saloon response. Upstream series detail request is returning 404, and the app should handle that gracefully instead of throwing."
created: 2026-04-01T20:29:43+00:00
updated: 2026-04-01T21:03:00+00:00
---

## Current Focus

hypothesis: The code fix is complete locally; remaining validation is human confirmation in the real browser workflow/environment.
test: Have a human verify missing movie and series detail routes now show the custom Inertia not-found page instead of a Laravel 404 page or crash.
expecting: Visiting missing detail routes should consistently show the new React 404 UI for both initial page loads and in-app navigation.
next_action: Wait for human verification feedback.

## Symptoms

expected: Visiting `/series/1079` should render the series detail page when data exists, or fail gracefully with app-level handling when upstream detail is missing/unavailable.
actual: Request crashes with `LogicException: Unable to create data transfer object as the response has failed.` from `Saloon\Http\Response->dtoOrFail()` in `app/Http/Controllers/Series/SeriesController.php:238`.
errors: Upstream request for `GetSeriesInfoRequest` returns HTTP 404, wrapped as `Illuminate\Http\Client\RequestException`, then `Saloon\Exceptions\Request\Statuses\NotFoundException`, then `LogicException` from `dtoOrFail()`.
reproduction: Hit `GET /series/1079` as an authenticated user.
started: Reported from production logs on 2026-04-01. Treat as current regression/bug to investigate.

## Eliminated

## Evidence

- timestamp: 2026-04-01T20:30:24+00:00
  checked: app/Http/Controllers/Series/SeriesController.php
  found: show() sends GetSeriesInfoRequest and immediately calls dtoOrFail() at line 238 with no failed-response guard.
  implication: Any upstream non-2xx response will throw instead of following app-level error handling.

- timestamp: 2026-04-01T20:30:24+00:00
  checked: app/Http/Integrations/LionzTv/Requests/GetSeriesInfoRequest.php
  found: Request defines DTO creation from raw JSON but does not customize failed-status handling.
  implication: A 404 response remains a failed Saloon response, so dtoOrFail() will raise LogicException unless caller checks first.

- timestamp: 2026-04-01T20:30:52+00:00
  checked: app/Http/Controllers/Series/SeriesDownloadController.php
  found: Series download endpoints also call dtoOrFail() on GetSeriesInfoRequest without guarding failed responses.
  implication: The reported crash is not caused by the request object itself; the immediate bug is specifically the unguarded detail controller path.

- timestamp: 2026-04-01T20:30:52+00:00
  checked: tests/Feature/Controllers/SeriesDetailCategoryContextTest.php
  found: Existing series detail tests cover successful rendering only and fake a 200 GetSeriesInfoRequest response.
  implication: There is currently no regression coverage for upstream 404 handling on the series detail page.

- timestamp: 2026-04-01T20:31:25+00:00
  checked: app/Http/Controllers/VodStream/VodStreamController.php and bootstrap/app.php
  found: Movie detail controller has the same unguarded dtoOrFail pattern, while global exception handling customizes only Inertia 403 responses and leaves 404s to Laravel defaults.
  implication: A controller-level abort(404) is the minimal way to get existing graceful not-found behavior for this series detail bug.

- timestamp: 2026-04-01T20:31:25+00:00
  checked: git status --short
  found: Worktree only contains the new debug session file.
  implication: The fix can stay tightly scoped without risking unrelated local changes.

- timestamp: 2026-04-01T20:32:15+00:00
  checked: php artisan test tests/Feature/Controllers/SeriesDetailCategoryContextTest.php
  found: New regression test failed with HTTP 500 and stack trace showing Saloon NotFoundException followed by LogicException from SeriesController::show line 238.
  implication: Root cause is confirmed: show() reaches dtoOrFail() on an upstream 404 instead of translating it into not-found handling.

- timestamp: 2026-04-01T20:33:22+00:00
  checked: php artisan test tests/Feature/Controllers/SeriesDetailCategoryContextTest.php after controller patch
  found: All 3 tests passed, including the new case asserting upstream missing series detail returns 404.
  implication: The controller guard fixes the crash and preserves existing successful series detail behavior in targeted coverage.

- timestamp: 2026-04-01T20:36:00+00:00
  checked: human verification checkpoint response
  found: Requested behavior changed from series-only crash handling to shared movie + series upstream-404 handling with a custom Inertia/React not-found UI instead of Laravel's default 404 page.
  implication: The prior controller-only series fix is insufficient; investigation must expand to movie detail and global 404 rendering.

- timestamp: 2026-04-01T20:38:30+00:00
  checked: app/Http/Controllers/VodStream/VodStreamController.php and app/Http/Integrations/LionzTv/Requests/GetVodInfoRequest.php
  found: VodStreamController::show still calls dtoOrFail() directly on GetVodInfoRequest with no failed-response guard, and the request object does not translate 404s.
  implication: Upstream missing movie details can fail through the same mechanism as series details.

- timestamp: 2026-04-01T20:38:30+00:00
  checked: bootstrap/app.php and resources/js/pages/errors/*
  found: Exception rendering provides a custom Inertia page only for 403 responses; there is no existing React 404 page component.
  implication: abort(404) currently falls back to Laravel's default not-found output unless exception rendering is extended.

- timestamp: 2026-04-01T20:38:30+00:00
  checked: tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php
  found: Movie detail tests cover successful rendering only and have no regression asserting upstream 404 handling or custom 404 Inertia output.
  implication: Targeted movie regression coverage must be added alongside series coverage for the broader fix.

- timestamp: 2026-04-01T20:42:05+00:00
  checked: php artisan test tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php after strengthening 404 assertions
  found: Series detail now returns plain 404 without an X-Inertia header, and movie detail still crashes with a 500 from VodStreamController::show dtoOrFail().
  implication: Both halves of the broader hypothesis are confirmed: the movie controller still needs a 404 guard, and app-level 404 exception rendering must be extended to Inertia.

- timestamp: 2026-04-01T20:45:20+00:00
  checked: php artisan test tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php after controller + exception + page patch
  found: All 6 targeted tests passed; both upstream-missing movie and series detail requests now return HTTP 404 with X-Inertia and component errors/not-found, and existing success cases remained green.
  implication: The expanded fix resolves the crash path and custom Inertia 404 behavior for detail requests; remaining verification is optional non-Inertia route behavior.

- timestamp: 2026-04-01T20:48:10+00:00
  checked: php artisan test tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php after adding plain-route assertions
  found: Plain GET responses were already 404 HTML containing the embedded Inertia page payload and custom message, but the tests looked for an unescaped component string instead of the escaped JSON payload.
  implication: Route-level behavior is correct; only the verification assertions need to match the actual HTML payload format.

- timestamp: 2026-04-01T20:50:10+00:00
  checked: php artisan test tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php after final assertion alignment
  found: All 6 tests passed with 34 assertions, covering successful movie/series detail pages plus missing upstream detail for both Inertia and plain HTML requests.
  implication: The local fix is verified for both targeted routes and both response modes; only real workflow confirmation remains.

- timestamp: 2026-04-01T21:03:00+00:00
  checked: php artisan tinker --execute='use App\Models\Series; use App\Http\Integrations\LionzTv\XtreamCodesConnector; use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest; $series = Series::query()->find(1079); $response = app(XtreamCodesConnector::class)->send(new GetSeriesInfoRequest($series->series_id)); dump(["status" => $response->status(), "failed" => $response->failed(), "body" => $response->body()]);'
  found: Real XtreamCodes request for series 1079 returned status 404 with empty body.
  implication: The production failure is caused by a real upstream missing-detail response, and the graceful 404 handling is the correct fix.

## Resolution

root_cause: SeriesController::show and VodStreamController::show assume upstream detail requests always succeed and call dtoOrFail() on upstream 404 responses, while exception rendering does not convert 404s into a custom Inertia page.
fix:
fix: Series and movie detail controllers now abort on upstream 404 before dtoOrFail(), and the exception responder now renders a shared Inertia errors/not-found page for non-JSON 404 responses.
verification:
  Targeted detail feature tests passed: php artisan test tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php
  Covered both movie + series upstream 404s for Inertia requests and plain HTML requests.
  Real XtreamCodes verification passed for series 1079: upstream GetSeriesInfoRequest returned 404.
files_changed:
  - app/Http/Controllers/Series/SeriesController.php
  - app/Http/Controllers/VodStream/VodStreamController.php
  - bootstrap/app.php
  - resources/js/pages/errors/not-found.tsx
  - tests/Feature/Controllers/SeriesDetailCategoryContextTest.php
  - tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php
