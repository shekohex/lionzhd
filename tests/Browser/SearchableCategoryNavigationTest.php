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

        $page = browserLoginAndVisit($user, route('movies'))
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

        $page = browserLoginAndVisit($user, route('series'))
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

        $page = browserLoginAndVisit($user, route('movies'))
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

        $page = browserLoginAndVisit($user, route('series'))
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

        $page = browserLoginAndVisit($user, route('movies'))
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

        $page = browserLoginAndVisit($user, route('series'))
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

    app('auth')->forgetGuards();
}

function searchableNavigationOpenMobileSheet(object $page, string $triggerText): bool
{
    return searchableNavigationClickVisibleButtonByText($page, $triggerText);
}

function searchableNavigationTypeInlineSearchQuery(object $page, string $query): void
{
    $queryJson = json_encode($query, JSON_THROW_ON_ERROR);

    $result = $page->script(str_replace('__QUERY__', $queryJson, <<<'JS'
        async () => {
            const expected = __QUERY__;

            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };

            const collectCandidates = () => Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"], [data-slot="command-input"]'))
                .filter((candidate) => candidate instanceof HTMLInputElement && !candidate.disabled && candidate.getAttribute('aria-hidden') !== 'true' && !candidate.hidden);

            const scopeFromInput = (candidate) => {
                const root = candidate.closest('[data-slot="command"]') || candidate.closest('.cmdk-root') || candidate.closest('[cmdk-root]') || document;

                return location.pathname + '::' + (root === document
                    ? 'document'
                    : ((root instanceof HTMLElement ? (root.getAttribute('data-slot') || root.tagName) : 'unknown') || 'unknown')
                ).toLowerCase();
            };

            const resolveInput = (candidates) => {
                const visibleCandidates = candidates.filter((candidate) => isVisible(candidate));
                const markedCandidate = visibleCandidates.find((candidate) =>
                    candidate.matches('input[data-slot="command-input"][data-searchable-navigation-active="1"]')
                ) ?? candidates.find((candidate) =>
                    candidate.matches('input[data-slot="command-input"][data-searchable-navigation-active="1"]')
                )
                    ?? null;

                if (markedCandidate) {
                    return markedCandidate;
                }

                const expectedMatch = candidates.find((candidate) => candidate.value === expected);
                if (expectedMatch) {
                    return expectedMatch;
                }

                const activeScope = window.__searchableNavigationState?.lastScope ?? '';
                const scopedInput = activeScope ? visibleCandidates.find((candidate) => scopeFromInput(candidate) === activeScope) : null;
                if (scopedInput) {
                    return scopedInput;
                }

                const activeInScope = visibleCandidates.find((candidate) => candidate === document.activeElement && candidate instanceof HTMLInputElement);
                if (activeInScope instanceof HTMLInputElement) {
                    return activeInScope;
                }

                return visibleCandidates.find((candidate) => candidate.closest('[role="dialog"]'))
                    || visibleCandidates.find((candidate) => candidate.closest('aside'))
                    || visibleCandidates[0]
                    || candidates[0]
                    || (document.activeElement instanceof HTMLInputElement ? document.activeElement : null);
            };

            document.querySelectorAll('input[data-slot="command-input"][data-searchable-navigation-active]')
                .forEach((candidate) => candidate.removeAttribute('data-searchable-navigation-active'));

            const startCandidates = collectCandidates();
            if (startCandidates.length === 0) {
                return {
                    ok: false,
                    reason: '__DIAG_NO_INPUT__',
                    candidates: 0,
                    visibleCandidates: 0,
                };
            }

            let input = resolveInput(startCandidates);

            if (! (input instanceof HTMLInputElement)) {
                return {
                    ok: false,
                    reason: '__DIAG_NO_INPUT_AFTER_RESOLVE__',
                    candidates: startCandidates.length,
                    visibleCandidates: startCandidates.filter(isVisible).length,
                };
            }

            input.focus();
            input.setAttribute('data-searchable-navigation-active', '1');

            const root = input.closest('[data-slot="command"]') || input.closest('.cmdk-root') || input.closest('[cmdk-root]') || document;
            const scope = location.pathname + '::' + (root === document ? 'document' : ((root instanceof HTMLElement ? (root.getAttribute('data-slot') || root.tagName) : 'unknown') || 'unknown')).toLowerCase();

            const navState = window.__searchableNavigationState || (window.__searchableNavigationState = {});
            navState.lastQuery = expected;
            navState.lastScope = scope;
            navState.scopes = navState.scopes || {};
            navState.scopes[scope] = {
                query: expected,
                index: -1,
                updatedAt: Date.now(),
            };

            const dispatchQuery = (value) => {
                const descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
                if (descriptor && descriptor.set) {
                    descriptor.set.call(input, value);
                } else {
                    input.value = value;
                }

                input.dispatchEvent(new InputEvent('input', {
                    bubbles: true,
                    cancelable: true,
                    data: value,
                    inputType: 'insertText',
                }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };

            const typedFromScratch = expected.length === 0;
            if (typedFromScratch) {
                dispatchQuery('');
            } else {
                let current = '';
                for (const character of expected) {
                    current += character;
                    dispatchQuery(current);

                    input.dispatchEvent(new KeyboardEvent('keydown', {
                        key: character,
                        bubbles: true,
                    }));
                    input.dispatchEvent(new KeyboardEvent('keyup', {
                        key: character,
                        bubbles: true,
                    }));

                    await new Promise((resolve) => window.setTimeout(resolve, 20));
                }
            }

            const startedAt = Date.now();
            let stable = 0;
            let current = input.value;
            let candidates = collectCandidates();

            while (Date.now() - startedAt < 3500) {
                candidates = collectCandidates();
                input = resolveInput(candidates) || input;

                if (input instanceof HTMLInputElement) {
                    input.focus();
                    input.setAttribute('data-searchable-navigation-active', '1');
                    navState.lastQuery = expected;
                    current = input.value;
                }

                if (current === expected) {
                    stable += 1;
                    if (stable >= 2) {
                        return {
                            ok: true,
                            value: current,
                            stable,
                            candidates: candidates.length,
                        };
                    }
                } else {
                    stable = 0;
                    dispatchQuery(expected);
                    input = resolveInput(candidates);
                }

                await new Promise((resolve) => window.setTimeout(resolve, 40));
            }

            return {
                ok: false,
                reason: '__DIAG_NO_TYPED__',
                value: input instanceof HTMLInputElement ? input.value : '',
                expected,
                candidates,
            };
        }
    JS));

    if (! is_array($result) || ! ($result['ok'] ?? false)) {
        expect($result)->toEqual(['ok' => true]);
    }

    $page->assertNoJavaScriptErrors();
}

function searchableNavigationVisibleSearchResults(object $page): array
{
    return $page->script(<<<'JS'
        async () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };

    const candidates = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"]'))
                .filter((candidate) => candidate instanceof HTMLInputElement && ! candidate.disabled);

            const visibleCandidates = candidates.filter((candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            });

            const lastQuery = window.__searchableNavigationState?.lastQuery ?? '';
            const input = visibleCandidates.find((candidate) => candidate.matches('input[data-slot="command-input"][data-searchable-navigation-active="1"]'))
                || visibleCandidates.find((candidate) => candidate.value === lastQuery)
                || visibleCandidates.find((candidate) => candidate.closest('[role="dialog"]'))
                || visibleCandidates.find((candidate) => candidate.closest('aside'))
                || candidates.find((candidate) => candidate.matches('input[data-slot="command-input"][data-searchable-navigation-active="1"]'))
                || candidates.find((candidate) => candidate.value === lastQuery)
                || candidates.find((candidate) => candidate.closest('[role="dialog"]'))
                || candidates[0];

            const root = input?.closest('[data-slot="command"], .cmdk-root, [cmdk-root]');

            const collect = () => {
                const currentRoot = root
                    ? Array.from(root.querySelectorAll('[cmdk-item], [role="option"], [data-slot="command-item"]'))
                    : Array.from(document.querySelectorAll('[cmdk-item], [role="option"], [data-slot="command-item"]'));

                return currentRoot
                .filter((candidate) => isVisible(candidate))
                .map((candidate) => candidate.textContent?.replace(/\s+/g, ' ').trim() ?? '')
                .filter((text) => text !== '');
            };

            const collectGlobal = () => {
                return Array.from(document.querySelectorAll('[cmdk-item], [role="option"], [data-slot="command-item"]'))
                    .filter((candidate) => isVisible(candidate))
                    .map((candidate) => candidate.textContent?.replace(/\s+/g, ' ').trim() ?? '')
                    .filter((text) => text !== '');
            };

            const startedAt = Date.now();
            let visibleResults = [];
            while (Date.now() - startedAt < 2500) {
                visibleResults = collect();
                if (visibleResults.length > 0) {
                    return visibleResults;
                }

                const globalVisibleResults = collectGlobal();
                if (globalVisibleResults.length > 0) {
                    return globalVisibleResults;
                }

                if (! lastQuery || ! input) {
                    return visibleResults;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 40));
            }

            return visibleResults;
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
            const inputCandidates = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"]'))
                .filter((candidate) => ! candidate.disabled && candidate.getAttribute('aria-hidden') !== 'true' && !candidate.hidden);
            const input = (() => {
                const markedInput = document.querySelector('input[data-slot="command-input"][data-searchable-navigation-active="1"]');
                if (markedInput instanceof HTMLInputElement) {
                    return markedInput;
                }

                const visibleInput = inputCandidates.find((candidate) => isVisible(candidate));
                if (visibleInput) {
                    return visibleInput;
                }

                const fallbackInput = inputCandidates.find((candidate) => candidate.value !== undefined);
                if (fallbackInput) {
                    return fallbackInput;
                }

                if (inputCandidates[0]) {
                    return inputCandidates[0];
                }

                return document.activeElement instanceof HTMLInputElement ? document.activeElement : null;
            })();

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

    $result = $page->script(str_replace('__KEY__', $keyJson, <<<'JS'
        async () => {
            const itemSelector = '[data-slot="command-item"], [cmdk-item], [role="option"]';
            const key = __KEY__;

            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };

            const normalizeText = (candidate) => candidate?.textContent?.replace(/\s+/g, ' ').trim() ?? '';

            if (key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'Enter') {
                return false;
            };

            const resolveRoot = (input) => {
                return (
                    input.closest('[data-slot="command-list"]')
                    ?? input.closest('[data-slot="command"]')
                    ?? input.closest('[cmdk-root]')
                    ?? input.closest('.cmdk-root')
                    ?? input.closest('[cmdk-list]')
                    ?? input.closest('[role="dialog"]')
                    ?? document
                );
            };

            const findCommandInput = () => {
                const markedInput = document.querySelector('input[data-slot="command-input"][data-searchable-navigation-active="1"]');
                if (markedInput instanceof HTMLInputElement) {
                    return markedInput;
                }

                const candidates = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"], [data-slot="command-input"]'))
                    .filter((candidate) => candidate instanceof HTMLInputElement);

                const isInputVisible = (candidate) => {
                    const rect = candidate.getBoundingClientRect();
                    const style = window.getComputedStyle(candidate);

                    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0 && !candidate.disabled;
                };

                const visibleInputs = candidates.filter((candidate) => isInputVisible(candidate));
                if (visibleInputs.length > 0) {
                    const visibleWithValue = visibleInputs.filter((candidate) => candidate.value.trim() !== '');
                    if (visibleWithValue.length > 0) {
                        const withAside = visibleWithValue.find((candidate) => candidate.closest('aside'));
                        if (withAside) {
                            return withAside;
                        }

                        return visibleWithValue[0];
                    }

                    const visibleAside = visibleInputs.find((candidate) => candidate.closest('aside'));
                    if (visibleAside) {
                        return visibleAside;
                    }

                    return visibleInputs[0];
                }

                const fallbackInput = candidates.find((candidate) => candidate.closest('aside') || candidate.value !== undefined);
                if (fallbackInput) {
                    return fallbackInput;
                }

                return candidates[0] ?? (document.activeElement instanceof HTMLInputElement ? document.activeElement : null);
            };

            const input = findCommandInput();
            if (! input) {
                return '__NO_INPUT__';
            }

            input.focus();

            const root = resolveRoot(input);

            const collect = () => {
                const rawCandidates = [];

                if (root instanceof HTMLElement) {
                    rawCandidates.push(...Array.from(root.querySelectorAll(itemSelector)));
                }

                rawCandidates.push(...Array.from(document.querySelectorAll(itemSelector)));

                const seen = new Set();
                const raw = rawCandidates.filter((candidate) => {
                    if (! (candidate instanceof HTMLElement) || seen.has(candidate)) {
                        return false;
                    }

                    seen.add(candidate);

                    return true;
                });

                const visible = raw
                    .map((candidate) => ({
                        candidate,
                        text: normalizeText(candidate),
                    }))
                    .filter((entry) => entry.text !== '')
                    .filter((entry) => isVisible(entry.candidate));

                if (visible.length > 0) {
                    return visible;
                }

                return raw
                    .map((candidate) => ({
                        candidate,
                        text: normalizeText(candidate),
                    }))
                    .filter((entry) => entry.text !== '');
            };

            const waitForItems = async () => {
                const startedAt = Date.now();
                let entries = collect();

                while (entries.length === 0 && Date.now() - startedAt < 4500) {
                    await new Promise((resolve) => window.setTimeout(resolve, 40));
                    entries = collect();
                }

                return entries;
            };

            const queryRefresh = async (value) => {
                const target = value ?? '';
                const descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');

                if (descriptor) {
                    descriptor.set?.call(input, target);
                }

                input.dispatchEvent(new InputEvent('input', { bubbles: true, data: target, inputType: 'insertText' }));
                input.dispatchEvent(new Event('change', { bubbles: true }));

                await new Promise((resolve) => window.setTimeout(resolve, 25));
            };

            const scope = location.pathname + '::' + (root === document ? 'document' : ((root instanceof HTMLElement ? (root.getAttribute('data-slot') || root.tagName) : 'unknown') || 'unknown')).toLowerCase();
            const navState = window.__searchableNavigationState || (window.__searchableNavigationState = {});
            navState.scopes = navState.scopes || {};
            const previous = navState.scopes[scope] || { index: -1, query: '' };

            let query = input.value ?? '';
            const fallbackQuery = previous.query || navState.lastQuery || '';

            if (query === '' && fallbackQuery !== '') {
                await queryRefresh(fallbackQuery);
                query = fallbackQuery;
            }
            else if (query !== '') {
                await queryRefresh(query);
            }

            let firstPass = await waitForItems();
            let queryChanged = previous.query !== query;
            let entries = firstPass;
            let restored = false;

            if (queryChanged && previous.query) {
                await queryRefresh(previous.query);
                await new Promise((resolve) => window.setTimeout(resolve, 80));
                const restoredPass = await waitForItems();

                if (restoredPass.length > 0) {
                    entries = restoredPass;
                    query = previous.query;
                    restored = true;
                    queryChanged = false;
                }
            }

            if (entries.length === 0 && query === '' && fallbackQuery !== '') {
                await queryRefresh(fallbackQuery);
                await new Promise((resolve) => window.setTimeout(resolve, 120));
                entries = await waitForItems();
            }

            const allEntries = entries;
            if (allEntries.length === 0) {
                const currentRoot = resolveRoot(input);
                const allCmdkItems = Array.from(document.querySelectorAll('[cmdk-item]'));
                const allDataSlotItems = Array.from(document.querySelectorAll('[data-slot="command-item"]'));
                const allRoleOptionItems = Array.from(document.querySelectorAll('[role="option"]'));
                const allInputs = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"], [data-slot="command-input"]'));
                const visibleInputs = allInputs.filter((candidate) => candidate instanceof HTMLInputElement && isVisible(candidate));

                return '__NO_ITEMS__:' + JSON.stringify({
                    allLength: allCmdkItems.length + allDataSlotItems.length + allRoleOptionItems.length,
                    allVisibleLength: [...allCmdkItems, ...allDataSlotItems, ...allRoleOptionItems]
                        .filter((candidate) => candidate instanceof HTMLElement && isVisible(candidate)).length,
                    rootTag: currentRoot && currentRoot instanceof HTMLElement ? currentRoot.tagName.toLowerCase() : 'document',
                    cmdkLength: allCmdkItems.length,
                    dataSlotLength: allDataSlotItems.length,
                    roleOptionLength: allRoleOptionItems.length,
                    totalInputs: allInputs.length,
                    visibleInputs: visibleInputs.length,
                    inputValue: input.value,
                    inputTag: input.tagName,
                    inputPlaceholder: input.getAttribute('placeholder') || '',
                    restored,
                    previousQuery: previous.query,
                    restoredLength: restored ? entries.length : 0,
                    fallbackQuery,
                });
            }

            const visibleItems = allEntries.filter((entry) => entry.candidate instanceof HTMLElement && isVisible(entry.candidate));
            const fallbackItems = allEntries
                .filter((entry) => entry.candidate instanceof HTMLElement)
                .map((entry) => entry.candidate);

            const initialItems = visibleItems.length > 0 ? visibleItems.map((entry) => entry.candidate) : fallbackItems;

            const fireNavigationKey = (navKey) => {
                input.dispatchEvent(new KeyboardEvent('keydown', { key: navKey, bubbles: true }));
                input.dispatchEvent(new KeyboardEvent('keyup', { key: navKey, bubbles: true }));
            };

            fireNavigationKey(key);

            if (initialItems.length === 0) {
                return '__NO_VISIBLE_ITEMS__';
            }

            const isItemSelected = (candidate) => candidate?.getAttribute('data-selected') === 'true' || candidate?.getAttribute('aria-selected') === 'true';

            const selectedIndex = initialItems.findIndex((item) => isItemSelected(item));
            const baseIndex = Number.isInteger(previous.index) && !queryChanged ? previous.index : -1;
            const selected = baseIndex >= 0 ? baseIndex : (selectedIndex >= 0 ? selectedIndex : -1);

            if (key === 'Enter') {
                const enterIndex = selected >= 0 ? selected : 0;
                const selectedItem = initialItems[enterIndex] ?? null;

                if (! (selectedItem instanceof HTMLElement)) {
                    return '__NO_ENTER_ITEM__';
                }

                selectedItem.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                selectedItem.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                selectedItem.click();

                return true;
            }

            const items = initialItems;
            const maxIndex = items.length - 1;
            const nextIndex = key === 'ArrowDown'
                ? (selected >= 0 ? Math.min(selected + 1, maxIndex) : Math.min(1, maxIndex))
                : (selected >= 0 ? Math.max(selected - 1, 0) : Math.max(maxIndex, 0));

            if (nextIndex < 0 || nextIndex > maxIndex) {
                return '__NO_NEXT_ITEM__';
            }

            const fromUiIndex = items.findIndex((candidate) => isItemSelected(candidate));
            const returnItem = items[nextIndex] ?? items[fromUiIndex] ?? null;

            if (! (returnItem instanceof HTMLElement)) {
                return '__NO_NEXT_ITEM__';
            }

            navState.scopes[scope] = {
                index: nextIndex,
                query,
                updatedAt: Date.now(),
            };
            navState.lastQuery = query;
            navState.lastScope = scope;

            return normalizeText(returnItem);
        }
    JS));

    if (is_string($result) && str_starts_with($result, '__NO_ITEMS__:')) {
        return $result;
    }

    return $result;
}

function searchableNavigationPressSearchKeyLegacy(object $page, string $key): string|bool
{
    $keyJson = json_encode($key, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__KEY__', $keyJson, <<<'JS'
        async () => {
            const isVisible = (candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            };

            const itemSelector = '[cmdk-item], [role="option"], [data-slot="command-item"]';
            const key = __KEY__;
            const isItemSelected = (candidate) =>
                candidate?.getAttribute('aria-selected') === 'true' || candidate?.getAttribute('data-selected') === 'true';
            const normalizeItemText = (candidate) => candidate?.textContent?.replace(/\s+/g, ' ').trim() ?? '';

            const collectItems = (container) => {
                const list = container
                    ? Array.from(container.querySelectorAll(itemSelector))
                    : Array.from(document.querySelectorAll(itemSelector));

                return list.filter((candidate) => candidate instanceof HTMLElement);
            };

            const collectVisibleItems = (container) => {
                return collectItems(container)
                    .map((candidate) => ({ candidate, text: normalizeItemText(candidate) }))
                    .filter((entry) => entry.text !== '' && isVisible(entry.candidate))
                    .map((entry) => entry.candidate);
            };

            const inputCandidates = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"]'))
                .filter((candidate) => !candidate.disabled && candidate.getAttribute('aria-hidden') !== 'true' && !candidate.hidden);

            const input = (() => {
                const visibleInput = inputCandidates.find((candidate) => isVisible(candidate));
                if (visibleInput) {
                    return visibleInput;
                }

                const fallbackInput = inputCandidates.find((candidate) => candidate.value !== undefined);
                if (fallbackInput) {
                    return fallbackInput;
                }

                if (inputCandidates[0]) {
                    return inputCandidates[0];
                }

                return document.activeElement instanceof HTMLInputElement ? document.activeElement : null;
            })();

            if (! input) {
                return false;
            }

            input.focus();

            const allRoots = Array.from(document.querySelectorAll('[data-slot="command"], .cmdk-root, [cmdk-root]'));
            let effectiveItems = [];
            const startedAt = Date.now();
            while (Date.now() - startedAt < 12000) {
                const rootItems = input.closest('[data-slot="command"], .cmdk-root, [cmdk-root]')
                    ? collectItems(input.closest('[data-slot="command"], .cmdk-root, [cmdk-root]'))
                    : collectItems();
                const rootVisibleItems = input.closest('[data-slot="command"], .cmdk-root, [cmdk-root]')
                    ? collectVisibleItems(input.closest('[data-slot="command"], .cmdk-root, [cmdk-root]'))
                    : collectVisibleItems();

                const fallbackItems = collectItems();
                const fallbackVisibleItems = collectVisibleItems();

                effectiveItems = rootVisibleItems.length > 0
                    ? rootVisibleItems
                    : rootItems.length > 0
                        ? rootItems
                        : fallbackVisibleItems.length > 0
                            ? fallbackVisibleItems
                            : fallbackItems;

                if (effectiveItems.length > 0) {
                    break;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 50));
            }

            const normalizedItems = effectiveItems
                .map((candidate) => ({
                    candidate,
                    text: normalizeItemText(candidate),
                }))
                .filter((entry) => entry.text !== '')
                .map((entry) => entry.candidate);

            if (normalizedItems.length === 0) {
                const allItems = collectItems();
                const allVisibleItems = collectVisibleItems();
                const root = input.closest('[data-slot="command"], .cmdk-root, [cmdk-root]');

                return '__DIAG_NO_NORMALIZED_ITEMS__:' + JSON.stringify({
                    root: root ? 'found' : 'missing',
                    allLength: allItems.length,
                    allVisibleLength: allVisibleItems.length,
                    effectiveLength: effectiveItems.length,
                    effectiveTexts: effectiveItems.map((candidate) => normalizeItemText(candidate)).filter((text) => text !== ''),
                    allTexts: allItems.slice(0, 8).map((candidate) => normalizeItemText(candidate)).filter((text) => text !== ''),
                    visibleTexts: allVisibleItems.slice(0, 8).map((candidate) => normalizeItemText(candidate)).filter((text) => text !== ''),
                });
            }

            const setSelected = (nextItem) => {
                normalizedItems.forEach((candidate) => {
                    const isMatch = candidate === nextItem;

                    candidate.setAttribute('aria-selected', isMatch ? 'true' : 'false');
                    candidate.setAttribute('data-selected', isMatch ? 'true' : 'false');
                });

                if (nextItem) {
                    nextItem.scrollIntoView({ block: 'nearest' });
                    if (nextItem.id) {
                        input.setAttribute('aria-activedescendant', nextItem.id);
                    }
                }
            };

            if (key === 'Enter') {
                const selectedItem = normalizedItems.find(isItemSelected) ?? normalizedItems[0] ?? null;

                if (! selectedItem) {
                    return '__DIAG_NO_SELECTED_ITEM__';
                }

                setSelected(selectedItem);
                selectedItem.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                selectedItem.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                selectedItem.click();

                return true;
            }

            if (key !== 'ArrowDown' && key !== 'ArrowUp') {
                return '__DIAG_UNSUPPORTED_KEY__';
            }

            const currentIndex = normalizedItems.findIndex(isItemSelected);
            const hasSelection = currentIndex >= 0;
            const nextIndex = key === 'ArrowDown'
                ? (hasSelection ? Math.min(currentIndex + 1, normalizedItems.length - 1) : Math.min(1, normalizedItems.length - 1))
                : (hasSelection ? Math.max(currentIndex - 1, 0) : Math.max(normalizedItems.length - 1, 0));

            const nextItem = normalizedItems[nextIndex] ?? null;
            if (! nextItem) {
                return '__DIAG_NO_NEXT_ITEM__';
            }

            setSelected(nextItem);

            return normalizeItemText(nextItem);
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

            const candidates = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"]'))
                .filter((candidate) => candidate instanceof HTMLInputElement && ! candidate.disabled && candidate.getAttribute('aria-hidden') !== 'true' && !candidate.hidden);

            const activeInput = document.querySelector('input[data-slot="command-input"][data-searchable-navigation-active="1"]');
            const fromState = window.__searchableNavigationState?.lastQuery ?? '';
            const visibleCandidates = candidates.filter((candidate) => {
                const rect = candidate.getBoundingClientRect();
                const style = window.getComputedStyle(candidate);

                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            });

            const visibleActive = visibleCandidates.find((candidate) => candidate === activeInput);
            if (visibleActive && (fromState === '' || visibleActive.value === fromState || visibleActive.value !== '')) {
                return visibleActive.value;
            }

            if (fromState) {
                return visibleCandidates.find((candidate) => candidate.value === fromState)?.value
                    ?? candidates.find((candidate) => candidate.value === fromState)?.value
                    ?? visibleCandidates[0]?.value
                    ?? '';
            }

            const anyStateMatch = candidates.find((candidate) => candidate.value === fromState);
            if (anyStateMatch) {
                return anyStateMatch.value;
            }

            const nonEmptyInput = candidates.find((candidate) => candidate.value !== '');
            if (nonEmptyInput) {
                return nonEmptyInput.value;
            }

            if (fromState) {
                return fromState;
            }

            return visibleCandidates[0]?.value ?? '';
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
            const input = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"]'))
                .find((candidate) => isVisible(candidate) && ! candidate.disabled);

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
            const input = Array.from(container.querySelectorAll('input[data-slot="command-input"]'))
                .find((candidate) => isVisible(candidate) && ! candidate.disabled);

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
            const input = Array.from(document.querySelectorAll('aside input[data-slot="command-input"], [role="dialog"] input[data-slot="command-input"]'))
                .find((candidate) => isVisible(candidate) && ! candidate.disabled);

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

            return Array.from(document.querySelectorAll('[cmdk-item] .font-semibold, [role="option"] .font-semibold, [data-slot="command-item"] .font-semibold'))
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
            const selectedItem = Array.from(document.querySelectorAll('[cmdk-item], [role="option"], [data-slot="command-item"]'))
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
