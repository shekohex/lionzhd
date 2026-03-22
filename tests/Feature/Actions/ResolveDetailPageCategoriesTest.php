<?php

declare(strict_types=1);

use App\Actions\ResolveDetailPageCategories;
use App\Enums\MediaType;
use App\Models\Category;
use App\Models\MediaCategoryAssignment;
use App\Models\Series;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns movie detail chips in canonical order and ignores hidden or ignored preferences', function (): void {
    $user = User::factory()->create();

    detailCategoryCreateCategory('movie-action', 'Action', inVod: true, vodSyncOrder: 1);
    detailCategoryCreateCategory('movie-comedy', 'Comedy', inVod: true, vodSyncOrder: 5);
    detailCategoryCreateCategory('movie-drama', 'Drama', inVod: true, vodSyncOrder: 5);
    detailCategoryCreateCategory('movie-zebra', 'Zebra', inVod: true);
    detailCategoryCreateCategory('movie-alpha', 'Alpha', inVod: true);

    $movie = detailCategoryCreateMovie('legacy-movie-category');

    detailCategoryAssign(MediaType::Movie, (string) $movie->getKey(), [
        'movie-zebra',
        'movie-comedy',
        'movie-action',
        'movie-drama',
        'movie-alpha',
    ]);

    detailCategoryInsertPreference($user, MediaType::Movie, 'movie-action', isHidden: true);
    detailCategoryInsertPreference($user, MediaType::Movie, 'movie-drama', isIgnored: true);

    $resolver = app(ResolveDetailPageCategories::class);

    expect(detailCategoryChipPayload($resolver->forMovie($movie)))->toBe([
        ['id' => 'movie-action', 'name' => 'Action', 'href' => route('movies', ['category' => 'movie-action'])],
        ['id' => 'movie-comedy', 'name' => 'Comedy', 'href' => route('movies', ['category' => 'movie-comedy'])],
        ['id' => 'movie-drama', 'name' => 'Drama', 'href' => route('movies', ['category' => 'movie-drama'])],
        ['id' => 'movie-alpha', 'name' => 'Alpha', 'href' => route('movies', ['category' => 'movie-alpha'])],
        ['id' => 'movie-zebra', 'name' => 'Zebra', 'href' => route('movies', ['category' => 'movie-zebra'])],
    ]);
});

it('normalizes movie detail categories to uncategorized and skips missing category rows', function (): void {
    detailCategoryCreateCategory(
        Category::UNCATEGORIZED_VOD_PROVIDER_ID,
        'Uncategorized',
        inVod: true,
        isSystem: true,
    );

    $movieWithoutAssignments = detailCategoryCreateMovie(null);
    $movieWithSystemAssignment = detailCategoryCreateMovie(null);
    $movieWithMissingCategory = detailCategoryCreateMovie(null);

    detailCategoryAssign(MediaType::Movie, (string) $movieWithSystemAssignment->getKey(), [
        Category::UNCATEGORIZED_VOD_PROVIDER_ID,
    ]);

    detailCategoryAssign(MediaType::Movie, (string) $movieWithMissingCategory->getKey(), [
        'missing-movie-category',
    ]);

    $resolver = app(ResolveDetailPageCategories::class);

    $uncategorizedChip = [
        ['id' => Category::UNCATEGORIZED_VOD_PROVIDER_ID, 'name' => 'Uncategorized', 'href' => route('movies', ['category' => Category::UNCATEGORIZED_VOD_PROVIDER_ID])],
    ];

    expect(detailCategoryChipPayload($resolver->forMovie($movieWithoutAssignments)))->toBe($uncategorizedChip)
        ->and(detailCategoryChipPayload($resolver->forMovie($movieWithSystemAssignment)))->toBe($uncategorizedChip)
        ->and(detailCategoryChipPayload($resolver->forMovie($movieWithMissingCategory)))->toBe([]);
});

it('returns series detail chips in canonical order and ignores hidden or ignored preferences', function (): void {
    $user = User::factory()->create();

    detailCategoryCreateCategory('series-action', 'Action', inSeries: true, seriesSyncOrder: 2);
    detailCategoryCreateCategory('series-drama', 'Drama', inSeries: true, seriesSyncOrder: 2);
    detailCategoryCreateCategory('series-comedy', 'Comedy', inSeries: true, seriesSyncOrder: 9);
    detailCategoryCreateCategory('series-alpha', 'Alpha', inSeries: true);
    detailCategoryCreateCategory('series-zebra', 'Zebra', inSeries: true);

    $series = detailCategoryCreateSeries('legacy-series-category');

    detailCategoryAssign(MediaType::Series, (string) $series->getKey(), [
        'series-zebra',
        'series-drama',
        'series-comedy',
        'series-action',
        'series-alpha',
    ]);

    detailCategoryInsertPreference($user, MediaType::Series, 'series-action', isHidden: true);
    detailCategoryInsertPreference($user, MediaType::Series, 'series-drama', isIgnored: true);

    $resolver = app(ResolveDetailPageCategories::class);

    expect(detailCategoryChipPayload($resolver->forSeries($series)))->toBe([
        ['id' => 'series-action', 'name' => 'Action', 'href' => route('series', ['category' => 'series-action'])],
        ['id' => 'series-drama', 'name' => 'Drama', 'href' => route('series', ['category' => 'series-drama'])],
        ['id' => 'series-comedy', 'name' => 'Comedy', 'href' => route('series', ['category' => 'series-comedy'])],
        ['id' => 'series-alpha', 'name' => 'Alpha', 'href' => route('series', ['category' => 'series-alpha'])],
        ['id' => 'series-zebra', 'name' => 'Zebra', 'href' => route('series', ['category' => 'series-zebra'])],
    ]);
});

it('normalizes series detail categories to uncategorized and skips missing category rows', function (): void {
    detailCategoryCreateCategory(
        Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
        'Uncategorized',
        inSeries: true,
        isSystem: true,
    );

    $seriesWithoutAssignments = detailCategoryCreateSeries(null);
    $seriesWithSystemAssignment = detailCategoryCreateSeries(null);
    $seriesWithMissingCategory = detailCategoryCreateSeries(null);

    detailCategoryAssign(MediaType::Series, (string) $seriesWithSystemAssignment->getKey(), [
        Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
    ]);

    detailCategoryAssign(MediaType::Series, (string) $seriesWithMissingCategory->getKey(), [
        'missing-series-category',
    ]);

    $resolver = app(ResolveDetailPageCategories::class);

    $uncategorizedChip = [
        ['id' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID, 'name' => 'Uncategorized', 'href' => route('series', ['category' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID])],
    ];

    expect(detailCategoryChipPayload($resolver->forSeries($seriesWithoutAssignments)))->toBe($uncategorizedChip)
        ->and(detailCategoryChipPayload($resolver->forSeries($seriesWithSystemAssignment)))->toBe($uncategorizedChip)
        ->and(detailCategoryChipPayload($resolver->forSeries($seriesWithMissingCategory)))->toBe([]);
});

function detailCategoryCreateCategory(
    string $providerId,
    string $name,
    bool $inVod = false,
    bool $inSeries = false,
    ?int $vodSyncOrder = null,
    ?int $seriesSyncOrder = null,
    bool $isSystem = false,
): void {
    Category::query()->updateOrCreate(['provider_id' => $providerId], [
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => $inVod,
        'in_series' => $inSeries,
        'is_system' => $isSystem,
        'vod_sync_order' => $vodSyncOrder,
        'series_sync_order' => $seriesSyncOrder,
    ]);
}

function detailCategoryCreateMovie(?string $categoryId): VodStream
{
    static $streamId = 4000;

    $streamId++;
    $movie = null;

    VodStream::withoutSyncingToSearch(static function () use ($streamId, $categoryId, &$movie): void {
        VodStream::unguarded(static function () use ($streamId, $categoryId, &$movie): void {
            $movie = VodStream::query()->create([
                'stream_id' => $streamId,
                'num' => $streamId,
                'name' => sprintf('Movie %d', $streamId),
                'stream_type' => 'movie',
                'added' => now()->toIso8601String(),
                'category_id' => $categoryId,
                'container_extension' => 'mp4',
            ]);
        });
    });

    if (! $movie instanceof VodStream) {
        throw new RuntimeException('Failed to create movie test fixture.');
    }

    return $movie;
}

function detailCategoryCreateSeries(?string $categoryId): Series
{
    static $seriesId = 9000;

    $seriesId++;

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'category_id' => $categoryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Series::query()->findOrFail($seriesId);
}

function detailCategoryAssign(MediaType $mediaType, string $mediaProviderId, array $categoryProviderIds): void
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

function detailCategoryInsertPreference(User $user, MediaType $mediaType, string $categoryProviderId, bool $isHidden = false, bool $isIgnored = false): void
{
    UserCategoryPreference::query()->create([
        'user_id' => $user->getKey(),
        'media_type' => $mediaType,
        'category_provider_id' => $categoryProviderId,
        'sort_order' => 0,
        'is_hidden' => $isHidden,
        'is_ignored' => $isIgnored,
    ]);
}

function detailCategoryChipPayload(iterable $chips): array
{
    return collect($chips)
        ->map(static fn (object $chip): array => [
            'id' => $chip->id,
            'name' => $chip->name,
            'href' => $chip->href,
        ])
        ->values()
        ->all();
}
