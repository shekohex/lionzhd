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
    it('requires the sockets extension for searchable category browser tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('desktop movie search ranks fuzzy hits, hides all categories, and keeps uncategorized last', function (): void {
        $user = User::factory()->create();

        seedSearchableMovieFixture();

        $page = loginAndVisitSearchPage($user, route('movies'))
            ->resize(1280, 900)
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Comedy'))->toBeTrue();

        $page->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dra');

        expect(searchInputValue($page))->toBe('dra');

        $results = visibleSearchResults($page);

        expect($results)->not->toContain('All categories');
        expect($results)->not->toContain('Comedy');
        expect($results)->not->toContain('Uncategorized');
        expect($results)->toContain('Drama');
        expect($results)->toContain('Action Drama');

        $page->assertSee('dra')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'unc');

        $uncategorizedResults = visibleSearchResults($page);

        expect(array_key_last($uncategorizedResults))->not->toBeNull();
        expect($uncategorizedResults[array_key_last($uncategorizedResults)])->toBe('Uncategorized');
    })->group('browser');

    it('desktop series search ranks fuzzy hits, hides all categories, and keeps uncategorized last', function (): void {
        $user = User::factory()->create();

        seedSearchableSeriesFixture();

        $page = loginAndVisitSearchPage($user, route('series'))
            ->resize(1280, 900)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Comedy'))->toBeTrue();

        $page->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dra');

        expect(searchInputValue($page))->toBe('dra');

        $results = visibleSearchResults($page);

        expect($results)->not->toContain('All categories');
        expect($results)->not->toContain('Comedy');
        expect($results)->not->toContain('Uncategorized');
        expect($results)->toContain('Drama');
        expect($results)->toContain('Action Drama');

        $page->assertSee('dra')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'unc');

        $uncategorizedResults = visibleSearchResults($page);

        expect(array_key_last($uncategorizedResults))->not->toBeNull();
        expect($uncategorizedResults[array_key_last($uncategorizedResults)])->toBe('Uncategorized');
    })->group('browser');

    it('mobile movie search works in browse and manage modes, closes on select, and resets on reopen', function (): void {
        $user = User::factory()->create();

        seedSearchableMovieFixture();
        updateCategoryPreferences($user, MediaType::Movie, route('movies'), [
            'pinned_ids' => [],
            'visible_ids' => ['movie-drama', 'movie-action-drama', 'movie-uncategorized'],
            'hidden_ids' => [],
            'ignored_ids' => ['movie-dramedy'],
        ]);

        $page = loginAndVisitSearchPage($user, route('movies'))
            ->resize(390, 844)
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Movie Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dram');

        $browseResults = visibleSearchResults($page);

        expect($browseResults)->toContain('Drama');
        expect($browseResults)->toContain('Action Drama');

        expect(clickVisibleButtonByText($page, 'Drama'))->toBeTrue();

        $page->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Movie Categories'))->toBeTrue();
        expect(searchInputValue($page))->toBe('');

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dram');

        $manageResults = visibleSearchResults($page);

        expect($manageResults)->toContain('Drama');
        expect($manageResults)->toContain('Action Drama');
    })->group('browser');

    it('mobile series search works in browse and manage modes, closes on select, and resets on reopen', function (): void {
        $user = User::factory()->create();

        seedSearchableSeriesFixture();
        updateCategoryPreferences($user, MediaType::Series, route('series'), [
            'pinned_ids' => [],
            'visible_ids' => ['series-drama', 'series-action-drama', 'series-uncategorized'],
            'hidden_ids' => [],
            'ignored_ids' => ['series-dramedy'],
        ]);

        $page = loginAndVisitSearchPage($user, route('series'))
            ->resize(390, 844)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Series Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dram');

        $browseResults = visibleSearchResults($page);

        expect($browseResults)->toContain('Drama');
        expect($browseResults)->toContain('Action Drama');

        expect(clickVisibleButtonByText($page, 'Drama'))->toBeTrue();

        $page->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Series Categories'))->toBeTrue();
        expect(searchInputValue($page))->toBe('');

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dram');

        $manageResults = visibleSearchResults($page);

        expect($manageResults)->toContain('Drama');
        expect($manageResults)->toContain('Action Drama');
    })->group('browser');
}

function loginAndVisitSearchPage(User $user, string $url): object
{
    visit(route('login'))
        ->waitForText('Log in to your account')
        ->fill('Email address', $user->email)
        ->fill('Password', 'password')
        ->press('Log in')
        ->waitForText('Discover')
        ->assertNoJavaScriptErrors();

    return visit($url);
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

function seedSearchableMovieFixture(): void
{
    createMovieCategory('movie-drama', 'Drama');
    createMovieCategory('movie-action-drama', 'Action Drama');
    createMovieCategory('movie-dramedy', 'Dramedy');
    createMovieCategory('movie-comedy', 'Comedy');
    createMovieCategory('movie-uncategorized', 'Uncategorized');

    seedMovieRecord(71_001, 'Drama Story', 'movie-drama');
    seedMovieRecord(71_002, 'Action Drama Story', 'movie-action-drama');
    seedMovieRecord(71_003, 'Dramedy Story', 'movie-dramedy');
    seedMovieRecord(71_004, 'Comedy Story', 'movie-comedy');
    seedMovieRecord(71_005, 'Uncategorized Movie', 'movie-uncategorized');
}

function seedSearchableSeriesFixture(): void
{
    createSeriesCategory('series-drama', 'Drama');
    createSeriesCategory('series-action-drama', 'Action Drama');
    createSeriesCategory('series-dramedy', 'Dramedy');
    createSeriesCategory('series-comedy', 'Comedy');
    createSeriesCategory('series-uncategorized', 'Uncategorized');

    seedSeriesRecord(72_001, 'Drama Nights', 'series-drama');
    seedSeriesRecord(72_002, 'Action Drama Nights', 'series-action-drama');
    seedSeriesRecord(72_003, 'Dramedy Nights', 'series-dramedy');
    seedSeriesRecord(72_004, 'Comedy Nights', 'series-comedy');
    seedSeriesRecord(72_005, 'Uncategorized Nights', 'series-uncategorized');
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

function openMobileSheet(object $page, string $triggerText): bool
{
    return clickVisibleButtonByText($page, $triggerText);
}

function typeInlineSearchQuery(object $page, string $query): void
{
    $queryJson = json_encode($query, JSON_THROW_ON_ERROR);

    $page->script(str_replace('__QUERY__', $queryJson, <<<'JS'
        () => {
            const input = Array.from(document.querySelectorAll('input')).find((candidate) => candidate.offsetParent !== null && ! candidate.disabled);

            if (! input) {
                return false;
            }

            const descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');

            input.focus();
            descriptor?.set?.call(input, __QUERY__);
            input.dispatchEvent(new InputEvent('input', { bubbles: true, data: __QUERY__, inputType: 'insertText' }));
            input.dispatchEvent(new Event('change', { bubbles: true }));

            return true;
        }
    JS));

    $page->assertNoJavaScriptErrors();
}

function visibleSearchResults(object $page): array
{
    return $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('[cmdk-item], [role="option"], button'))
            .filter((candidate) => candidate.offsetParent !== null)
            .map((candidate) => candidate.textContent?.replace(/\s+/g, ' ').trim() ?? '')
            .filter((text) => text !== '')
    JS);
}

function selectSearchResultWithKeyboard(object $page, int $arrowDownCount): bool
{
    $countJson = json_encode($arrowDownCount, JSON_THROW_ON_ERROR);

    $page->script(str_replace('__COUNT__', $countJson, <<<'JS'
        () => {
            const input = Array.from(document.querySelectorAll('input')).find((candidate) => candidate.offsetParent !== null && ! candidate.disabled);

            if (! input) {
                return false;
            }

            input.focus();

            const fire = (key) => {
                input.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
                input.dispatchEvent(new KeyboardEvent('keyup', { key, bubbles: true }));
            };

            for (let index = 0; index < __COUNT__; index += 1) {
                fire('ArrowDown');
            }

            fire('Enter');

            return true;
        }
    JS));

    $page->assertNoJavaScriptErrors();

    return true;
}

function searchInputValue(object $page): string
{
    return $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('input')).find((candidate) => candidate.offsetParent !== null && ! candidate.disabled)?.value ?? ''
    JS);
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
