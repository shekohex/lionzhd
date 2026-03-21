<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

if (! extension_loaded('sockets')) {
    it('requires the sockets extension for search mode browser tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('keeps full-page draft query out of url history until submit', function (): void {
        $user = User::factory()->create();

        searchModeUxSeedFixture();

        $page = searchModeUxLoginAndVisitPage($user, route('search.full', ['q' => 'Galaxy']))
            ->resize(1440, 960)
            ->waitForText('Search the entire media library')
            ->assertNoJavaScriptErrors();

        $initialHistoryLength = searchModeUxHistoryLength($page);

        expect(searchModeUxTypeSearchQuery($page, 'Galaxy Draft'))->toBeTrue();

        searchModeUxWait($page, 700);

        expect(searchModeUxSearchQueryValue($page))->toBe('Galaxy Draft');
        expect(searchModeUxCurrentLocation($page))->toContain('/search?q=Galaxy');
        expect(searchModeUxCurrentLocation($page))->not->toContain('Galaxy+Draft');
        expect(searchModeUxHistoryLength($page))->toBe($initialHistoryLength);
    })->group('browser');

    it('syncs mode tabs with url', function (): void {
        $user = User::factory()->create();

        searchModeUxSeedFixture();

        $page = searchModeUxLoginAndVisitPage($user, route('search.full', ['q' => 'Galaxy']))
            ->resize(1440, 960)
            ->waitForText('Search the entire media library')
            ->assertNoJavaScriptErrors();

        $initialHistoryLength = searchModeUxHistoryLength($page);

        expect(searchModeUxActiveMode($page))->toBe('all');
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('Galaxy');
        expect(searchModeUxSearchQueryValue($page))->toBe('Galaxy');

        expect(searchModeUxTypeSearchQuery($page, 'Galaxy Draft'))->toBeTrue();
        searchModeUxWait($page, 150);
        expect(searchModeUxSearchQueryValue($page))->toBe('Galaxy Draft');
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('Galaxy');
        expect(searchModeUxHistoryLength($page))->toBe($initialHistoryLength);

        expect(searchModeUxClickVisibleTab($page, 'Movies'))->toBeTrue();
        expect(searchModeUxWaitForQueryParam($page, 'media_type', 'movie'))->toBeTrue();
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('Galaxy Draft');
        expect(searchModeUxSearchQueryValue($page))->toBe('Galaxy Draft');
        expect(searchModeUxActiveMode($page))->toBe('movie');

        expect(searchModeUxClickVisibleTab($page, 'Sort By'))->toBeTrue();
        expect(searchModeUxClickVisibleTab($page, 'Rating'))->toBeTrue();
        expect(searchModeUxWaitForQueryParam($page, 'sort_by', 'rating'))->toBeTrue();
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('Galaxy Draft');
        expect(searchModeUxActiveMode($page))->toBe('movie');

        expect(searchModeUxClickVisibleTab($page, 'Reset search'))->toBeTrue();
        expect(searchModeUxWaitForQueryParam($page, 'q', ''))->toBeTrue();
        expect(searchModeUxCurrentQueryParam($page, 'media_type'))->toBe('movie');
        expect(searchModeUxCurrentQueryParam($page, 'sort_by'))->toBe('rating');
        expect(searchModeUxSearchQueryValue($page))->toBe('');

        expect(searchModeUxTypeSearchQuery($page, 'Nebula'))->toBeTrue();
        searchModeUxWait($page, 150);
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('');
        expect(searchModeUxSearchQueryValue($page))->toBe('Nebula');

        expect(searchModeUxSubmitSearch($page))->toBeTrue();
        expect(searchModeUxWaitForQueryParam($page, 'q', 'Nebula'))->toBeTrue();
        expect(searchModeUxCurrentQueryParam($page, 'media_type'))->toBe('movie');
        expect(searchModeUxCurrentQueryParam($page, 'sort_by'))->toBe('rating');
        expect(searchModeUxSearchQueryValue($page))->toBe('Nebula');

        searchModeUxHistoryBack($page);
        expect(searchModeUxWaitForQueryParam($page, 'q', ''))->toBeTrue();
        expect(searchModeUxSearchQueryValue($page))->toBe('');
        expect(searchModeUxActiveMode($page))->toBe('movie');

        searchModeUxHistoryBack($page);
        expect(searchModeUxWaitForQueryParam($page, 'q', 'Galaxy Draft'))->toBeTrue();
        expect(searchModeUxWaitForQueryParam($page, 'sort_by', 'rating'))->toBeTrue();
        expect(searchModeUxSearchQueryValue($page))->toBe('Galaxy Draft');
        expect(searchModeUxActiveMode($page))->toBe('movie');

        searchModeUxHistoryForward($page);
        expect(searchModeUxWaitForQueryParam($page, 'q', ''))->toBeTrue();

        searchModeUxHistoryForward($page);
        expect(searchModeUxWaitForQueryParam($page, 'q', 'Nebula'))->toBeTrue();

        expect(searchModeUxClickVisibleTab($page, 'TV Series'))->toBeTrue();
        expect(searchModeUxWaitForQueryParam($page, 'media_type', 'series'))->toBeTrue();
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('Nebula');
        expect(searchModeUxSearchQueryValue($page))->toBe('Nebula');
        expect(searchModeUxActiveMode($page))->toBe('series');

        expect(searchModeUxClickVisibleTab($page, 'All'))->toBeTrue();
        expect(searchModeUxWaitForQueryParam($page, 'media_type', null))->toBeTrue();
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('Nebula');
        expect(searchModeUxSearchQueryValue($page))->toBe('Nebula');
        expect(searchModeUxActiveMode($page))->toBe('all');

        searchModeUxHistoryBack($page);
        expect(searchModeUxWaitForQueryParam($page, 'media_type', 'series'))->toBeTrue();
        expect(searchModeUxCurrentQueryParam($page, 'q'))->toBe('Nebula');
        expect(searchModeUxActiveMode($page))->toBe('series');
    })->group('browser');

    it('renders filtered full width layout', function (): void {
        $user = User::factory()->create();

        searchModeUxSeedFixture();

        $page = searchModeUxLoginAndVisitPage($user, route('search.full', [
            'q' => 'Galaxy type:movie',
            'media_type' => 'movie',
        ]))
            ->resize(1440, 960)
            ->waitForText('Search the entire media library')
            ->assertNoJavaScriptErrors();

        expect(searchModeUxWaitForLocationToContain($page, 'media_type=movie'))->toBeTrue();
        expect(searchModeUxActiveMode($page))->toBe('movie');
        expect(searchModeUxActiveLayout($page))->toBe('filtered');
        expect(searchModeUxVisibleSectionHeadings($page))->toContain('Movies only');
        expect(searchModeUxVisibleSectionHeadings($page))->not->toContain('TV Series');
        expect(searchModeUxVisibleBodyText($page))->toContain('Movies only');
        expect(searchModeUxVisibleBodyText($page))->toContain('1 movie result for "Galaxy type:movie"');

        $emptyPage = searchModeUxLoginAndVisitPage($user, route('search.full', [
            'q' => 'Nothing type:movie',
            'media_type' => 'movie',
        ]))
            ->resize(1440, 960)
            ->waitForText('Search the entire media library')
            ->assertNoJavaScriptErrors();

        expect(searchModeUxWaitForLocationToContain($emptyPage, 'media_type=movie'))->toBeTrue();
        expect(searchModeUxActiveMode($emptyPage))->toBe('movie');
        expect(searchModeUxActiveLayout($emptyPage))->toBe('filtered');
        expect(searchModeUxVisibleBodyText($emptyPage))->toContain('No movies found');
        expect(searchModeUxVisibleBodyText($emptyPage))->toContain('Try editing or clearing your search query.');
        expect(searchModeUxVisibleBodyText($emptyPage))->not->toContain('Try searching TV series');
    })->group('browser');

    it('restores search state across refresh and history', function (): void {
        $user = User::factory()->create();

        searchModeUxSeedFixture();

        $page = searchModeUxLoginAndVisitPage($user, route('search.full', [
            'q' => 'Galaxy',
            'media_type' => 'movie',
            'sort_by' => 'rating',
            'page' => 2,
        ]))
            ->resize(1440, 960)
            ->waitForText('Search the entire media library')
            ->assertNoJavaScriptErrors();

        expect(searchModeUxWaitForSearchUrl($page, ['q=Galaxy', 'media_type=movie', 'sort_by=rating', 'page=2']))->toBeTrue();
        expect(searchModeUxActiveMode($page))->toBe('movie');

        searchModeUxHistoryBack($page);

        expect(searchModeUxWaitForLocationToContain($page, '/search'))->toBeTrue();

        searchModeUxHistoryForward($page);

        expect(searchModeUxWaitForSearchUrl($page, ['q=Galaxy', 'media_type=movie', 'sort_by=rating', 'page=2']))->toBeTrue();

        $page = searchModeUxRefreshPage($page)
            ->waitForText('Search the entire media library')
            ->assertNoJavaScriptErrors();

        expect(searchModeUxCurrentLocation($page))->toContain('media_type=movie');
        expect(searchModeUxCurrentLocation($page))->toContain('sort_by=rating');
        expect(searchModeUxCurrentLocation($page))->toContain('page=2');
        expect(searchModeUxActiveMode($page))->toBe('movie');
    })->group('browser');
}

function searchModeUxLoginAndVisitPage(User $user, string $url): object
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

function searchModeUxSeedFixture(): void
{
    config()->set('scout.driver', 'database');

    searchModeUxSeedMovieRecord(81_001, 'Galaxy Movie', 'movie-fixture');
    searchModeUxSeedMovieRecord(81_002, 'Nebula Movie', 'movie-fixture');

    searchModeUxSeedSeriesRecord(82_001, 'Galaxy Series', 'series-fixture');
    searchModeUxSeedSeriesRecord(82_002, 'Nebula Series', 'series-fixture');
}

function searchModeUxSeedMovieRecord(int $streamId, string $name, string $categoryId): void
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

function searchModeUxSeedSeriesRecord(int $seriesId, string $name, string $categoryId): void
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
        'releaseDate' => '2026-03-21',
        'rating' => 4.5,
        'rating_5based' => 4.5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function searchModeUxCurrentLocation(object $page): string
{
    return $page->script(<<<'JS'
        () => `${window.location.pathname}${window.location.search}`
    JS);
}

function searchModeUxCurrentQueryParam(object $page, string $parameter): ?string
{
    $parameterJson = json_encode($parameter, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__PARAMETER__', $parameterJson, <<<'JS'
        () => new URLSearchParams(window.location.search).get(__PARAMETER__)
    JS));
}

function searchModeUxHistoryLength(object $page): int
{
    return $page->script(<<<'JS'
        () => window.history.length
    JS);
}

function searchModeUxSearchQueryValue(object $page): ?string
{
    return $page->script(<<<'JS'
        () => document.querySelector('input[type="search"]')?.value ?? null
    JS);
}

function searchModeUxTypeSearchQuery(object $page, string $value): bool
{
    $valueJson = json_encode($value, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__VALUE__', $valueJson, <<<'JS'
        () => {
            const input = document.querySelector('input[type="search"]');

            if (! input) {
                return false;
            }

            const descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');

            descriptor?.set?.call(input, __VALUE__);
            input.dispatchEvent(new Event('input', { bubbles: true }));

            return true;
        }
    JS));
}

function searchModeUxWait(object $page, int $milliseconds): void
{
    $page->script(str_replace('__MILLISECONDS__', (string) $milliseconds, <<<'JS'
        async () => {
            await new Promise((resolve) => window.setTimeout(resolve, __MILLISECONDS__));

            return true;
        }
    JS));
}

function searchModeUxWaitForSearchUrl(object $page, array $needles): bool
{
    $needlesJson = json_encode($needles, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__NEEDLES__', $needlesJson, <<<'JS'
        async () => {
            const needles = __NEEDLES__;
            const startedAt = Date.now();

            while (Date.now() - startedAt < 3000) {
                const current = `${window.location.pathname}${window.location.search}`;

                if (needles.every((needle) => current.includes(needle))) {
                    return true;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 50));
            }

            return false;
        }
    JS));
}

function searchModeUxWaitForLocationToContain(object $page, string $needle): bool
{
    return searchModeUxWaitForSearchUrl($page, [$needle]);
}

function searchModeUxWaitForQueryParam(object $page, string $parameter, ?string $value): bool
{
    $parameterJson = json_encode($parameter, JSON_THROW_ON_ERROR);
    $valueJson = json_encode($value, JSON_THROW_ON_ERROR);

    return $page->script(str_replace(['__PARAMETER__', '__VALUE__'], [$parameterJson, $valueJson], <<<'JS'
        async () => {
            const parameter = __PARAMETER__;
            const value = __VALUE__;
            const startedAt = Date.now();

            while (Date.now() - startedAt < 3000) {
                const params = new URLSearchParams(window.location.search);
                const currentValue = params.get(parameter);

                if (value === null ? currentValue === null : currentValue === value) {
                    return true;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 50));
            }

            return false;
        }
    JS));
}

function searchModeUxHistoryBack(object $page): void
{
    $page->script(<<<'JS'
        () => {
            window.history.back();

            return true;
        }
    JS);
}

function searchModeUxHistoryForward(object $page): void
{
    $page->script(<<<'JS'
        () => {
            window.history.forward();

            return true;
        }
    JS);
}

function searchModeUxRefreshPage(object $page): object
{
    return visit(searchModeUxCurrentLocation($page));
}

function searchModeUxSubmitSearch(object $page): bool
{
    return $page->script(<<<'JS'
        () => {
            const input = document.querySelector('input[type="search"]');
            const form = input?.closest('form');

            if (! form) {
                return false;
            }

            form.requestSubmit();

            return true;
        }
    JS);
}

function searchModeUxActiveMode(object $page): ?string
{
    return $page->script(<<<'JS'
        () => {
            const activeTab = Array.from(document.querySelectorAll('[role="tab"]')).find((candidate) => {
                const selected = candidate.getAttribute('aria-selected') === 'true';
                const state = candidate.getAttribute('data-state') === 'active';

                return selected || state;
            });

            if (! activeTab) {
                return null;
            }

            const label = activeTab.textContent?.trim().toLowerCase() ?? '';

            if (label === 'all') {
                return 'all';
            }

            if (label === 'movies') {
                return 'movie';
            }

            if (label === 'tv series') {
                return 'series';
            }

            return label;
        }
    JS);
}

function searchModeUxActiveLayout(object $page): ?string
{
    return $page->script(<<<'JS'
        () => {
            const explicit = document.querySelector('[data-search-layout]');

            if (explicit) {
                return explicit.getAttribute('data-search-layout');
            }

            const headings = Array.from(document.querySelectorAll('h1, h2, h3')).map((candidate) => candidate.textContent?.trim() ?? '');
            const hasMovies = headings.some((text) => text.includes('Movies'));
            const hasSeries = headings.some((text) => text.includes('TV Series'));

            if (hasMovies && hasSeries) {
                return 'all';
            }

            if (hasMovies || hasSeries) {
                return 'filtered';
            }

            return null;
        }
    JS);
}

function searchModeUxVisibleSectionHeadings(object $page): array
{
    return $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('h1, h2, h3'))
            .map((candidate) => candidate.textContent?.replace(/\s+/g, ' ').trim() ?? '')
            .filter((text) => text !== '')
    JS);
}

function searchModeUxVisibleBodyText(object $page): string
{
    return $page->script(<<<'JS'
        () => document.body.textContent?.replace(/\s+/g, ' ').trim() ?? ''
    JS);
}

function searchModeUxClickVisibleTab(object $page, string $text): bool
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
            const tab = Array.from(document.querySelectorAll('[role="tab"], button')).find((candidate) =>
                candidate.textContent?.replace(/\s+/g, ' ').trim().includes(text) && isVisible(candidate)
            );

            tab?.click();

            return Boolean(tab);
        }
    JS));
}
