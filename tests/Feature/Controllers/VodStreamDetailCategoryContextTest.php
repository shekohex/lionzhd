<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\MediaCategoryAssignment;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\VodStream;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('returns movie detail category context in canonical order with browse hrefs and neutral preferences', function (): void {
    $user = User::factory()->create();

    movieDetailCreateCategory('movie-comedy', 'Comedy', vodSyncOrder: 4);
    movieDetailCreateCategory('movie-action', 'Action', vodSyncOrder: 1);
    movieDetailCreateCategory('movie-drama', 'Drama', vodSyncOrder: 4);
    movieDetailCreateCategory('movie-zebra', 'Zebra');
    movieDetailCreateCategory('movie-alpha', 'Alpha');

    $movie = movieDetailCreateMovie('legacy-category');

    movieDetailAssignCategories((string) $movie->getKey(), [
        'movie-zebra',
        'movie-comedy',
        'movie-action',
        'movie-drama',
        'movie-alpha',
    ]);

    movieDetailCreatePreference($user, 'movie-action', isHidden: true);
    movieDetailCreatePreference($user, 'movie-drama', isIgnored: true);

    movieDetailBindInfo($movie->stream_id, $movie->name);

    $response = test()->actingAs($user)
        ->withHeaders(movieDetailInertiaHeaders())
        ->get(route('movies.show', ['model' => $movie->getKey()]));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'movies/show');
    $response->assertJsonPath('props.category_context', [
        ['id' => 'movie-action', 'name' => 'Action', 'href' => route('movies', ['category' => 'movie-action'])],
        ['id' => 'movie-comedy', 'name' => 'Comedy', 'href' => route('movies', ['category' => 'movie-comedy'])],
        ['id' => 'movie-drama', 'name' => 'Drama', 'href' => route('movies', ['category' => 'movie-drama'])],
        ['id' => 'movie-alpha', 'name' => 'Alpha', 'href' => route('movies', ['category' => 'movie-alpha'])],
        ['id' => 'movie-zebra', 'name' => 'Zebra', 'href' => route('movies', ['category' => 'movie-zebra'])],
    ]);
});

it('normalizes movie detail category context to uncategorized when no concrete assignment exists', function (): void {
    $user = User::factory()->create();

    movieDetailCreateCategory(
        Category::UNCATEGORIZED_VOD_PROVIDER_ID,
        'Uncategorized',
        vodSyncOrder: 99,
        isSystem: true,
    );

    $movieWithoutAssignments = movieDetailCreateMovie(null);
    $movieWithUncategorizedAssignment = movieDetailCreateMovie(null);

    movieDetailAssignCategories((string) $movieWithUncategorizedAssignment->getKey(), [
        Category::UNCATEGORIZED_VOD_PROVIDER_ID,
    ]);

    movieDetailBindInfo($movieWithoutAssignments->stream_id, $movieWithoutAssignments->name);

    $withoutAssignmentsResponse = test()->actingAs($user)
        ->withHeaders(movieDetailInertiaHeaders())
        ->get(route('movies.show', ['model' => $movieWithoutAssignments->getKey()]));

    $expectedChip = [
        ['id' => Category::UNCATEGORIZED_VOD_PROVIDER_ID, 'name' => 'Uncategorized', 'href' => route('movies', ['category' => Category::UNCATEGORIZED_VOD_PROVIDER_ID])],
    ];

    $withoutAssignmentsResponse->assertOk();
    $withoutAssignmentsResponse->assertJsonPath('props.category_context', $expectedChip);

    movieDetailBindInfo($movieWithUncategorizedAssignment->stream_id, $movieWithUncategorizedAssignment->name);

    $withUncategorizedAssignmentResponse = test()->actingAs($user)
        ->withHeaders(movieDetailInertiaHeaders())
        ->get(route('movies.show', ['model' => $movieWithUncategorizedAssignment->getKey()]));

    $withUncategorizedAssignmentResponse->assertOk();
    $withUncategorizedAssignmentResponse->assertJsonPath('props.category_context', $expectedChip);
});

function movieDetailCreateCategory(
    string $providerId,
    string $name,
    ?int $vodSyncOrder = null,
    bool $isSystem = false,
): void {
    Category::query()->updateOrCreate(['provider_id' => $providerId], [
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => true,
        'in_series' => false,
        'is_system' => $isSystem,
        'vod_sync_order' => $vodSyncOrder,
        'series_sync_order' => null,
    ]);
}

function movieDetailCreateMovie(?string $categoryId): VodStream
{
    static $streamId = 12_000;

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

function movieDetailAssignCategories(string $movieProviderId, array $categoryProviderIds): void
{
    MediaCategoryAssignment::query()
        ->where('media_type', MediaType::Movie->value)
        ->where('media_provider_id', $movieProviderId)
        ->delete();

    foreach (array_values($categoryProviderIds) as $sourceOrder => $categoryProviderId) {
        MediaCategoryAssignment::query()->create([
            'media_type' => MediaType::Movie->value,
            'media_provider_id' => $movieProviderId,
            'category_provider_id' => $categoryProviderId,
            'source_order' => $sourceOrder,
        ]);
    }
}

function movieDetailCreatePreference(User $user, string $categoryProviderId, bool $isHidden = false, bool $isIgnored = false): void
{
    UserCategoryPreference::query()->create([
        'user_id' => $user->getKey(),
        'media_type' => MediaType::Movie,
        'category_provider_id' => $categoryProviderId,
        'sort_order' => 0,
        'is_hidden' => $isHidden,
        'is_ignored' => $isIgnored,
    ]);
}

function movieDetailBindInfo(int $streamId, string $name): void
{
    $mockClient = new MockClient([
        GetVodInfoRequest::class => MockResponse::make([
            'info' => [
                'movie_image' => '',
                'tmdb_id' => '',
                'backdrop' => '',
                'youtube_trailer' => '',
                'genre' => '',
                'plot' => '',
                'cast' => '',
                'rating' => '0',
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
                'stream_id' => $streamId,
                'name' => $name,
                'added' => '2026-01-01 00:00:00',
                'category_id' => '',
                'container_extension' => 'mp4',
                'custom_sid' => '',
                'direct_source' => '',
            ],
        ], 200),
    ]);

    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });
}

function movieDetailInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
