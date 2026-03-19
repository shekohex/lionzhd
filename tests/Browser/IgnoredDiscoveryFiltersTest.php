<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Category;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

if (! extension_loaded('sockets')) {
    it('requires the sockets extension for ignored discovery browser tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('shows movie ignored category recovery in place and restores results on the same url after unignore', function (): void {
        $user = User::factory()->create();

        createMovieCategory('movie-action', 'Action');
        createMovieCategory('movie-comedy', 'Comedy');

        seedMovieRecord(51_001, 'Movie Action', 'movie-action');
        seedMovieRecord(51_002, 'Movie Comedy', 'movie-comedy');

        updateCategoryPreferences($user, MediaType::Movie, route('movies'), [
            'pinned_ids' => [],
            'visible_ids' => ['movie-comedy'],
            'hidden_ids' => [],
            'ignored_ids' => ['movie-action'],
        ]);

        test()->actingAs($user);

        $page = visit(route('movies', ['category' => 'movie-action']))
            ->waitForText('Movie Categories')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('movies', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBe('movie-action');

        $page->click('Unignore and restore results')
            ->waitForText('Movie Action')
            ->assertDontSee('This category is ignored')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('movies', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBe('movie-action');
    })->group('browser');

    it('uses manage first recovery for empty all categories movie browse and keeps reset secondary', function (): void {
        $user = User::factory()->create();

        createMovieCategory('movie-action', 'Action');
        createMovieCategory('movie-drama', 'Drama');

        seedMovieRecord(52_001, 'Movie Action', 'movie-action');
        seedMovieRecord(52_002, 'Movie Drama', 'movie-drama');

        updateCategoryPreferences($user, MediaType::Movie, route('movies'), [
            'pinned_ids' => [],
            'visible_ids' => [],
            'hidden_ids' => ['movie-action'],
            'ignored_ids' => ['movie-drama'],
        ]);

        test()->actingAs($user);

        $page = visit(route('movies'))
            ->waitForText('Movie Categories')
            ->waitForText('Your movie view is empty')
            ->assertSee('Manage categories')
            ->assertSee('Reset preferences')
            ->assertNoJavaScriptErrors();

        $actions = $page->script(<<<'JS'
            () => {
                const root = Array.from(document.querySelectorAll('h3')).find((heading) =>
                    heading.textContent?.includes('Your movie view is empty')
                )?.parentElement;

                return Array.from(root?.querySelectorAll('button') ?? []).map((button) => button.textContent?.trim());
            }
        JS);

        expect($actions)->toBe(['Manage categories', 'Reset preferences']);

        $page->click('Manage categories')
            ->waitForText('Preferences')
            ->assertSee('Reset to default')
            ->assertNoJavaScriptErrors();
    })->group('browser');

    it('keeps ignored movie rows visible and selectable on desktop and mobile with muted affordances', function (): void {
        $user = User::factory()->create();

        createMovieCategory('movie-action', 'Action');
        createMovieCategory('movie-comedy', 'Comedy');

        seedMovieRecord(53_001, 'Movie Action', 'movie-action');
        seedMovieRecord(53_002, 'Movie Comedy', 'movie-comedy');

        updateCategoryPreferences($user, MediaType::Movie, route('movies'), [
            'pinned_ids' => [],
            'visible_ids' => ['movie-comedy'],
            'hidden_ids' => [],
            'ignored_ids' => ['movie-action'],
        ]);

        test()->actingAs($user);

        $page = visit(route('movies'))
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        $desktopMetrics = ignoredRowMetrics($page, 'Action');

        expect($desktopMetrics['found'])->toBeTrue();
        expect($desktopMetrics['className'])->toContain('bg-muted/25');
        expect($desktopMetrics['rowText'])->toBe('Action');

        $page->click('Action')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();

        $mobilePage = visit(route('movies'))
            ->resize(390, 844)
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        $opened = $mobilePage->script(<<<'JS'
            () => {
                const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                    candidate.textContent?.trim() === 'Movie Categories' && candidate.offsetParent !== null
                );

                button?.click();

                return Boolean(button);
            }
        JS);

        expect($opened)->toBeTrue();

        $mobilePage->waitForText('Action')
            ->assertNoJavaScriptErrors();

        $mobileMetrics = ignoredRowMetrics($mobilePage, 'Action');

        expect($mobileMetrics['found'])->toBeTrue();
        expect($mobileMetrics['className'])->toContain('bg-muted/25');
        expect($mobileMetrics['rowText'])->toBe('Action');

        $selected = $mobilePage->script(<<<'JS'
            () => {
                const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                    candidate.textContent?.trim() === 'Action' && candidate.offsetParent !== null
                );

                button?.click();

                return Boolean(button);
            }
        JS);

        expect($selected)->toBeTrue();

        $mobilePage->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();
    })->group('browser');

    it('persists desktop movie ignore and unignore actions across refresh without leaving browse', function (): void {
        $user = User::factory()->create();

        createMovieCategory('movie-action', 'Action');
        createMovieCategory('movie-comedy', 'Comedy');

        seedMovieRecord(53_101, 'Movie Action', 'movie-action');
        seedMovieRecord(53_102, 'Movie Comedy', 'movie-comedy');

        test()->actingAs($user);

        $page = visit(route('movies'))
            ->waitForText('Movie Categories')
            ->waitForText('Movie Action')
            ->waitForText('Movie Comedy')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByAriaLabel($page, 'Ignore Action'))->toBeTrue();

        $page->waitForText('Movie Comedy')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('movies', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBeNull();

        $refreshedPage = visit(route('movies'))
            ->waitForText('Movie Categories')
            ->waitForText('Movie Comedy')
            ->assertDontSee('Movie Action')
            ->assertNoJavaScriptErrors();

        $ignoredMetrics = ignoredRowMetrics($refreshedPage, 'Action');

        expect($ignoredMetrics['found'])->toBeTrue();
        expect($ignoredMetrics['className'])->toContain('bg-muted/25');

        expect(clickVisibleButtonByAriaLabel($refreshedPage, 'Unignore Action'))->toBeTrue();

        $refreshedPage->waitForText('Movie Action')
            ->assertNoJavaScriptErrors();

        $restoredPage = visit(route('movies'))
            ->waitForText('Movie Categories')
            ->waitForText('Movie Action')
            ->waitForText('Movie Comedy')
            ->assertNoJavaScriptErrors();

        $restoredMetrics = ignoredRowMetrics($restoredPage, 'Action');

        expect($restoredMetrics['found'])->toBeTrue();
        expect($restoredMetrics['className'])->not->toContain('bg-muted/25');
    })->group('browser');

    it('persists mobile movie manage-mode ignore and unignore actions across refresh without leaving browse', function (): void {
        $user = User::factory()->create();

        createMovieCategory('movie-action', 'Action');
        createMovieCategory('movie-comedy', 'Comedy');

        seedMovieRecord(53_201, 'Movie Action', 'movie-action');
        seedMovieRecord(53_202, 'Movie Comedy', 'movie-comedy');

        test()->actingAs($user);

        $page = visit(route('movies'))
            ->resize(390, 844)
            ->waitForText('Movie Categories')
            ->waitForText('Movie Action')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Movie Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByAriaLabel($page, 'Ignore Action'))->toBeTrue();

        $page->waitForText('Ignored')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('movies', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBeNull();

        $reopenedPage = visit(route('movies'))
            ->resize(390, 844)
            ->waitForText('Movie Categories')
            ->assertDontSee('Movie Action')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($reopenedPage, 'Movie Categories'))->toBeTrue();

        $reopenedPage->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($reopenedPage, 'Manage'))->toBeTrue();

        $reopenedPage->waitForText('Ignored')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByAriaLabel($reopenedPage, 'Unignore Action'))->toBeTrue();

        $reopenedPage->assertNoJavaScriptErrors();

        $restoredPage = visit(route('movies'))
            ->resize(390, 844)
            ->waitForText('Movie Categories')
            ->waitForText('Movie Action')
            ->waitForText('Movie Comedy')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($restoredPage, 'Movie Categories'))->toBeTrue();

        $restoredPage->waitForText('Action')
            ->assertNoJavaScriptErrors();

        $restoredMetrics = ignoredRowMetrics($restoredPage, 'Action');

        expect($restoredMetrics['found'])->toBeTrue();
        expect($restoredMetrics['className'])->not->toContain('bg-muted/25');
    })->group('browser');

    it('restores only the selected ignored movie category when multiple ignored categories exist', function (): void {
        $user = User::factory()->create();

        createMovieCategory('movie-action', 'Action');
        createMovieCategory('movie-comedy', 'Comedy');
        createMovieCategory('movie-drama', 'Drama');

        seedMovieRecord(53_301, 'Movie Action', 'movie-action');
        seedMovieRecord(53_302, 'Movie Comedy', 'movie-comedy');
        seedMovieRecord(53_303, 'Movie Drama', 'movie-drama');

        updateCategoryPreferences($user, MediaType::Movie, route('movies'), [
            'pinned_ids' => [],
            'visible_ids' => ['movie-drama'],
            'hidden_ids' => [],
            'ignored_ids' => ['movie-action', 'movie-comedy'],
        ]);

        test()->actingAs($user);

        $page = visit(route('movies', ['category' => 'movie-action']))
            ->waitForText('Movie Categories')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();

        $page->click('Unignore and restore results')
            ->waitForText('Movie Action')
            ->assertDontSee('This category is ignored')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('movies', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBe('movie-action');

        $allCategoriesPage = visit(route('movies'))
            ->waitForText('Movie Categories')
            ->waitForText('Movie Action')
            ->waitForText('Movie Drama')
            ->assertDontSee('Movie Comedy')
            ->assertNoJavaScriptErrors();

        $restoredMetrics = ignoredRowMetrics($allCategoriesPage, 'Action');
        $remainingIgnoredMetrics = ignoredRowMetrics($allCategoriesPage, 'Comedy');

        expect($restoredMetrics['found'])->toBeTrue();
        expect($restoredMetrics['className'])->not->toContain('bg-muted/25');
        expect($remainingIgnoredMetrics['found'])->toBeTrue();
        expect($remainingIgnoredMetrics['className'])->toContain('bg-muted/25');

        $allCategoriesPage->click('Comedy')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();
    })->group('browser');

    it('shows series ignored category recovery in place and restores results on the same url after unignore', function (): void {
        $user = User::factory()->create();

        createSeriesCategory('series-drama', 'Drama');
        createSeriesCategory('series-comedy', 'Comedy');

        seedSeriesRecord(61_001, 'Drama Nights', 'series-drama');
        seedSeriesRecord(61_002, 'Comedy Nights', 'series-comedy');

        updateCategoryPreferences($user, MediaType::Series, route('series'), [
            'pinned_ids' => [],
            'visible_ids' => ['series-comedy'],
            'hidden_ids' => [],
            'ignored_ids' => ['series-drama'],
        ]);

        test()->actingAs($user);

        $page = visit(route('series', ['category' => 'series-drama']))
            ->waitForText('Series Categories')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('series', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBe('series-drama');

        $page->click('Unignore and restore results')
            ->waitForText('Drama Nights')
            ->assertDontSee('This category is ignored')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('series', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBe('series-drama');
    })->group('browser');

    it('uses manage first recovery for empty all categories series browse and keeps reset secondary', function (): void {
        $user = User::factory()->create();

        createSeriesCategory('series-drama', 'Drama');
        createSeriesCategory('series-thriller', 'Thriller');

        seedSeriesRecord(62_001, 'Drama Nights', 'series-drama');
        seedSeriesRecord(62_002, 'Thriller Nights', 'series-thriller');

        updateCategoryPreferences($user, MediaType::Series, route('series'), [
            'pinned_ids' => [],
            'visible_ids' => [],
            'hidden_ids' => ['series-drama'],
            'ignored_ids' => ['series-thriller'],
        ]);

        test()->actingAs($user);

        $page = visit(route('series'))
            ->waitForText('Series Categories')
            ->waitForText('Your series view is empty')
            ->assertSee('Manage categories')
            ->assertSee('Reset preferences')
            ->assertNoJavaScriptErrors();

        $actions = $page->script(<<<'JS'
            () => {
                const root = Array.from(document.querySelectorAll('h3')).find((heading) =>
                    heading.textContent?.includes('Your series view is empty')
                )?.parentElement;

                return Array.from(root?.querySelectorAll('button') ?? []).map((button) => button.textContent?.trim());
            }
        JS);

        expect($actions)->toBe(['Manage categories', 'Reset preferences']);

        $page->click('Manage categories')
            ->waitForText('Preferences')
            ->assertSee('Reset to default')
            ->assertNoJavaScriptErrors();
    })->group('browser');

    it('keeps ignored series rows visible and selectable on desktop and mobile with muted affordances', function (): void {
        $user = User::factory()->create();

        createSeriesCategory('series-drama', 'Drama');
        createSeriesCategory('series-comedy', 'Comedy');

        seedSeriesRecord(63_001, 'Drama Nights', 'series-drama');
        seedSeriesRecord(63_002, 'Comedy Nights', 'series-comedy');

        updateCategoryPreferences($user, MediaType::Series, route('series'), [
            'pinned_ids' => [],
            'visible_ids' => ['series-comedy'],
            'hidden_ids' => [],
            'ignored_ids' => ['series-drama'],
        ]);

        test()->actingAs($user);

        $page = visit(route('series'))
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        $desktopMetrics = ignoredRowMetrics($page, 'Drama');

        expect($desktopMetrics['found'])->toBeTrue();
        expect($desktopMetrics['className'])->toContain('bg-muted/25');
        expect($desktopMetrics['rowText'])->toBe('Drama');

        $page->click('Drama')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();

        $mobilePage = visit(route('series'))
            ->resize(390, 844)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        $opened = $mobilePage->script(<<<'JS'
            () => {
                const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                    candidate.textContent?.trim() === 'Series Categories' && candidate.offsetParent !== null
                );

                button?.click();

                return Boolean(button);
            }
        JS);

        expect($opened)->toBeTrue();

        $mobilePage->waitForText('Drama')
            ->assertNoJavaScriptErrors();

        $mobileMetrics = ignoredRowMetrics($mobilePage, 'Drama');

        expect($mobileMetrics['found'])->toBeTrue();
        expect($mobileMetrics['className'])->toContain('bg-muted/25');
        expect($mobileMetrics['rowText'])->toBe('Drama');

        $selected = $mobilePage->script(<<<'JS'
            () => {
                const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                    candidate.textContent?.trim() === 'Drama' && candidate.offsetParent !== null
                );

                button?.click();

                return Boolean(button);
            }
        JS);

        expect($selected)->toBeTrue();

        $mobilePage->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();
    })->group('browser');

    it('persists desktop series ignore and unignore actions across refresh without leaving browse', function (): void {
        $user = User::factory()->create();

        createSeriesCategory('series-drama', 'Drama');
        createSeriesCategory('series-comedy', 'Comedy');

        seedSeriesRecord(63_101, 'Drama Nights', 'series-drama');
        seedSeriesRecord(63_102, 'Comedy Nights', 'series-comedy');

        test()->actingAs($user);

        $page = visit(route('series'))
            ->waitForText('Series Categories')
            ->waitForText('Drama Nights')
            ->waitForText('Comedy Nights')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByAriaLabel($page, 'Ignore Drama'))->toBeTrue();

        $page->waitForText('Comedy Nights')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('series', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBeNull();

        $refreshedPage = visit(route('series'))
            ->waitForText('Series Categories')
            ->waitForText('Comedy Nights')
            ->assertDontSee('Drama Nights')
            ->assertNoJavaScriptErrors();

        $ignoredMetrics = ignoredRowMetrics($refreshedPage, 'Drama');

        expect($ignoredMetrics['found'])->toBeTrue();
        expect($ignoredMetrics['className'])->toContain('bg-muted/25');

        expect(clickVisibleButtonByAriaLabel($refreshedPage, 'Unignore Drama'))->toBeTrue();

        $refreshedPage->waitForText('Drama Nights')
            ->assertNoJavaScriptErrors();

        $restoredPage = visit(route('series'))
            ->waitForText('Series Categories')
            ->waitForText('Drama Nights')
            ->waitForText('Comedy Nights')
            ->assertNoJavaScriptErrors();

        $restoredMetrics = ignoredRowMetrics($restoredPage, 'Drama');

        expect($restoredMetrics['found'])->toBeTrue();
        expect($restoredMetrics['className'])->not->toContain('bg-muted/25');
    })->group('browser');

    it('persists mobile series manage-mode ignore and unignore actions across refresh without leaving browse', function (): void {
        $user = User::factory()->create();

        createSeriesCategory('series-drama', 'Drama');
        createSeriesCategory('series-comedy', 'Comedy');

        seedSeriesRecord(63_201, 'Drama Nights', 'series-drama');
        seedSeriesRecord(63_202, 'Comedy Nights', 'series-comedy');

        test()->actingAs($user);

        $page = visit(route('series'))
            ->resize(390, 844)
            ->waitForText('Series Categories')
            ->waitForText('Drama Nights')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Series Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByAriaLabel($page, 'Ignore Drama'))->toBeTrue();

        $page->waitForText('Ignored')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('series', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBeNull();

        $reopenedPage = visit(route('series'))
            ->resize(390, 844)
            ->waitForText('Series Categories')
            ->assertDontSee('Drama Nights')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($reopenedPage, 'Series Categories'))->toBeTrue();

        $reopenedPage->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($reopenedPage, 'Manage'))->toBeTrue();

        $reopenedPage->waitForText('Ignored')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByAriaLabel($reopenedPage, 'Unignore Drama'))->toBeTrue();

        $reopenedPage->assertNoJavaScriptErrors();

        $restoredPage = visit(route('series'))
            ->resize(390, 844)
            ->waitForText('Series Categories')
            ->waitForText('Drama Nights')
            ->waitForText('Comedy Nights')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($restoredPage, 'Series Categories'))->toBeTrue();

        $restoredPage->waitForText('Drama')
            ->assertNoJavaScriptErrors();

        $restoredMetrics = ignoredRowMetrics($restoredPage, 'Drama');

        expect($restoredMetrics['found'])->toBeTrue();
        expect($restoredMetrics['className'])->not->toContain('bg-muted/25');
    })->group('browser');

    it('restores only the selected ignored series category when multiple ignored categories exist', function (): void {
        $user = User::factory()->create();

        createSeriesCategory('series-drama', 'Drama');
        createSeriesCategory('series-comedy', 'Comedy');
        createSeriesCategory('series-thriller', 'Thriller');

        seedSeriesRecord(63_301, 'Drama Nights', 'series-drama');
        seedSeriesRecord(63_302, 'Comedy Nights', 'series-comedy');
        seedSeriesRecord(63_303, 'Thriller Nights', 'series-thriller');

        updateCategoryPreferences($user, MediaType::Series, route('series'), [
            'pinned_ids' => [],
            'visible_ids' => ['series-thriller'],
            'hidden_ids' => [],
            'ignored_ids' => ['series-drama', 'series-comedy'],
        ]);

        test()->actingAs($user);

        $page = visit(route('series', ['category' => 'series-drama']))
            ->waitForText('Series Categories')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();

        $page->click('Unignore and restore results')
            ->waitForText('Drama Nights')
            ->assertDontSee('This category is ignored')
            ->assertNoJavaScriptErrors();

        expect(parse_url($page->url(), PHP_URL_PATH))->toBe(route('series', [], false));
        expect(currentQueryValue($page->url(), 'category'))->toBe('series-drama');

        $allCategoriesPage = visit(route('series'))
            ->waitForText('Series Categories')
            ->waitForText('Drama Nights')
            ->waitForText('Thriller Nights')
            ->assertDontSee('Comedy Nights')
            ->assertNoJavaScriptErrors();

        $restoredMetrics = ignoredRowMetrics($allCategoriesPage, 'Drama');
        $remainingIgnoredMetrics = ignoredRowMetrics($allCategoriesPage, 'Comedy');

        expect($restoredMetrics['found'])->toBeTrue();
        expect($restoredMetrics['className'])->not->toContain('bg-muted/25');
        expect($remainingIgnoredMetrics['found'])->toBeTrue();
        expect($remainingIgnoredMetrics['className'])->toContain('bg-muted/25');

        $allCategoriesPage->click('Comedy')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();
    })->group('browser');
}

function createMovieCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);
}

function createSeriesCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);
}

function seedMovieRecord(int $streamId, string $name, string $categoryId): void
{
    VodStream::withoutSyncingToSearch(static function () use ($streamId, $name, $categoryId): void {
        VodStream::unguarded(static function () use ($streamId, $name, $categoryId): void {
            VodStream::query()->create([
                'stream_id' => $streamId,
                'num' => $streamId,
                'name' => $name,
                'stream_type' => 'movie',
                'stream_icon' => 'https://example.com/poster.jpg',
                'added' => now()->toIso8601String(),
                'category_id' => $categoryId,
                'container_extension' => 'mp4',
            ]);
        });
    });
}

function seedSeriesRecord(int $seriesId, string $name, string $categoryId): void
{
    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => $name,
        'category_id' => $categoryId,
        'cover' => 'https://example.com/cover.jpg',
        'plot' => 'Plot',
        'cast' => 'Cast',
        'director' => 'Director',
        'genre' => 'Genre',
        'backdrop_path' => json_encode([], JSON_THROW_ON_ERROR),
        'releaseDate' => '2026-03-18',
        'rating' => 4.5,
        'rating_5based' => 4.5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function updateCategoryPreferences(User $user, MediaType $mediaType, string $from, array $payload): void
{
    test()->actingAs($user)
        ->from($from)
        ->patch(route('category-preferences.update', ['mediaType' => $mediaType->value]), $payload)
        ->assertRedirect($from)
        ->assertSessionHasNoErrors();
}

function currentQueryValue(string $url, string $key): ?string
{
    $query = parse_url($url, PHP_URL_QUERY);

    if (! is_string($query) || $query === '') {
        return null;
    }

    parse_str($query, $values);

    $value = $values[$key] ?? null;

    return is_string($value) ? $value : null;
}

function ignoredRowMetrics(object $page, string $label): array
{
    $labelJson = json_encode($label, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__LABEL__', $labelJson, <<<'JS'
        () => {
            const label = __LABEL__;
            const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                candidate.textContent?.trim() === label && candidate.offsetParent !== null
            );

            return {
                found: Boolean(button),
                className: button?.className ?? '',
                rowText: button?.closest('[class*="group/row"]')?.textContent?.replace(/\s+/g, ' ').trim() ?? button?.textContent?.trim() ?? '',
            };
        }
    JS));
}

function clickVisibleButtonByAriaLabel(object $page, string $label): bool
{
    $labelJson = json_encode($label, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__LABEL__', $labelJson, <<<'JS'
        () => {
            const label = __LABEL__;
            const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                candidate.getAttribute('aria-label') === label && candidate.offsetParent !== null
            );

            button?.click();

            return Boolean(button);
        }
    JS));
}

function clickVisibleButtonByText(object $page, string $text): bool
{
    $textJson = json_encode($text, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__TEXT__', $textJson, <<<'JS'
        () => {
            const text = __TEXT__;
            const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                candidate.textContent?.trim() === text && candidate.offsetParent !== null
            );

            button?.click();

            return Boolean(button);
        }
    JS));
}
