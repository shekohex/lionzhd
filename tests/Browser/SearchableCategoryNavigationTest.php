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

        expect(searchInputAppearsBelowSidebarTitle($page, 'Movie Categories'))->toBeTrue();

        expect(clickVisibleButtonByText($page, 'Comedy'))->toBeTrue();

        $page->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dra');

        expect(searchInputValue($page))->toBe('dra');

        $results = visibleSearchResults($page);

        expect($results)->not->toContain('All categories');
        expect($results)->not->toContain('Comedy');
        expect($results)->not->toContain('Uncategorized');
        expect($results)->toBe(['Drama', 'Dramedy', 'Action Drama']);
        expect(visibleHighlightedSegments($page))->toContain('Dra');

        $page->assertSee('dra')
            ->assertNoJavaScriptErrors();

        expect(pressSearchKey($page, 'ArrowDown'))->toBe('Dramedy');
        expect(pressSearchKey($page, 'ArrowDown'))->toBe('Action Drama');
        expect(pressSearchKey($page, 'Enter'))->toBeTrue();

        expect(waitForLocationToContain($page, 'category=movie-action-drama'))->toBeTrue();
    })->group('browser');

    it('desktop series search ranks fuzzy hits, hides all categories, and keeps uncategorized last', function (): void {
        $user = User::factory()->create();

        seedSearchableSeriesFixture();

        $page = loginAndVisitSearchPage($user, route('series'))
            ->resize(1280, 900)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        expect(searchInputAppearsBelowSidebarTitle($page, 'Series Categories'))->toBeTrue();

        expect(clickVisibleButtonByText($page, 'Comedy'))->toBeTrue();

        $page->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dra');

        expect(searchInputValue($page))->toBe('dra');

        $results = visibleSearchResults($page);

        expect($results)->not->toContain('All categories');
        expect($results)->not->toContain('Comedy');
        expect($results)->not->toContain('Uncategorized');
        expect($results)->toBe(['Drama', 'Dramedy', 'Action Drama']);
        expect(visibleHighlightedSegments($page))->toContain('Dra');

        $page->assertSee('dra')
            ->assertNoJavaScriptErrors();

        expect(pressSearchKey($page, 'ArrowDown'))->toBe('Dramedy');
        expect(pressSearchKey($page, 'ArrowDown'))->toBe('Action Drama');
        expect(pressSearchKey($page, 'Enter'))->toBeTrue();

        expect(waitForLocationToContain($page, 'category=series-action-drama'))->toBeTrue();
    })->group('browser');

    it('desktop movie search keeps uncategorized last and offers guided clear-search recovery', function (): void {
        $user = User::factory()->create();

        seedSearchableMovieFixture();

        $page = loginAndVisitSearchPage($user, route('movies'))
            ->resize(1280, 900)
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'unc');

        $uncategorizedResults = visibleSearchResults($page);

        expect(array_key_last($uncategorizedResults))->not->toBeNull();
        expect($uncategorizedResults[array_key_last($uncategorizedResults)])->toBe('Uncategorized');

        typeInlineSearchQuery($page, 'zzz');

        expect(noMatchStateText($page))->toContain('No categories match your search.');
        expect(noMatchStateText($page))->toContain('Try a different category name or clear the current query.');
        expect(noMatchStateText($page))->not->toContain('hidden');
        expect(clickVisibleButtonByText($page, 'Clear search'))->toBeTrue();
        expect(searchInputValue($page))->toBe('');
    })->group('browser');

    it('desktop series search keeps uncategorized last and offers guided clear-search recovery', function (): void {
        $user = User::factory()->create();

        seedSearchableSeriesFixture();

        $page = loginAndVisitSearchPage($user, route('series'))
            ->resize(1280, 900)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'unc');

        $uncategorizedResults = visibleSearchResults($page);

        expect(array_key_last($uncategorizedResults))->not->toBeNull();
        expect($uncategorizedResults[array_key_last($uncategorizedResults)])->toBe('Uncategorized');

        typeInlineSearchQuery($page, 'zzz');

        expect(noMatchStateText($page))->toContain('No categories match your search.');
        expect(noMatchStateText($page))->toContain('Try a different category name or clear the current query.');
        expect(noMatchStateText($page))->not->toContain('hidden');
        expect(clickVisibleButtonByText($page, 'Clear search'))->toBeTrue();
        expect(searchInputValue($page))->toBe('');
    })->group('browser');

    it('mobile movie search works in browse and manage modes, closes on select, and resets on reopen', function (): void {
        $user = User::factory()->create();

        seedSearchableMovieFixture();
        updateCategoryPreferences($user, MediaType::Movie, route('movies'), [
            'pinned_ids' => [],
            'visible_ids' => ['movie-drama', 'movie-action-drama', 'movie-uncategorized'],
            'hidden_ids' => ['movie-comedy'],
            'ignored_ids' => ['movie-dramedy'],
        ]);

        test()->actingAs($user);

        $page = visit(route('movies'))
            ->resize(390, 844)
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Movie Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(searchInputAppearsNearMobileSheetTop($page, 'Movie Categories'))->toBeTrue();
        expect(searchInputIsFocused($page))->toBeFalse();

        typeInlineSearchQuery($page, 'dram');

        $browseResults = visibleSearchResults($page);

        expect($browseResults)->not->toContain('All categories');
        expect($browseResults)->toContain('Drama');
        expect($browseResults)->toContain('Action Drama');
        expect(implode(' ', $browseResults))->toContain('Dramedy');

        expect(clickVisibleButtonByText($page, 'Drama'))->toBeTrue();

        expect(waitForLocationToContain($page, 'category=movie-drama'))->toBeTrue();
        expect(waitForMobileSheetToClose($page))->toBeTrue();
        $page->assertNoJavaScriptErrors();
        expect(waitForVisibleButtonByText($page, 'Movie Categories'))->toBeTrue();

        $page->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Movie Categories'))->toBeTrue();
        expect(searchInputValue($page))->toBe('');
        expect(searchInputIsFocused($page))->toBeFalse();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dram');

        $manageResults = visibleSearchResults($page);

        expect($manageResults)->not->toContain('All categories');
        expect($manageResults)->toContain('Drama');
        expect($manageResults)->toContain('Action Drama');
        expect(implode(' ', $manageResults))->toContain('Dramedy');

        typeInlineSearchQuery($page, 'com');

        expect(noMatchStateText($page))->toContain('No categories match your search.');
        expect(implode(' ', visibleSearchResults($page)))->not->toContain('Comedy');
    })->group('browser');

    it('mobile series search works in browse and manage modes, closes on select, and resets on reopen', function (): void {
        $user = User::factory()->create();

        seedSearchableSeriesFixture();
        updateCategoryPreferences($user, MediaType::Series, route('series'), [
            'pinned_ids' => [],
            'visible_ids' => ['series-drama', 'series-action-drama', 'series-uncategorized'],
            'hidden_ids' => ['series-comedy'],
            'ignored_ids' => ['series-dramedy'],
        ]);

        test()->actingAs($user);

        $page = visit(route('series'))
            ->resize(390, 844)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Series Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(searchInputAppearsNearMobileSheetTop($page, 'Series Categories'))->toBeTrue();
        expect(searchInputIsFocused($page))->toBeFalse();

        typeInlineSearchQuery($page, 'dram');

        $browseResults = visibleSearchResults($page);

        expect($browseResults)->not->toContain('All categories');
        expect($browseResults)->toContain('Drama');
        expect($browseResults)->toContain('Action Drama');
        expect(implode(' ', $browseResults))->toContain('Dramedy');

        expect(clickVisibleButtonByText($page, 'Drama'))->toBeTrue();

        expect(waitForLocationToContain($page, 'category=series-drama'))->toBeTrue();
        expect(waitForMobileSheetToClose($page))->toBeTrue();
        expect(waitForVisibleButtonByText($page, 'Series Categories'))->toBeTrue();

        $page->assertNoJavaScriptErrors();

        expect(openMobileSheet($page, 'Series Categories'))->toBeTrue();
        expect(searchInputValue($page))->toBe('');
        expect(searchInputIsFocused($page))->toBeFalse();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(clickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        typeInlineSearchQuery($page, 'dram');

        $manageResults = visibleSearchResults($page);

        expect($manageResults)->not->toContain('All categories');
        expect($manageResults)->toContain('Drama');
        expect($manageResults)->toContain('Action Drama');
        expect(implode(' ', $manageResults))->toContain('Dramedy');

        typeInlineSearchQuery($page, 'com');

        expect(noMatchStateText($page))->toContain('No categories match your search.');
        expect(implode(' ', visibleSearchResults($page)))->not->toContain('Comedy');
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
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const input = Array.from(document.querySelectorAll('input')).find((candidate) => isVisible(candidate) && ! candidate.disabled);

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
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };

            return Array.from(document.querySelectorAll('[cmdk-item], [role="option"]'))
                .filter((candidate) => isVisible(candidate))
            .map((candidate) => candidate.textContent?.replace(/\s+/g, ' ').trim() ?? '')
                .filter((text) => text !== '');
        }
    JS);
}

function selectSearchResultWithKeyboard(object $page, int $arrowDownCount): bool
{
    $countJson = json_encode($arrowDownCount, JSON_THROW_ON_ERROR);

    $page->script(str_replace('__COUNT__', $countJson, <<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const input = Array.from(document.querySelectorAll('input')).find((candidate) => isVisible(candidate) && ! candidate.disabled);

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

function pressSearchKey(object $page, string $key): string|bool
{
    $keyJson = json_encode($key, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__KEY__', $keyJson, <<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const items = Array.from(document.querySelectorAll('[cmdk-item]')).filter((candidate) => isVisible(candidate));

            if (items.length === 0) {
                return false;
            }

            const key = __KEY__;

            if (key === 'Enter') {
                const selectedItem = items.find((candidate) => candidate.getAttribute('aria-selected') === 'true' || candidate.getAttribute('data-selected') === 'true');
                selectedItem?.click();

                return Boolean(selectedItem);
            }

            const currentIndex = items.findIndex((candidate) => candidate.getAttribute('aria-selected') === 'true' || candidate.getAttribute('data-selected') === 'true');
            const nextIndex = currentIndex === -1 ? 0 : Math.min(currentIndex + 1, items.length - 1);

            items.forEach((candidate, index) => {
                if (index === nextIndex) {
                    candidate.setAttribute('aria-selected', 'true');
                    candidate.setAttribute('data-selected', 'true');
                } else {
                    candidate.setAttribute('aria-selected', 'false');
                    candidate.setAttribute('data-selected', 'false');
                }
            });

            return items[nextIndex]?.textContent?.replace(/\s+/g, ' ').trim() ?? false;
        }
    JS));
}

function searchInputValue(object $page): string
{
    return $page->script(<<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };

            return Array.from(document.querySelectorAll('input')).find((candidate) => isVisible(candidate) && ! candidate.disabled)?.value ?? '';
        }
    JS);
}

function searchInputAppearsBelowSidebarTitle(object $page, string $title): bool
{
    $titleJson = json_encode($title, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__TITLE__', $titleJson, <<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const heading = Array.from(document.querySelectorAll('aside h1, aside h2, aside h3')).find((candidate) =>
                candidate.textContent?.trim() === __TITLE__ && isVisible(candidate)
            );
            const input = Array.from(document.querySelectorAll('input')).find((candidate) => isVisible(candidate) && ! candidate.disabled);

            if (! heading || ! input) {
                return false;
            }

            return input.getBoundingClientRect().top >= heading.getBoundingClientRect().bottom - 8;
        }
    JS));
}

function searchInputAppearsNearMobileSheetTop(object $page, string $title): bool
{
    $titleJson = json_encode($title, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__TITLE__', $titleJson, <<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const container = Array.from(document.querySelectorAll('[data-slot="sheet-content"], [role="dialog"]')).find((candidate) => isVisible(candidate)) ?? document;
            const input = Array.from(container.querySelectorAll('input')).find((candidate) => isVisible(candidate) && ! candidate.disabled);

            return Boolean(input);
        }
    JS));
}

function searchInputIsFocused(object $page): bool
{
    return $page->script(<<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const input = Array.from(document.querySelectorAll('input')).find((candidate) => isVisible(candidate) && ! candidate.disabled);

            return Boolean(input) && document.activeElement === input;
        }
    JS);
}

function visibleHighlightedSegments(object $page): array
{
    return $page->script(<<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };

            return Array.from(document.querySelectorAll('[cmdk-item] .font-semibold'))
                .filter((candidate) => isVisible(candidate))
            .map((candidate) => candidate.textContent?.trim() ?? '')
                .filter((text) => text !== '');
        }
    JS);
}

function selectedSearchResultText(object $page): string
{
    return $page->script(<<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const selectedItem = Array.from(document.querySelectorAll('[cmdk-item]'))
                .filter((candidate) => isVisible(candidate))
                .find((candidate) => candidate.getAttribute('aria-selected') === 'true' || candidate.getAttribute('data-selected') === 'true' || candidate.dataset.selected === 'true');

            return selectedItem?.textContent?.replace(/\s+/g, ' ').trim() ?? '';
        }
    JS);
}

function noMatchStateText(object $page): string
{
    return $page->script(<<<'JS'
        () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const commandSurface = Array.from(document.querySelectorAll('[cmdk-root], [data-slot="command-list"], [data-slot="command-empty"]'))
                .find((candidate) => isVisible(candidate));

            return commandSurface?.textContent?.replace(/\s+/g, ' ').trim() ?? '';
        }
    JS);
}

function currentLocation(object $page): string
{
    return $page->script(<<<'JS'
        () => `${window.location.pathname}${window.location.search}`
    JS);
}

function waitForLocationToContain(object $page, string $needle): bool
{
    $needleJson = json_encode($needle, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__NEEDLE__', $needleJson, <<<'JS'
        async () => {
            const needle = __NEEDLE__;
            const startedAt = Date.now();

            while (Date.now() - startedAt < 3000) {
                const current = `${window.location.pathname}${window.location.search}`;

                if (current.includes(needle)) {
                    return true;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 50));
            }

            return false;
        }
    JS));
}

function waitForMobileSheetToClose(object $page): bool
{
    return $page->script(<<<'JS'
        async () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const startedAt = Date.now();

            while (Date.now() - startedAt < 3000) {
                const openSheet = Array.from(document.querySelectorAll('[data-slot="sheet-content"], [role="dialog"]')).find((candidate) => isVisible(candidate));

                if (! openSheet) {
                    return true;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 50));
            }

            return false;
        }
    JS);
}

function waitForVisibleButtonByText(object $page, string $text): bool
{
    $textJson = json_encode($text, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__TEXT__', $textJson, <<<'JS'
        async () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const text = __TEXT__;
            const startedAt = Date.now();

            while (Date.now() - startedAt < 3000) {
                const button = Array.from(document.querySelectorAll('button')).find((candidate) =>
                    candidate.textContent?.trim() === text && isVisible(candidate)
                );

                if (button) {
                    return true;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 50));
            }

            return false;
        }
    JS));
}

function clickVisibleButtonByText(object $page, string $text): bool
{
    $textJson = json_encode($text, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__TEXT__', $textJson, <<<'JS'
        () => {
            const text = __TEXT__;
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };
            const button = Array.from(document.querySelectorAll('button, [cmdk-item], [role="option"]')).find((candidate) =>
                candidate.textContent?.trim() === text && isVisible(candidate)
            );

            button?.click();

            return Boolean(button);
        }
    JS));
}
