<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\MediaCategoryAssignment;
use App\Models\Series;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\VodStream;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig([
        'host' => 'http://detail-page.test',
        'port' => 80,
        'username' => 'detail-user',
        'password' => 'detail-pass',
    ]));
});

if (! extension_loaded('sockets')) {
    it('requires the sockets extension for detail page category browser tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('shows hero category chips and browse navigation for both media types', function (): void {
        $user = User::factory()->create();

        detailPageCategoryCreateMovieCategory('movie-hidden', 'Hidden Movie Category', vodSyncOrder: 1);
        detailPageCategoryCreateMovieCategory('movie-visible', 'Visible Movie Category', vodSyncOrder: 2);
        detailPageCategoryCreateSeriesCategory('series-ignored', 'Ignored Series Category', seriesSyncOrder: 1);
        detailPageCategoryCreateSeriesCategory('series-visible', 'Visible Series Category', seriesSyncOrder: 2);

        $movie = detailPageCategoryCreateMovie('movie-hidden', 'Movie Detail Title');
        $series = detailPageCategoryCreateSeries('series-ignored', 'Series Detail Title');

        detailPageCategoryAssign(MediaType::Movie, (string) $movie->getKey(), ['movie-hidden', 'movie-visible']);
        detailPageCategoryAssign(MediaType::Series, (string) $series->getKey(), ['series-ignored', 'series-visible']);

        detailPageCategoryCreatePreference($user, MediaType::Movie, 'movie-hidden', isHidden: true);
        detailPageCategoryCreatePreference($user, MediaType::Series, 'series-ignored', isIgnored: true);
        detailPageCategoryFakeResponses($movie->stream_id, $movie->name, $series->series_id, $series->name);

        $moviePage = browserLoginAndVisit($user, route('movies.show', ['model' => $movie->getKey()]))
            ->resize(1440, 960)
            ->waitForText('Movie Detail Title')
            ->assertNoJavaScriptErrors();

        expect(detailPageCategoryHeroChipsInViewport($moviePage))->toBeTrue();

        $moviePage->assertSee('Hidden Movie Category')
            ->assertSee('Visible Movie Category')
            ->click('Hidden Movie Category')
            ->waitForText('Hidden Category Active')
            ->waitForText('Movie Detail Title')
            ->assertNoJavaScriptErrors();

        expect(parse_url($moviePage->url(), PHP_URL_PATH))->toBe(route('movies', [], false));
        expect(detailPageCategoryCurrentQueryValue($moviePage->url(), 'category'))->toBe('movie-hidden');

        $seriesPage = detailPageCategoryVisit($moviePage, route('series.show', ['model' => $series->getKey()]))
            ->resize(1440, 960)
            ->waitForText('Series Detail Title')
            ->assertNoJavaScriptErrors();

        expect(detailPageCategoryHeroChipsInViewport($seriesPage))->toBeTrue();

        $seriesPage->assertSee('Ignored Series Category')
            ->assertSee('Visible Series Category')
            ->click('Ignored Series Category')
            ->waitForText('This category is ignored')
            ->assertSee('Unignore and restore results')
            ->assertNoJavaScriptErrors();

        expect(parse_url($seriesPage->url(), PHP_URL_PATH))->toBe(route('series', [], false));
        expect(detailPageCategoryCurrentQueryValue($seriesPage->url(), 'category'))->toBe('series-ignored');
    })->group('browser');

    it('keeps hero category chips readable on mobile without truncate affordances', function (): void {
        $user = User::factory()->create();

        $movieLongName = 'Movie Category With A Very Long Name That Should Wrap Instead Of Truncating On Mobile';
        $seriesLongName = 'Series Category With Another Very Long Name That Should Stay Fully Visible On Mobile';

        detailPageCategoryCreateMovieCategory('movie-long', $movieLongName, vodSyncOrder: 1);
        detailPageCategoryCreateSeriesCategory('series-long', $seriesLongName, seriesSyncOrder: 1);

        $movie = detailPageCategoryCreateMovie('movie-long', 'Mobile Movie Detail');
        $series = detailPageCategoryCreateSeries('series-long', 'Mobile Series Detail');

        detailPageCategoryAssign(MediaType::Movie, (string) $movie->getKey(), ['movie-long']);
        detailPageCategoryAssign(MediaType::Series, (string) $series->getKey(), ['series-long']);
        detailPageCategoryFakeResponses($movie->stream_id, $movie->name, $series->series_id, $series->name);

        test()->actingAs($user);

        $moviePage = visit(route('movies.show', ['model' => $movie->getKey()]))
            ->resize(390, 844)
            ->waitForText('Mobile Movie Detail')
            ->assertNoJavaScriptErrors();

        expect(detailPageCategoryChipMetrics($moviePage, $movieLongName))->toMatchArray([
            'found' => true,
            'text' => $movieLongName,
            'hasForbiddenClass' => false,
            'textOverflow' => 'clip',
            'whiteSpace' => 'normal',
            'overflowWrap' => 'break-word',
        ]);

        $seriesPage = visit(route('series.show', ['model' => $series->getKey()]))
            ->resize(390, 844)
            ->waitForText('Mobile Series Detail')
            ->assertNoJavaScriptErrors();

        expect(detailPageCategoryChipMetrics($seriesPage, $seriesLongName))->toMatchArray([
            'found' => true,
            'text' => $seriesLongName,
            'hasForbiddenClass' => false,
            'textOverflow' => 'clip',
            'whiteSpace' => 'normal',
            'overflowWrap' => 'break-word',
        ]);
    })->group('browser');
}

function detailPageCategoryCreateMovieCategory(string $providerId, string $name, ?int $vodSyncOrder = null): void
{
    Category::query()->updateOrCreate(['provider_id' => $providerId], [
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
        'vod_sync_order' => $vodSyncOrder,
        'series_sync_order' => null,
    ]);
}

function detailPageCategoryCreateSeriesCategory(string $providerId, string $name, ?int $seriesSyncOrder = null): void
{
    Category::query()->updateOrCreate(['provider_id' => $providerId], [
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
        'vod_sync_order' => null,
        'series_sync_order' => $seriesSyncOrder,
    ]);
}

function detailPageCategoryCreateMovie(string $categoryId, string $name): VodStream
{
    static $streamId = 71_000;

    $streamId++;
    $movie = null;

    VodStream::withoutSyncingToSearch(static function () use ($streamId, $categoryId, $name, &$movie): void {
        VodStream::unguarded(static function () use ($streamId, $categoryId, $name, &$movie): void {
            $movie = VodStream::query()->create([
                'stream_id' => $streamId,
                'num' => $streamId,
                'name' => $name,
                'stream_type' => 'movie',
                'stream_icon' => 'https://example.com/movie.jpg',
                'added' => now()->toIso8601String(),
                'category_id' => $categoryId,
                'container_extension' => 'mp4',
            ]);
        });
    });

    if (! $movie instanceof VodStream) {
        throw new RuntimeException('Failed to create movie browser fixture.');
    }

    return $movie;
}

function detailPageCategoryCreateSeries(string $categoryId, string $name): Series
{
    static $seriesId = 72_000;

    $seriesId++;

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => $name,
        'category_id' => $categoryId,
        'cover' => 'https://example.com/series.jpg',
        'plot' => 'Series plot',
        'cast' => 'Series cast',
        'director' => 'Series director',
        'genre' => 'Series genre',
        'backdrop_path' => json_encode([], JSON_THROW_ON_ERROR),
        'releaseDate' => '2026-03-23',
        'rating' => 4.5,
        'rating_5based' => 4.5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Series::query()->findOrFail($seriesId);
}

function detailPageCategoryAssign(MediaType $mediaType, string $mediaProviderId, array $categoryProviderIds): void
{
    MediaCategoryAssignment::query()
        ->where('media_type', $mediaType->value)
        ->where('media_provider_id', $mediaProviderId)
        ->delete();

    foreach (array_values($categoryProviderIds) as $sourceOrder => $categoryProviderId) {
        MediaCategoryAssignment::query()->create([
            'media_type' => $mediaType->value,
            'media_provider_id' => $mediaProviderId,
            'category_provider_id' => $categoryProviderId,
            'source_order' => $sourceOrder,
        ]);
    }
}

function detailPageCategoryCreatePreference(
    User $user,
    MediaType $mediaType,
    string $categoryProviderId,
    bool $isHidden = false,
    bool $isIgnored = false,
): void {
    UserCategoryPreference::query()->create([
        'user_id' => $user->getKey(),
        'media_type' => $mediaType,
        'category_provider_id' => $categoryProviderId,
        'sort_order' => 0,
        'is_hidden' => $isHidden,
        'is_ignored' => $isIgnored,
    ]);
}

function detailPageCategoryFakeResponses(int $movieId, string $movieName, int $seriesId, string $seriesName): void
{
    $mockClient = new MockClient([
        GetVodInfoRequest::class => MockResponse::make([
            'info' => [
                'movie_image' => '',
                'tmdb_id' => '',
                'backdrop' => '',
                'youtube_trailer' => '',
                'genre' => 'Action, Drama',
                'plot' => 'Movie plot',
                'cast' => '',
                'rating' => '4.5',
                'director' => '',
                'releasedate' => '2026-01-01',
                'backdrop_path' => [],
                'duration_secs' => 0,
                'duration' => '0:00:00',
                'video' => [],
                'audio' => [],
                'bitrate' => 0,
            ],
            'movie_data' => [
                'stream_id' => $movieId,
                'name' => $movieName,
                'added' => '2026-01-01 00:00:00',
                'category_id' => '',
                'container_extension' => 'mp4',
                'custom_sid' => '',
                'direct_source' => '',
            ],
        ], 200),
        GetSeriesInfoRequest::class => MockResponse::make([
            'info' => [
                'name' => $seriesName,
                'cover' => 'https://example.test/cover.jpg',
                'plot' => 'Series plot',
                'cast' => 'Series cast',
                'director' => 'Series director',
                'genre' => 'Drama, Thriller',
                'releaseDate' => '2026-01-01',
                'last_modified' => '2026-01-01 00:00:00',
                'rating' => '8.0',
                'rating_5based' => 4.0,
                'backdrop_path' => ['https://example.test/backdrop.jpg'],
                'youtube_trailer' => '',
                'episode_run_time' => '00:45:00',
                'category_id' => '',
            ],
            'seasons' => ['1'],
            'episodes' => [
                '1' => [
                    [
                        'id' => '101',
                        'season' => 1,
                        'episode_num' => 1,
                        'title' => 'Episode 1',
                        'container_extension' => 'mkv',
                        'custom_sid' => 'sid-101',
                        'added' => '2026-01-01 00:00:00',
                        'direct_source' => '',
                        'info' => [
                            'duration_secs' => 2700,
                            'duration' => '00:45:00',
                            'bitrate' => 1000,
                            'video' => [],
                            'audio' => [],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });
}

function detailPageCategoryHeroChipsInViewport(object $page): bool
{
    return $page->script(<<<'JS'
        () => {
            const container = document.querySelector('[data-slot="hero-category-context"]');

            if (!(container instanceof HTMLElement)) {
                return false;
            }

            const rect = container.getBoundingClientRect();

            return rect.top >= 0 && rect.bottom <= window.innerHeight;
        }
    JS);
}

function detailPageCategoryChipMetrics(object $page, string $label): array
{
    $labelJson = json_encode($label, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__LABEL__', $labelJson, <<<'JS'
        () => {
            const label = __LABEL__;
            const link = Array.from(document.querySelectorAll('[data-slot="hero-category-chip"]')).find((candidate) =>
                candidate.textContent?.trim() === label && candidate instanceof HTMLElement
            );

            if (!(link instanceof HTMLElement)) {
                return {
                    found: false,
                    text: '',
                    hasForbiddenClass: false,
                    textOverflow: '',
                    whiteSpace: '',
                    overflowWrap: '',
                };
            }

            const badge = link.closest('[data-slot="badge"]');
            const className = `${link.className} ${badge instanceof HTMLElement ? badge.className : ''}`;
            const style = window.getComputedStyle(link);

            return {
                found: true,
                text: link.textContent?.trim() ?? '',
                hasForbiddenClass: /(truncate|line-clamp|ellipsis)/.test(className),
                textOverflow: style.textOverflow,
                whiteSpace: style.whiteSpace,
                overflowWrap: style.overflowWrap,
            };
        }
    JS));
}

function detailPageCategoryVisit(object $page, string $url): object
{
    $urlJson = json_encode($url, JSON_THROW_ON_ERROR);

    $page->script(str_replace('__URL__', $urlJson, <<<'JS'
        () => {
            window.location.assign(__URL__);

            return true;
        }
    JS));

    return $page;
}

function detailPageCategoryCurrentQueryValue(string $url, string $key): ?string
{
    $query = parse_url($url, PHP_URL_QUERY);

    if (! is_string($query) || $query === '') {
        return null;
    }

    parse_str($query, $values);

    $value = $values[$key] ?? null;

    return is_string($value) ? $value : null;
}
