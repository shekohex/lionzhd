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
