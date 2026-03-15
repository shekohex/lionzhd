---
status: awaiting_human_verify
trigger: "Investigate issue: lionzhd-28\n\n**Summary:** Fix sentry issue LIONZHD-28. Use scientific method. Find root cause and fix it in the codebase."
created: 2026-03-15T00:00:00Z
updated: 2026-03-15T00:55:00Z
---

## Current Focus

hypothesis: Confirmed. SearchMediaData defaulting per_page to 10 hardens every strict-int search callsite; only regression coverage needed expansion for movie/VOD and lightweight payload shapes.
test: Self-verified with focused feature tests for omitted per_page across full-search series, full-search movie/VOD, and lightweight search.
expecting: User confirms the real workflow no longer crashes when per_page is omitted.
next_action: wait for human verification in the real search workflow/environment

## Symptoms

expected: GET /search should render successfully for series searches even when optional pagination/limit inputs are omitted.
actual: production request to /search crashes with a TypeError because App\Actions\SearchSeries::__invoke() receives null for argument #4 $perPage, which is declared int.
errors: TypeError: App\Actions\SearchSeries::__invoke(): Argument #4 ($perPage) must be of type int, null given, called in /lionz/app/Http/Controllers/SearchController.php on line 55.
reproduction: hit https://lionz.0iq.xyz/search with a series search (query: "type:series", page: "1", sortBy: SearchSortby object) in production so code path at app/Http/Controllers/SearchController.php:55 invokes SearchSeries with a null 4th arg.
started: first seen and last seen 2026-03-15T02:47:08Z; new unresolved issue with 2 occurrences.

## Eliminated

- hypothesis: Lightweight search still has a null-per_page production bug after the DTO fix.
  evidence: The dumped lightweight Inertia response returned HTTP 200 with props.filters.per_page = 10 plus populated movie and series paginator payloads; only the test looked at props.movies.total instead of props.movies.meta.total.
  timestamp: 2026-03-15T00:50:00Z

## Evidence

- timestamp: 2026-03-15T00:04:00Z
  checked: app/Http/Controllers/SearchController.php and app/Actions/SearchSeries.php
  found: SearchController line 55 passes $typeLimit as the 4th argument to SearchSeries::__invoke(), and SearchSeries declares int $perPage = 10.
  implication: Any null value in $typeLimit will crash before the action body runs.

- timestamp: 2026-03-15T00:07:00Z
  checked: app/Data/SearchMediaData.php, app/Http/Controllers/LightweightSearchController.php, and app/Actions/SearchMovies.php
  found: SearchMediaData declares public ?int $per_page with no default, while both SearchController and LightweightSearchController forward per_page-derived values into action parameters typed as int $perPage.
  implication: Omitting per_page from the request leaves a null that can crash both search entry points; normalization should happen before controller/action invocation.

- timestamp: 2026-03-15T00:09:00Z
  checked: tests directory for existing search coverage
  found: There is no existing search controller regression test covering omitted per_page.
  implication: We need a new targeted test to lock the fix and prevent recurrence.

- timestamp: 2026-03-15T00:12:00Z
  checked: app/Models/Series.php, routes/web.php, phpunit.xml, and existing discovery feature tests
  found: /search is an authenticated Inertia route, test users are already verified by default, and SQLite feature tests can insert series rows directly.
  implication: A focused feature test can reproduce the issue without external services if Scout is configured appropriately in-test.

- timestamp: 2026-03-15T00:15:00Z
  checked: per_page usages in backend/frontend search flows
  found: Controllers are the only backend callsites forwarding SearchMediaData::per_page into strict int action parameters, and frontend search forms already supply their own defaults when they own the request state.
  implication: Setting a backend default on SearchMediaData is the minimal robust fix and does not conflict with existing UI behavior.

- timestamp: 2026-03-15T00:19:00Z
  checked: patched SearchMediaData plus new feature regression test
  found: SearchMediaData now defaults per_page to 10 with Min(1) validation, and php artisan test tests/Feature/Controllers/SearchControllerTest.php passes for a series /search request that omits per_page.
  implication: The reported null-to-int TypeError path is fixed and covered by regression tests.

- timestamp: 2026-03-15T00:30:00Z
  checked: checkpoint response after human verification request
  found: Production verification is still pending, and the reporter identified the same omitted per_page symptom as possible during movie/VOD search as well as series search.
  implication: We need to verify all search callsites, not just the series regression path, and extend test coverage accordingly.

- timestamp: 2026-03-15T00:35:00Z
  checked: SearchMediaData, SearchController, LightweightSearchController, SearchMovies, and SearchSeries
  found: Both full-search and lightweight-search controllers forward SearchMediaData::per_page into strict int $perPage action parameters for movie/VOD and series queries; after the DTO change, every callsite now reads a non-null int default of 10.
  implication: The code fix already hardens movie/VOD as well as series paths, but regression coverage is incomplete because only the full-search series flow is tested.

- timestamp: 2026-03-15T00:42:00Z
  checked: php artisan test tests/Feature/Controllers/SearchControllerTest.php after adding movie and lightweight omitted-per_page tests
  found: Full-search series and movie/VOD tests pass, but the lightweight test fails because props.movies.total is null while the response still returned HTTP 200 and filters.per_page = 10.
  implication: The DTO fix appears to cover movie/VOD too; we need to inspect the lightweight response shape before deciding whether any production code is still wrong.

- timestamp: 2026-03-15T00:50:00Z
  checked: dumped lightweight search response payload from the failing test
  found: The lightweight response serializes paginators as data/links/meta, so totals live at props.movies.meta.total and props.series.meta.total while props.filters.per_page remains 10.
  implication: No further production hardening is needed for omitted per_page; only the regression test assertions need to target the correct lightweight payload shape.

- timestamp: 2026-03-15T00:55:00Z
  checked: php artisan test tests/Feature/Controllers/SearchControllerTest.php after correcting lightweight assertions
  found: All three focused regressions pass for omitted per_page across full-search series, full-search movie/VOD, and lightweight search.
  implication: The fix is self-verified for the reported and newly identified omitted-per_page paths; only end-to-end human verification remains.

## Resolution

root_cause: SearchMediaData exposed per_page as nullable with no default, so omitted requests injected null into strict int $perPage arguments in SearchSeries/SearchMovies via the search controllers.
fix: Changed SearchMediaData::per_page to a validated int with default 10 so omitted requests are normalized before controller/action invocation, and expanded regression coverage to include full-search movie/VOD and lightweight search flows when per_page is omitted.
verification: php artisan test tests/Feature/Controllers/SearchControllerTest.php
files_changed:
  - app/Data/SearchMediaData.php
  - tests/Feature/Controllers/SearchControllerTest.php
