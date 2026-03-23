<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\MediaCategoryAssignment;
use App\Models\Series;
use App\Models\User;
use App\Models\UserCategoryPreference;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

it('returns assigned series detail categories in canonical order with browse hrefs regardless of hidden or ignored preferences', function (): void {
    $user = User::factory()->create();

    seriesDetailCreateCategory('series-action', 'Action', seriesSyncOrder: 2);
    seriesDetailCreateCategory('series-drama', 'Drama', seriesSyncOrder: 2);
    seriesDetailCreateCategory('series-comedy', 'Comedy', seriesSyncOrder: 9);
    seriesDetailCreateCategory('series-zebra', 'Zebra');
    seriesDetailCreateCategory('series-alpha', 'Alpha');

    $series = seriesDetailCreateSeries('legacy-series-category');

    seriesDetailAssign(MediaType::Series, (string) $series->getKey(), [
        'series-zebra',
        'series-drama',
        'series-comedy',
        'series-action',
        'series-alpha',
    ]);

    seriesDetailInsertPreference($user, 'series-action', isHidden: true);
    seriesDetailInsertPreference($user, 'series-drama', isIgnored: true);
    seriesDetailFakeShowResponse();

    $response = seriesDetailShowResponse($user, $series);

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'series/show');
    $response->assertJsonPath('props.in_watchlist', false);
    $response->assertJsonPath('props.monitor', null);
    $response->assertJsonPath('props.category_context', [
        ['id' => 'series-drama', 'name' => 'Drama', 'href' => route('series', ['category' => 'series-drama'])],
        ['id' => 'series-action', 'name' => 'Action', 'href' => route('series', ['category' => 'series-action'])],
        ['id' => 'series-comedy', 'name' => 'Comedy', 'href' => route('series', ['category' => 'series-comedy'])],
        ['id' => 'series-alpha', 'name' => 'Alpha', 'href' => route('series', ['category' => 'series-alpha'])],
        ['id' => 'series-zebra', 'name' => 'Zebra', 'href' => route('series', ['category' => 'series-zebra'])],
    ]);
});

it('normalizes series detail category context to uncategorized when assignments are absent or uncategorized only', function (): void {
    $user = User::factory()->create();

    seriesDetailCreateCategory(
        Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
        'Uncategorized',
        seriesSyncOrder: 999,
        isSystem: true,
    );

    $seriesWithoutAssignments = seriesDetailCreateSeries(null);
    $seriesWithSystemAssignment = seriesDetailCreateSeries(null);

    seriesDetailAssign(MediaType::Series, (string) $seriesWithSystemAssignment->getKey(), [
        Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
    ]);

    seriesDetailFakeShowResponse();

    $expected = [
        ['id' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID, 'name' => 'Uncategorized', 'href' => route('series', ['category' => Category::UNCATEGORIZED_SERIES_PROVIDER_ID])],
    ];

    seriesDetailShowResponse($user, $seriesWithoutAssignments)
        ->assertOk()
        ->assertJsonPath('props.category_context', $expected);

    seriesDetailShowResponse($user, $seriesWithSystemAssignment)
        ->assertOk()
        ->assertJsonPath('props.category_context', $expected);
});

function seriesDetailShowResponse(User $user, Series $series): TestResponse
{
    return test()->actingAs($user)
        ->withHeaders(seriesDetailInertiaHeaders())
        ->get(route('series.show', ['model' => $series->series_id]));
}

function seriesDetailCreateCategory(
    string $providerId,
    string $name,
    ?int $seriesSyncOrder = null,
    bool $isSystem = false,
): void {
    Category::query()->updateOrCreate(['provider_id' => $providerId], [
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => false,
        'in_series' => true,
        'is_system' => $isSystem,
        'series_sync_order' => $seriesSyncOrder,
    ]);
}

function seriesDetailCreateSeries(?string $categoryId): Series
{
    static $seriesId = 30_000;

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

function seriesDetailAssign(MediaType $mediaType, string $mediaProviderId, array $categoryProviderIds): void
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

function seriesDetailInsertPreference(User $user, string $categoryProviderId, bool $isHidden = false, bool $isIgnored = false): void
{
    UserCategoryPreference::query()->create([
        'user_id' => $user->getKey(),
        'media_type' => MediaType::Series,
        'category_provider_id' => $categoryProviderId,
        'sort_order' => 0,
        'is_hidden' => $isHidden,
        'is_ignored' => $isIgnored,
    ]);
}

function seriesDetailFakeShowResponse(): void
{
    $mockClient = new MockClient([
        GetSeriesInfoRequest::class => MockResponse::make([
            'info' => [
                'name' => 'Series Detail',
                'cover' => 'https://example.test/cover.jpg',
                'plot' => 'plot',
                'cast' => 'cast',
                'director' => 'director',
                'genre' => 'genre',
                'releaseDate' => '2026-01-01',
                'last_modified' => '2026-01-01 00:00:00',
                'rating' => '8.0',
                'rating_5based' => 4.0,
                'backdrop_path' => ['https://example.test/backdrop.jpg'],
                'youtube_trailer' => 'https://youtube.com/watch?v=test',
                'episode_run_time' => '00:45:00',
                'category_id' => 'legacy-series-category',
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

function seriesDetailInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
