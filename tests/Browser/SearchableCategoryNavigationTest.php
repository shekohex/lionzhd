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

        searchableNavigationSeedMovieFixture();

        $page = searchableNavigationLoginAndVisitPage($user, route('movies'))
            ->resize(1280, 900)
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationSearchInputAppearsBelowSidebarTitle($page, 'Movie Categories'))->toBeTrue();

        expect(searchableNavigationClickVisibleButtonByText($page, 'Comedy'))->toBeTrue();

        $page->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        searchableNavigationTypeInlineSearchQuery($page, 'dra');

        expect(searchableNavigationSearchInputValue($page))->toBe('dra');

        $results = searchableNavigationVisibleSearchResults($page);

        expect($results)->not->toContain('All categories');
        expect($results)->not->toContain('Comedy');
        expect($results)->not->toContain('Uncategorized');
        expect($results)->toBe(['Drama', 'Dramedy', 'Action Drama']);
        expect(searchableNavigationVisibleHighlightedSegments($page))->toContain('Dra');

        $page->assertSee('dra')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationPressSearchKey($page, 'ArrowDown'))->toBe('Dramedy');
        expect(searchableNavigationPressSearchKey($page, 'ArrowDown'))->toBe('Action Drama');
        expect(searchableNavigationPressSearchKey($page, 'Enter'))->toBeTrue();

        expect(searchableNavigationWaitForLocationToContain($page, 'category=movie-action-drama'))->toBeTrue();
    })->group('browser');

    it('desktop series search ranks fuzzy hits, hides all categories, and keeps uncategorized last', function (): void {
        $user = User::factory()->create();

        searchableNavigationSeedSeriesFixture();

        $page = searchableNavigationLoginAndVisitPage($user, route('series'))
            ->resize(1280, 900)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationSearchInputAppearsBelowSidebarTitle($page, 'Series Categories'))->toBeTrue();

        expect(searchableNavigationClickVisibleButtonByText($page, 'Comedy'))->toBeTrue();

        $page->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        searchableNavigationTypeInlineSearchQuery($page, 'dra');

        expect(searchableNavigationSearchInputValue($page))->toBe('dra');

        $results = searchableNavigationVisibleSearchResults($page);

        expect($results)->not->toContain('All categories');
        expect($results)->not->toContain('Comedy');
        expect($results)->not->toContain('Uncategorized');
        expect($results)->toBe(['Drama', 'Dramedy', 'Action Drama']);
        expect(searchableNavigationVisibleHighlightedSegments($page))->toContain('Dra');

        $page->assertSee('dra')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationPressSearchKey($page, 'ArrowDown'))->toBe('Dramedy');
        expect(searchableNavigationPressSearchKey($page, 'ArrowDown'))->toBe('Action Drama');
        expect(searchableNavigationPressSearchKey($page, 'Enter'))->toBeTrue();

        expect(searchableNavigationWaitForLocationToContain($page, 'category=series-action-drama'))->toBeTrue();
    })->group('browser');

    it('desktop movie search keeps uncategorized last and offers guided clear-search recovery', function (): void {
        $user = User::factory()->create();

        searchableNavigationSeedMovieFixture();

        $page = searchableNavigationLoginAndVisitPage($user, route('movies'))
            ->resize(1280, 900)
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors();

        searchableNavigationTypeInlineSearchQuery($page, 'unc');

        $uncategorizedResults = searchableNavigationVisibleSearchResults($page);

        expect(array_key_last($uncategorizedResults))->not->toBeNull();
        expect($uncategorizedResults[array_key_last($uncategorizedResults)])->toBe('Uncategorized');

        searchableNavigationTypeInlineSearchQuery($page, 'zzz');

        expect(searchableNavigationNoMatchStateText($page))->toContain('No categories match your search.');
        expect(searchableNavigationNoMatchStateText($page))->toContain('Try a different category name or clear the current query.');
        expect(searchableNavigationNoMatchStateText($page))->not->toContain('hidden');
        expect(searchableNavigationClickVisibleButtonByText($page, 'Clear search'))->toBeTrue();
        expect(searchableNavigationSearchInputValue($page))->toBe('');
    })->group('browser');

    it('desktop series search keeps uncategorized last and offers guided clear-search recovery', function (): void {
        $user = User::factory()->create();

        searchableNavigationSeedSeriesFixture();

        $page = searchableNavigationLoginAndVisitPage($user, route('series'))
            ->resize(1280, 900)
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors();

        searchableNavigationTypeInlineSearchQuery($page, 'unc');

        $uncategorizedResults = searchableNavigationVisibleSearchResults($page);

        expect(array_key_last($uncategorizedResults))->not->toBeNull();
        expect($uncategorizedResults[array_key_last($uncategorizedResults)])->toBe('Uncategorized');

        searchableNavigationTypeInlineSearchQuery($page, 'zzz');

        expect(searchableNavigationNoMatchStateText($page))->toContain('No categories match your search.');
        expect(searchableNavigationNoMatchStateText($page))->toContain('Try a different category name or clear the current query.');
        expect(searchableNavigationNoMatchStateText($page))->not->toContain('hidden');
        expect(searchableNavigationClickVisibleButtonByText($page, 'Clear search'))->toBeTrue();
        expect(searchableNavigationSearchInputValue($page))->toBe('');
    })->group('browser');

    it('mobile movie search works in browse and manage modes, closes on select, and resets on reopen', function (): void {
        $user = User::factory()->create();

        searchableNavigationSeedMovieFixture();
        searchableNavigationUpdateCategoryPreferences($user, MediaType::Movie, route('movies'), [
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

        expect(searchableNavigationOpenMobileSheet($page, 'Movie Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationSearchInputAppearsNearMobileSheetTop($page, 'Movie Categories'))->toBeTrue();
        expect(searchableNavigationSearchInputIsFocused($page))->toBeFalse();

        searchableNavigationTypeInlineSearchQuery($page, 'dram');

        $browseResults = searchableNavigationVisibleSearchResults($page);

        expect($browseResults)->not->toContain('All categories');
        expect($browseResults)->toContain('Drama');
        expect($browseResults)->toContain('Action Drama');
        expect(implode(' ', $browseResults))->toContain('Dramedy');

        expect(searchableNavigationClickVisibleButtonByText($page, 'Drama'))->toBeTrue();

        expect(searchableNavigationWaitForLocationToContain($page, 'category=movie-drama'))->toBeTrue();
        expect(searchableNavigationWaitForMobileSheetToClose($page))->toBeTrue();
        $page->assertNoJavaScriptErrors();
        expect(searchableNavigationWaitForVisibleButtonByText($page, 'Movie Categories'))->toBeTrue();

        $page->assertNoJavaScriptErrors();

        expect(searchableNavigationOpenMobileSheet($page, 'Movie Categories'))->toBeTrue();
        expect(searchableNavigationSearchInputValue($page))->toBe('');
        expect(searchableNavigationSearchInputIsFocused($page))->toBeFalse();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationClickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        searchableNavigationTypeInlineSearchQuery($page, 'dram');

        $manageResults = searchableNavigationVisibleSearchResults($page);

        expect($manageResults)->not->toContain('All categories');
        expect($manageResults)->toContain('Drama');
        expect($manageResults)->toContain('Action Drama');
        expect(implode(' ', $manageResults))->toContain('Dramedy');

        searchableNavigationTypeInlineSearchQuery($page, 'com');

        expect(searchableNavigationNoMatchStateText($page))->toContain('No categories match your search.');
        expect(implode(' ', searchableNavigationVisibleSearchResults($page)))->not->toContain('Comedy');
    })->group('browser');

    it('mobile series search works in browse and manage modes, closes on select, and resets on reopen', function (): void {
        $user = User::factory()->create();

        searchableNavigationSeedSeriesFixture();
        searchableNavigationUpdateCategoryPreferences($user, MediaType::Series, route('series'), [
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

        expect(searchableNavigationOpenMobileSheet($page, 'Series Categories'))->toBeTrue();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationSearchInputAppearsNearMobileSheetTop($page, 'Series Categories'))->toBeTrue();
        expect(searchableNavigationSearchInputIsFocused($page))->toBeFalse();

        searchableNavigationTypeInlineSearchQuery($page, 'dram');

        $browseResults = searchableNavigationVisibleSearchResults($page);

        expect($browseResults)->not->toContain('All categories');
        expect($browseResults)->toContain('Drama');
        expect($browseResults)->toContain('Action Drama');
        expect(implode(' ', $browseResults))->toContain('Dramedy');

        expect(searchableNavigationClickVisibleButtonByText($page, 'Drama'))->toBeTrue();

        expect(searchableNavigationWaitForLocationToContain($page, 'category=series-drama'))->toBeTrue();
        expect(searchableNavigationWaitForMobileSheetToClose($page))->toBeTrue();
        expect(searchableNavigationWaitForVisibleButtonByText($page, 'Series Categories'))->toBeTrue();

        $page->assertNoJavaScriptErrors();

        expect(searchableNavigationOpenMobileSheet($page, 'Series Categories'))->toBeTrue();
        expect(searchableNavigationSearchInputValue($page))->toBe('');
        expect(searchableNavigationSearchInputIsFocused($page))->toBeFalse();

        $page->waitForText('Manage')
            ->assertNoJavaScriptErrors();

        expect(searchableNavigationClickVisibleButtonByText($page, 'Manage'))->toBeTrue();

        $page->waitForText('Preferences')
            ->assertNoJavaScriptErrors();

        searchableNavigationTypeInlineSearchQuery($page, 'dram');

        $manageResults = searchableNavigationVisibleSearchResults($page);

        expect($manageResults)->not->toContain('All categories');
        expect($manageResults)->toContain('Drama');
        expect($manageResults)->toContain('Action Drama');
        expect(implode(' ', $manageResults))->toContain('Dramedy');

        searchableNavigationTypeInlineSearchQuery($page, 'com');

        expect(searchableNavigationNoMatchStateText($page))->toContain('No categories match your search.');
        expect(implode(' ', searchableNavigationVisibleSearchResults($page)))->not->toContain('Comedy');
    })->group('browser');
}

function searchableNavigationLoginAndVisitPage(User $user, string $url): object
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

function searchableNavigationCreateMovieCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);
}

function searchableNavigationCreateSeriesCategory(string $providerId, string $name): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);
}

function searchableNavigationSeedMovieFixture(): void
{
    searchableNavigationCreateMovieCategory('movie-drama', 'Drama');
    searchableNavigationCreateMovieCategory('movie-action-drama', 'Action Drama');
    searchableNavigationCreateMovieCategory('movie-dramedy', 'Dramedy');
    searchableNavigationCreateMovieCategory('movie-comedy', 'Comedy');
    searchableNavigationCreateMovieCategory('movie-uncategorized', 'Uncategorized');

    searchableNavigationSeedMovieRecord(71_001, 'Drama Story', 'movie-drama');
    searchableNavigationSeedMovieRecord(71_002, 'Action Drama Story', 'movie-action-drama');
    searchableNavigationSeedMovieRecord(71_003, 'Dramedy Story', 'movie-dramedy');
    searchableNavigationSeedMovieRecord(71_004, 'Comedy Story', 'movie-comedy');
    searchableNavigationSeedMovieRecord(71_005, 'Uncategorized Movie', 'movie-uncategorized');
}

function searchableNavigationSeedSeriesFixture(): void
{
    searchableNavigationCreateSeriesCategory('series-drama', 'Drama');
    searchableNavigationCreateSeriesCategory('series-action-drama', 'Action Drama');
    searchableNavigationCreateSeriesCategory('series-dramedy', 'Dramedy');
    searchableNavigationCreateSeriesCategory('series-comedy', 'Comedy');
    searchableNavigationCreateSeriesCategory('series-uncategorized', 'Uncategorized');

    searchableNavigationSeedSeriesRecord(72_001, 'Drama Nights', 'series-drama');
    searchableNavigationSeedSeriesRecord(72_002, 'Action Drama Nights', 'series-action-drama');
    searchableNavigationSeedSeriesRecord(72_003, 'Dramedy Nights', 'series-dramedy');
    searchableNavigationSeedSeriesRecord(72_004, 'Comedy Nights', 'series-comedy');
    searchableNavigationSeedSeriesRecord(72_005, 'Uncategorized Nights', 'series-uncategorized');
}

function searchableNavigationSeedMovieRecord(int $streamId, string $name, string $categoryId): void
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

function searchableNavigationSeedSeriesRecord(int $seriesId, string $name, string $categoryId): void
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

function searchableNavigationUpdateCategoryPreferences(User $user, MediaType $mediaType, string $from, array $payload): void
{
    test()->actingAs($user)
        ->from($from)
        ->patch(route('category-preferences.update', ['mediaType' => $mediaType->value]), $payload)
        ->assertRedirect($from)
        ->assertSessionHasNoErrors();
}

function searchableNavigationOpenMobileSheet(object $page, string $triggerText): bool
{
    return searchableNavigationClickVisibleButtonByText($page, $triggerText);
}

function searchableNavigationTypeInlineSearchQuery(object $page, string $query): void
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

function searchableNavigationVisibleSearchResults(object $page): array
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

function searchableNavigationSelectSearchResultWithKeyboard(object $page, int $arrowDownCount): bool
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

function searchableNavigationPressSearchKey(object $page, string $key): string|bool
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

function searchableNavigationSearchInputValue(object $page): string
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

function searchableNavigationSearchInputAppearsBelowSidebarTitle(object $page, string $title): bool
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

function searchableNavigationSearchInputAppearsNearMobileSheetTop(object $page, string $title): bool
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

function searchableNavigationSearchInputIsFocused(object $page): bool
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

function searchableNavigationVisibleHighlightedSegments(object $page): array
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

function searchableNavigationSelectedSearchResultText(object $page): string
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

function searchableNavigationNoMatchStateText(object $page): string
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

function searchableNavigationCurrentLocation(object $page): string
{
    return $page->script(<<<'JS'
        () => `${window.location.pathname}${window.location.search}`
    JS);
}

function searchableNavigationWaitForLocationToContain(object $page, string $needle): bool
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

function searchableNavigationWaitForMobileSheetToClose(object $page): bool
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

function searchableNavigationWaitForVisibleButtonByText(object $page, string $text): bool
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

function searchableNavigationClickVisibleButtonByText(object $page, string $text): bool
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
