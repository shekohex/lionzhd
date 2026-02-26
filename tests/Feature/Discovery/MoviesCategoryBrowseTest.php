<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('filters movies by a valid category and returns selected filter prop', function (): void {
    $user = User::factory()->create();

    Category::query()->create([
        'provider_id' => 'vod-action',
        'name' => 'Action',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-drama',
        'name' => 'Drama',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    seedMovieRecord(1001, 'Action 1', 'vod-action');
    seedMovieRecord(1002, 'Action 2', 'vod-action');
    seedMovieRecord(1003, 'Drama 1', 'vod-drama');

    $response = $this->actingAs($user)
        ->withHeaders(moviesInertiaHeaders())
        ->get(route('movies', ['category' => 'vod-action']));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'movies/index');
    $response->assertJsonPath('props.filters.category', 'vod-action');
    $response->assertJsonCount(2, 'props.movies.data');

    $categoryIds = collect($response->json('props.movies.data'))
        ->pluck('category_id')
        ->unique()
        ->values()
        ->all();

    expect($categoryIds)->toBe(['vod-action']);
});

it('filters uncategorized movies across null blank and system uncategorized category ids', function (): void {
    $user = User::factory()->create();

    Category::query()->create([
        'provider_id' => 'vod-action',
        'name' => 'Action',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    seedMovieRecord(1101, 'Null Category', null);
    seedMovieRecord(1102, 'Blank Category', '');
    seedMovieRecord(1103, 'System Uncategorized', Category::UNCATEGORIZED_VOD_PROVIDER_ID);
    seedMovieRecord(1104, 'Action Category', 'vod-action');

    $response = $this->actingAs($user)
        ->withHeaders(moviesInertiaHeaders())
        ->get(route('movies', ['category' => Category::UNCATEGORIZED_VOD_PROVIDER_ID]));

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', Category::UNCATEGORIZED_VOD_PROVIDER_ID);
    $response->assertJsonCount(3, 'props.movies.data');

    $categoryIds = collect($response->json('props.movies.data'))
        ->pluck('category_id')
        ->sort()
        ->values()
        ->all();

    expect($categoryIds)->toHaveCount(3);
    expect($categoryIds)->toContain(null);
    expect($categoryIds)->toContain('');
    expect($categoryIds)->toContain(Category::UNCATEGORIZED_VOD_PROVIDER_ID);
});

it('redirects invalid category ids to movies index with warning flash', function (): void {
    $user = User::factory()->create();

    Category::query()->create([
        'provider_id' => 'vod-action',
        'name' => 'Action',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    $response = $this->actingAs($user)
        ->get(route('movies', ['category' => 'missing-category']));

    $response->assertRedirect(route('movies'));
    $response->assertSessionHas('warning', 'Category not found. Showing all categories.');

    $followResponse = $this->actingAs($user)
        ->withHeaders(moviesInertiaHeaders())
        ->get(route('movies'));

    $followResponse->assertOk();
    $followResponse->assertJsonPath('component', 'movies/index');
});

it('keeps selected zero-item category enabled and returns empty paginator without redirect', function (): void {
    $user = User::factory()->create();

    Category::query()->create([
        'provider_id' => 'vod-zero',
        'name' => 'Zero',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    seedMovieRecord(1201, 'Other Category Movie', Category::UNCATEGORIZED_VOD_PROVIDER_ID);

    $response = $this->actingAs($user)
        ->withHeaders(moviesInertiaHeaders())
        ->get(route('movies', ['category' => 'vod-zero']));

    $response->assertOk();
    $response->assertJsonPath('props.filters.category', 'vod-zero');
    $response->assertJsonPath('props.movies.total', 0);
    $response->assertJsonCount(0, 'props.movies.data');

    $selectedCategory = collect($response->json('props.categories'))->firstWhere('id', 'vod-zero');

    expect($selectedCategory)->not->toBeNull();
    expect($selectedCategory['disabled'])->toBeFalse();
});

it('disables zero-item category when it is not selected', function (): void {
    $user = User::factory()->create();

    Category::query()->create([
        'provider_id' => 'vod-zero',
        'name' => 'Zero',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    seedMovieRecord(1301, 'Other Category Movie', Category::UNCATEGORIZED_VOD_PROVIDER_ID);

    $response = $this->actingAs($user)
        ->withHeaders(moviesInertiaHeaders())
        ->get(route('movies'));

    $response->assertOk();

    $zeroCategory = collect($response->json('props.categories'))->firstWhere('id', 'vod-zero');

    expect($zeroCategory)->not->toBeNull();
    expect($zeroCategory['disabled'])->toBeTrue();
});

it('preserves active category query string in paginator next page url', function (): void {
    $user = User::factory()->create();

    Category::query()->create([
        'provider_id' => 'vod-many',
        'name' => 'Many',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    foreach (range(1, 21) as $index) {
        seedMovieRecord(1400 + $index, sprintf('Many %d', $index), 'vod-many');
    }

    $response = $this->actingAs($user)
        ->withHeaders(moviesInertiaHeaders())
        ->get(route('movies', ['category' => 'vod-many']));

    $response->assertOk();

    $nextPageUrl = $response->json('props.movies.next_page_url');

    expect($nextPageUrl)->not->toBeNull();
    expect($nextPageUrl)->toContain('category=vod-many');
    expect($nextPageUrl)->toContain('page=2');
});

it('returns categories ordered alphabetically with uncategorized last', function (): void {
    $user = User::factory()->create();

    Category::query()->create([
        'provider_id' => 'vod-zeta',
        'name' => 'zeta',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-alpha',
        'name' => 'Alpha',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'vod-bravo',
        'name' => 'bravo',
        'in_vod' => true,
        'in_series' => false,
        'is_system' => false,
    ]);

    Category::query()->create([
        'provider_id' => 'series-only',
        'name' => 'Series Only',
        'in_vod' => false,
        'in_series' => true,
        'is_system' => false,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(moviesInertiaHeaders())
        ->get(route('movies'));

    $response->assertOk();

    $categoryIds = collect($response->json('props.categories'))
        ->pluck('id')
        ->all();

    expect($categoryIds)->toBe([
        'vod-alpha',
        'vod-bravo',
        'vod-zeta',
        Category::UNCATEGORIZED_VOD_PROVIDER_ID,
    ]);
});

function moviesInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}

function seedMovieRecord(int $streamId, string $name, ?string $categoryId): void
{
    VodStream::withoutSyncingToSearch(static function () use ($streamId, $name, $categoryId): void {
        VodStream::unguarded(static function () use ($streamId, $name, $categoryId): void {
            VodStream::query()->create([
                'stream_id' => $streamId,
                'num' => $streamId,
                'name' => $name,
                'stream_type' => 'movie',
                'added' => now()->toIso8601String(),
                'category_id' => $categoryId,
                'container_extension' => 'mp4',
            ]);
        });
    });
}
