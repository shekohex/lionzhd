<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Enums\SearchSortby;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

it('renders series search when per page is omitted', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();

    DB::table('series')->insert([
        'series_id' => 1001,
        'num' => 1001,
        'name' => 'Alpha Series',
        'plot' => 'Alpha plot',
        'last_modified' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = test()->actingAs($user)
        ->withHeaders(searchInertiaHeaders())
        ->get(route('search.full', [
            'q' => 'Alpha',
            'media_type' => MediaType::Series->value,
        ]));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'search');
    $response->assertJsonPath('props.filters.per_page', 10);
    $response->assertJsonPath('props.series.total', 1);
});

it('renders movie search when per page is omitted', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();

    DB::table('vod_streams')->insert([
        'stream_id' => 2001,
        'num' => 2001,
        'name' => 'Alpha Movie',
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => '8',
        'rating_5based' => 4.0,
        'added' => now()->toDateTimeString(),
        'is_adult' => false,
        'category_id' => null,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = test()->actingAs($user)
        ->withHeaders(searchInertiaHeaders())
        ->get(route('search.full', [
            'q' => 'Alpha',
            'media_type' => MediaType::Movie->value,
        ]));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'search');
    $response->assertJsonPath('props.filters.per_page', 10);
    $response->assertJsonPath('props.movies.total', 1);
});

it('renders lightweight search when per page is omitted', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();

    DB::table('series')->insert([
        'series_id' => 1002,
        'num' => 1002,
        'name' => 'Beta Series',
        'plot' => 'Beta plot',
        'last_modified' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vod_streams')->insert([
        'stream_id' => 2002,
        'num' => 2002,
        'name' => 'Beta Movie',
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => '7',
        'rating_5based' => 3.5,
        'added' => now()->toDateTimeString(),
        'is_adult' => false,
        'category_id' => null,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = test()->actingAs($user)
        ->withHeaders(searchPartialHeaders('search'))
        ->post(route('search.lightweight'), [
            'q' => 'Beta',
        ]);

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'search');
    $response->assertJsonPath('props.filters.per_page', 10);
    $response->assertJsonPath('props.movies.meta.total', 1);
    $response->assertJsonPath('props.series.meta.total', 1);
});

it('prefers canonical params over conflicting magic words in the raw query', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();

    seedSearchControllerMovie(3_001, 'Galaxy Movie');
    seedSearchControllerSeries(4_001, 'Galaxy Series');

    $rawQuery = 'Galaxy type:series sort:popular';

    $response = test()->actingAs($user)
        ->withHeaders(searchInertiaHeaders())
        ->get(route('search.full', [
            'q' => $rawQuery,
            'media_type' => MediaType::Movie->value,
            'sort_by' => SearchSortby::Latest->value,
        ]));

    $response->assertOk();
    $response->assertJsonPath('props.filters.q', $rawQuery);
    $response->assertJsonPath('props.filters.media_type', MediaType::Movie->value);
    $response->assertJsonPath('props.filters.sort_by', SearchSortby::Latest->value);
    $response->assertJsonPath('props.movies.total', 1);
    $response->assertJsonPath('props.movies.data.0.name', 'Galaxy Movie');
    $response->assertJsonPath('props.series', []);
});

it('falls back to parsed media type tokens when canonical params are absent', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();

    seedSearchControllerMovie(3_002, 'Nebula Movie');
    seedSearchControllerSeries(4_002, 'Nebula Series');

    $rawQuery = 'Nebula type:series';

    $response = test()->actingAs($user)
        ->withHeaders(searchInertiaHeaders())
        ->get(route('search.full', [
            'q' => $rawQuery,
        ]));

    $response->assertOk();
    $response->assertJsonPath('props.filters.q', $rawQuery);
    $response->assertJsonPath('props.filters.media_type', MediaType::Series->value);
    $response->assertJsonPath('props.movies', []);
    $response->assertJsonPath('props.series.total', 1);
    $response->assertJsonPath('props.series.data.0.name', 'Nebula Series');
});

it('searches with the stripped base query while keeping visible sort tokens in q', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();

    seedSearchControllerMovie(3_003, 'Comet Movie');
    seedSearchControllerSeries(4_003, 'Comet Series');

    $rawQuery = 'Comet sort:rating';

    $response = test()->actingAs($user)
        ->withHeaders(searchInertiaHeaders())
        ->get(route('search.full', [
            'q' => $rawQuery,
        ]));

    $response->assertOk();
    $response->assertJsonPath('props.filters.q', $rawQuery);
    $response->assertJsonPath('props.filters.sort_by', SearchSortby::Rating->value);
    $response->assertJsonPath('props.movies.total', 1);
    $response->assertJsonPath('props.series.total', 1);
});

it('shared page param keeps mixed search sections aligned', function (): void {
    config()->set('scout.driver', 'database');

    $user = User::factory()->create();

    foreach (range(1, 6) as $index) {
        seedSearchControllerMovie(5_000 + $index, "Galaxy Movie {$index}");
        seedSearchControllerSeries(6_000 + $index, "Galaxy Series {$index}");
    }

    $pageOne = test()->actingAs($user)
        ->withHeaders(searchInertiaHeaders())
        ->get(route('search.full', [
            'q' => 'Galaxy',
        ]));

    $pageOne->assertOk();
    $pageOne->assertJsonPath('props.filters.q', 'Galaxy');
    $pageOne->assertJsonPath('props.filters.page', 1);
    $pageOne->assertJsonMissingPath('props.filters.movie_page');
    $pageOne->assertJsonMissingPath('props.filters.series_page');
    $pageOne->assertJsonPath('props.movies.current_page', 1);
    $pageOne->assertJsonPath('props.series.current_page', 1);
    $pageOne->assertJsonPath('props.movies.per_page', 5);
    $pageOne->assertJsonPath('props.series.per_page', 5);
    $pageOne->assertJsonPath('props.movies.total', 6);
    $pageOne->assertJsonPath('props.series.total', 6);
    expect(searchControllerPaginatorDataCount($pageOne, 'movies'))->toBe(5);
    expect(searchControllerPaginatorDataCount($pageOne, 'series'))->toBe(5);
    expect(searchControllerPaginatorUrls($pageOne, 'movies'))->each->toContain('page=');
    expect(searchControllerPaginatorUrls($pageOne, 'series'))->each->toContain('page=');
    expect(searchControllerPaginatorUrls($pageOne, 'movies'))->each->not->toContain('movie_page=');
    expect(searchControllerPaginatorUrls($pageOne, 'movies'))->each->not->toContain('series_page=');
    expect(searchControllerPaginatorUrls($pageOne, 'series'))->each->not->toContain('movie_page=');
    expect(searchControllerPaginatorUrls($pageOne, 'series'))->each->not->toContain('series_page=');

    $pageTwo = test()->actingAs($user)
        ->withHeaders(searchInertiaHeaders())
        ->get(route('search.full', [
            'q' => 'Galaxy',
            'page' => 2,
        ]));

    $pageTwo->assertOk();
    $pageTwo->assertJsonPath('props.filters.q', 'Galaxy');
    $pageTwo->assertJsonPath('props.filters.page', 2);
    $pageTwo->assertJsonMissingPath('props.filters.movie_page');
    $pageTwo->assertJsonMissingPath('props.filters.series_page');
    $pageTwo->assertJsonPath('props.movies.current_page', 2);
    $pageTwo->assertJsonPath('props.series.current_page', 2);
    $pageTwo->assertJsonPath('props.movies.per_page', 5);
    $pageTwo->assertJsonPath('props.series.per_page', 5);
    $pageTwo->assertJsonPath('props.movies.total', 6);
    $pageTwo->assertJsonPath('props.series.total', 6);
    expect(searchControllerPaginatorDataCount($pageTwo, 'movies'))->toBe(1);
    expect(searchControllerPaginatorDataCount($pageTwo, 'series'))->toBe(1);
    expect(searchControllerPaginatorUrls($pageTwo, 'movies'))->each->toContain('page=');
    expect(searchControllerPaginatorUrls($pageTwo, 'series'))->each->toContain('page=');
    expect(searchControllerPaginatorUrls($pageTwo, 'movies'))->each->not->toContain('movie_page=');
    expect(searchControllerPaginatorUrls($pageTwo, 'movies'))->each->not->toContain('series_page=');
    expect(searchControllerPaginatorUrls($pageTwo, 'series'))->each->not->toContain('movie_page=');
    expect(searchControllerPaginatorUrls($pageTwo, 'series'))->each->not->toContain('series_page=');
});

function searchInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}

function searchPartialHeaders(string $component): array
{
    return [
        ...searchInertiaHeaders(),
        'X-Inertia-Partial-Component' => $component,
    ];
}

function seedSearchControllerMovie(int $streamId, string $name): void
{
    DB::table('vod_streams')->insert([
        'stream_id' => $streamId,
        'num' => $streamId,
        'name' => $name,
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => '8',
        'rating_5based' => 4.0,
        'added' => now()->toDateTimeString(),
        'is_adult' => false,
        'category_id' => null,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedSearchControllerSeries(int $seriesId, string $name): void
{
    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => $name,
        'plot' => 'Plot',
        'last_modified' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function searchControllerPaginatorDataCount(TestResponse $response, string $key): int
{
    return count($response->json("props.{$key}.data") ?? []);
}

function searchControllerPaginatorUrls(TestResponse $response, string $key): array
{
    return collect($response->json("props.{$key}.links") ?? [])
        ->pluck('url')
        ->filter(static fn (?string $url): bool => filled($url))
        ->values()
        ->all();
}
