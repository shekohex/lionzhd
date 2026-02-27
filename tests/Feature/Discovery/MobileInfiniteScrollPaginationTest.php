<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

it('orders movie pages deterministically under added timestamp ties', function (): void {
    $user = User::factory()->create();

    mobileInfiniteCreateMovieCategory('vod-mobile-deterministic');

    $addedAt = '2026-01-01T00:00:00+00:00';

    foreach (range(1, 40) as $offset) {
        mobileInfiniteInsertMovie(1000 + $offset, 'vod-mobile-deterministic', $addedAt);
    }

    $pageOne = mobileInfiniteMoviesResponse($user, ['category' => 'vod-mobile-deterministic']);

    $pageOne->assertOk();

    $pageOneIds = mobileInfiniteMovieIds($pageOne);

    expect($pageOneIds)->toBe(range(1040, 1021));

    $pageTwo = mobileInfiniteMoviesResponse($user, [
        'category' => 'vod-mobile-deterministic',
        'page' => 2,
    ]);

    $pageTwo->assertOk();

    $pageTwoIds = mobileInfiniteMovieIds($pageTwo);

    expect($pageTwoIds)->toBe(range(1020, 1001));
    expect(array_values(array_intersect($pageOneIds, $pageTwoIds)))->toBe([]);
});

it('keeps movies snapshot-consistent across page boundaries when new rows are inserted', function (): void {
    $user = User::factory()->create();

    mobileInfiniteCreateMovieCategory('vod-mobile-snapshot');

    $addedAt = '2026-01-01T00:00:00+00:00';

    foreach (range(1, 40) as $offset) {
        mobileInfiniteInsertMovie(2000 + $offset, 'vod-mobile-snapshot', $addedAt);
    }

    $pageOne = mobileInfiniteMoviesResponse($user, ['category' => 'vod-mobile-snapshot']);

    $pageOne->assertOk();

    $nextPageUrl = $pageOne->json('props.movies.next_page_url');
    $nextPageQuery = mobileInfiniteNextPageQuery($pageOne, 'movies');
    $asOf = (string) ($nextPageQuery['as_of'] ?? '');
    $asOfId = (int) ($nextPageQuery['as_of_id'] ?? 0);

    expect($nextPageUrl)->toContain('page=2');
    expect($nextPageUrl)->toContain('as_of=');
    expect($nextPageUrl)->toContain('as_of_id=');
    expect($asOf)->not->toBe('');
    expect($asOfId)->toBeGreaterThan(0);

    mobileInfiniteInsertMovie(900001, 'vod-mobile-snapshot', '2026-01-02T00:00:00+00:00');
    mobileInfiniteInsertMovie($asOfId + 1000, 'vod-mobile-snapshot', $asOf);

    $pageTwo = mobileInfiniteMoviesResponse($user, [
        'category' => 'vod-mobile-snapshot',
        'page' => 2,
        'as_of' => $asOf,
        'as_of_id' => $asOfId,
    ]);

    $pageTwo->assertOk();

    $pageOneIds = mobileInfiniteMovieIds($pageOne);
    $pageTwoIds = mobileInfiniteMovieIds($pageTwo);
    $originalOrderedIds = range(2040, 2001);
    $combinedIds = array_values(array_merge($pageOneIds, $pageTwoIds));

    expect($combinedIds)->toBe($originalOrderedIds);
    expect(array_unique($combinedIds))->toHaveCount(40);
    expect($combinedIds)->not->toContain(900001);
    expect($combinedIds)->not->toContain($asOfId + 1000);
});

it('orders series pages deterministically under last_modified ties', function (): void {
    $user = User::factory()->create();

    mobileInfiniteCreateSeriesCategory('series-mobile-deterministic');

    $modifiedAt = '2026-01-01T00:00:00+00:00';

    foreach (range(1, 40) as $offset) {
        mobileInfiniteInsertSeries(3000 + $offset, 'series-mobile-deterministic', $modifiedAt);
    }

    $pageOne = mobileInfiniteSeriesResponse($user, ['category' => 'series-mobile-deterministic']);

    $pageOne->assertOk();

    $pageOneIds = mobileInfiniteSeriesIds($pageOne);

    expect($pageOneIds)->toBe(range(3040, 3021));

    $pageTwo = mobileInfiniteSeriesResponse($user, [
        'category' => 'series-mobile-deterministic',
        'page' => 2,
    ]);

    $pageTwo->assertOk();

    $pageTwoIds = mobileInfiniteSeriesIds($pageTwo);

    expect($pageTwoIds)->toBe(range(3020, 3001));
    expect(array_values(array_intersect($pageOneIds, $pageTwoIds)))->toBe([]);
});

it('keeps series snapshot-consistent under inserts and retains null last_modified rows in pagination', function (): void {
    $user = User::factory()->create();

    mobileInfiniteCreateSeriesCategory('series-mobile-snapshot');

    $modifiedAt = '2026-01-01T00:00:00+00:00';

    foreach (range(1, 39) as $offset) {
        mobileInfiniteInsertSeries(4000 + $offset, 'series-mobile-snapshot', $modifiedAt);
    }

    mobileInfiniteInsertSeries(4999, 'series-mobile-snapshot', null);

    $pageOne = mobileInfiniteSeriesResponse($user, ['category' => 'series-mobile-snapshot']);

    $pageOne->assertOk();

    $nextPageQuery = mobileInfiniteNextPageQuery($pageOne, 'series');
    $asOf = (string) ($nextPageQuery['as_of'] ?? '');
    $asOfId = (int) ($nextPageQuery['as_of_id'] ?? 0);

    expect($asOf)->not->toBe('');
    expect($asOfId)->toBeGreaterThan(0);

    mobileInfiniteInsertSeries(900002, 'series-mobile-snapshot', '2026-01-02T00:00:00+00:00');
    mobileInfiniteInsertSeries($asOfId + 1000, 'series-mobile-snapshot', $asOf);

    $pageTwo = mobileInfiniteSeriesResponse($user, [
        'category' => 'series-mobile-snapshot',
        'page' => 2,
        'as_of' => $asOf,
        'as_of_id' => $asOfId,
    ]);

    $pageTwo->assertOk();

    $pageOneIds = mobileInfiniteSeriesIds($pageOne);
    $pageTwoIds = mobileInfiniteSeriesIds($pageTwo);
    $combinedIds = array_values(array_merge($pageOneIds, $pageTwoIds));
    $originalOrderedIds = array_merge(range(4039, 4001), [4999]);

    expect($combinedIds)->toBe($originalOrderedIds);
    expect(array_unique($combinedIds))->toHaveCount(40);
    expect($combinedIds)->not->toContain(900002);
    expect($combinedIds)->not->toContain($asOfId + 1000);
    expect($combinedIds)->toContain(4999);
});

function mobileInfiniteMoviesResponse(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(mobileInfiniteInertiaHeaders())
        ->get(route('movies', $query));
}

function mobileInfiniteSeriesResponse(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(mobileInfiniteInertiaHeaders())
        ->get(route('series', $query));
}

function mobileInfiniteMovieIds(TestResponse $response): array
{
    return collect($response->json('props.movies.data'))
        ->pluck('stream_id')
        ->map(static fn (mixed $value): int => (int) $value)
        ->values()
        ->all();
}

function mobileInfiniteSeriesIds(TestResponse $response): array
{
    return collect($response->json('props.series.data'))
        ->pluck('series_id')
        ->map(static fn (mixed $value): int => (int) $value)
        ->values()
        ->all();
}

function mobileInfiniteNextPageQuery(TestResponse $response, string $resource): array
{
    $nextPageUrl = $response->json("props.{$resource}.next_page_url");

    expect($nextPageUrl)->not->toBeNull();

    $queryString = parse_url((string) $nextPageUrl, PHP_URL_QUERY);

    expect($queryString)->not->toBeNull();

    parse_str((string) $queryString, $query);

    return $query;
}

function mobileInfiniteCreateMovieCategory(string $categoryId): void
{
    Category::query()->create([
        'provider_id' => $categoryId,
        'name' => $categoryId,
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);
}

function mobileInfiniteCreateSeriesCategory(string $categoryId): void
{
    Category::query()->create([
        'provider_id' => $categoryId,
        'name' => $categoryId,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);
}

function mobileInfiniteInsertMovie(int $streamId, ?string $categoryId, string $added): void
{
    DB::table('vod_streams')->insert([
        'stream_id' => $streamId,
        'num' => $streamId,
        'name' => sprintf('Movie %d', $streamId),
        'stream_type' => 'movie',
        'added' => $added,
        'category_id' => $categoryId,
        'container_extension' => 'mp4',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function mobileInfiniteInsertSeries(int $seriesId, ?string $categoryId, ?string $lastModified): void
{
    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'category_id' => $categoryId,
        'last_modified' => $lastModified,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function mobileInfiniteInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
