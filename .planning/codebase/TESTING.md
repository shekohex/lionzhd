# Testing Patterns

**Analysis Date:** 2026-02-24

## Test Framework

**Runner:**
- Pest PHP (invokes PHPUnit)
- Primary command used in CI: `./vendor/bin/pest`
  - Source: `.github/workflows/tests.yml`
- Config: `phpunit.xml`

**Assertion Library:**
- Pest expectations (`expect(...)`) + Laravel HTTP test assertions
  - Example: `tests/Feature/DirectDownloadLinksIntegrationTest.php` (Pest `expect`)
  - Example: `tests/Feature/Controllers/DirectDownloadControllerTest.php` (response assertions)

**Run Commands:**
```bash
./vendor/bin/pest              # Run all tests
php artisan test               # Alternate entrypoint (Laravel)
./vendor/bin/pest --coverage   # Coverage (requires a coverage driver)
```

## Test File Organization

**Location:**
- Tests live under `tests/` grouped by intent:
  - Unit: `tests/Unit/**`
  - Feature: `tests/Feature/**`
  - Architecture: `tests/Architecture/**`
  - Source: `phpunit.xml` (testsuites)

**Naming:**
- Test files end with `*Test.php`.
  - Examples: `tests/Feature/Controllers/DirectDownloadControllerTest.php`, `tests/Unit/Http/Integrations/LionzTv/Responses/VodInformationTest.php`

**Pest bootstrap:**
- Shared Pest config and base test case binding:
  - `tests/Pest.php` (`pest()->extend(TestCase::class)->in('Feature', 'Unit');`)

**Structure:**
```
tests/
├── Architecture/
│   └── GeneralTest.php
├── Feature/
│   ├── Actions/
│   └── Controllers/
├── Unit/
│   └── Http/
└── Pest.php
```

## Test Structure

**Suite Organization:**
```php
// `tests/Feature/Controllers/DirectDownloadControllerTest.php`
describe('Direct Download Controller', function (): void {
    beforeEach(function (): void {
        Config::set('features.direct_download_links', true);
    });

    it('redirects to remote URL for valid signed token', function (): void {
        $url = URL::temporarySignedRoute('direct.resolve', now()->addHours(4), ['token' => 'test-token']);
        Cache::put('direct:link:test-token', 'https://example.com/remote-url.mp4', now()->addHours(4));

        $response = $this->get($url);
        $response->assertRedirect('https://example.com/remote-url.mp4');
    });
});
```

**Patterns:**
- Use `describe(...)` to group behavior; use `it(...)` / `test(...)` for cases.
  - Examples: `tests/Unit/Http/Integrations/LionzTv/Requests/RequestCachingTest.php`, `tests/Feature/Jobs/RefreshMediaContentsTest.php`
- Use `beforeEach(...)` for per-test setup.
  - Example: `tests/Feature/Jobs/RefreshMediaContentsTest.php`

**Database setup:**
- Use `RefreshDatabase` when tests touch persistence.
  - Example: `tests/Feature/Jobs/RefreshMediaContentsTest.php` (`uses(RefreshDatabase::class);`)

## Mocking

**Framework:**
- Laravel fakes (facades), container binding, and Saloon fakes.

**Patterns:**
```php
// Facade fakes: `tests/Feature/Controllers/SyncMediaControllerTest.php`
Queue::fake();
// ... perform request
Queue::assertPushed(RefreshMediaContents::class);

// Container binding + Saloon MockClient: `tests/Feature/Jobs/RefreshMediaContentsTest.php`
app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
    $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

    return $connector->withMockClient(new MockClient([
        GetSeriesRequest::class => MockResponse::make([], 200),
        GetVodStreamsRequest::class => MockResponse::make([], 200),
    ]));
});
```

**What to Mock:**
- External HTTP integrations via Saloon `MockClient`.
  - Examples: `tests/Feature/Actions/CreateDownloadDirTest.php`, `tests/Feature/Jobs/RefreshMediaContentsTest.php`
- Laravel side effects via facades (queues, cache, config).
  - Examples: `tests/Feature/Controllers/SyncMediaControllerTest.php`, `tests/Feature/Controllers/DirectDownloadControllerTest.php`

**What NOT to Mock:**
- Pure DTO parsing/normalization logic: assert output directly from constructors/factories.
  - Example: `tests/Unit/Http/Integrations/LionzTv/Responses/VodInformationTest.php` (`VodInformation::fromJson(...)`)

## Fixtures and Factories

**Test Data:**
- Use model factories for Laravel models.
  - Example: `tests/Feature/Controllers/SyncMediaControllerTest.php` (`User::factory()->make(...)`)
- Use `::fake()` constructors on integration response DTOs for convenient test inputs.
  - Example: `tests/Feature/DirectDownloadLinksIntegrationTest.php` (`VodInformation::fake()`, `Episode::fake()`)

**Location:**
- JSON fixtures live in `tests/fixtures/`.
  - Examples: `tests/fixtures/get_vod_streams.json`, `tests/fixtures/get_series.json`

## Coverage

**Requirements:**
- None enforced.
  - CI runs `./vendor/bin/pest` without a coverage gate.
  - Source: `.github/workflows/tests.yml`

**View Coverage:**
```bash
./vendor/bin/pest --coverage
```

## Test Types

**Unit Tests:**
- Validate parsing/mapping and small pure behaviors.
  - Example: `tests/Unit/Http/Integrations/LionzTv/Responses/MovieTest.php`

**Integration/Feature Tests:**
- Exercise HTTP endpoints, jobs, and actions with Laravel test helpers.
  - Example: `tests/Feature/Controllers/DirectDownloadControllerTest.php`
  - Example: `tests/Feature/Jobs/RefreshMediaContentsTest.php`

**Architecture Tests:**
- Enforce high-level rules via Pest architecture presets.
  - Example: `tests/Architecture/GeneralTest.php`

**E2E Tests:**
- Not detected (no Cypress/Playwright config; no `resources/js/**/*.test.*`).
  - Evidence: `package.json` (no JS test runner scripts/deps)

## Common Patterns

**Async/Jobs:**
- Use Laravel queue interaction helpers on the job instance.
  - Example: `tests/Feature/Jobs/RefreshMediaContentsTest.php` (`withFakeQueueInteractions()->assertNotFailed()->handle();`)

**Error Testing:**
- Prefer behavior assertions (HTTP status, redirects, session errors).
  - Example: `tests/Feature/Controllers/DirectDownloadControllerTest.php` (`assertForbidden()`, `assertNotFound()`)

---

*Testing analysis: 2026-02-24*
