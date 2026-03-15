<?php

declare(strict_types=1);

use App\Enums\MediaType;
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
