<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\patch;

uses(RefreshDatabase::class);

it('requires authentication to mutate category preferences', function (): void {
    createCategory('movie-action', 'Action', inVod: true);

    patch(route('category-preferences.update', ['mediaType' => MediaType::Movie->value]), validSnapshot())
        ->assertRedirect(route('login'));

    delete(route('category-preferences.reset', ['mediaType' => MediaType::Movie->value]))
        ->assertRedirect(route('login'));
});

it('persists a category preference snapshot for the authenticated user and requested media type', function (): void {
    $user = User::factory()->create();

    createCategory('movie-action', 'Action', inVod: true);
    createCategory('movie-comedy', 'Comedy', inVod: true);
    createCategory('movie-drama', 'Drama', inVod: true);
    createCategory('series-crime', 'Crime', inSeries: true);

    $browseUrl = route('movies', ['category' => 'movie-comedy', 'view' => 'grid']);

    $response = test()->actingAs($user)
        ->from($browseUrl)
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Movie->value]), [
            'pinned_ids' => ['movie-comedy'],
            'visible_ids' => ['movie-action', 'movie-comedy'],
            'hidden_ids' => ['movie-drama'],
        ]);

    $response->assertRedirect($browseUrl);

    assertDatabaseHas('user_category_preferences', [
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-action',
        'pin_rank' => null,
        'is_hidden' => false,
    ]);

    assertDatabaseHas('user_category_preferences', [
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-comedy',
        'pin_rank' => 1,
        'is_hidden' => false,
    ]);

    assertDatabaseHas('user_category_preferences', [
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-drama',
        'pin_rank' => null,
        'is_hidden' => true,
    ]);

    assertDatabaseMissing('user_category_preferences', [
        'user_id' => $user->id,
        'media_type' => MediaType::Series->value,
        'category_provider_id' => 'series-crime',
    ]);
});

it('rejects a sixth pinned category with a clear validation message', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 6) as $number) {
        createCategory("movie-{$number}", "Movie {$number}", inVod: true);
    }

    $response = test()->actingAs($user)
        ->from(route('movies'))
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Movie->value]), [
            'pinned_ids' => ['movie-1', 'movie-2', 'movie-3', 'movie-4', 'movie-5', 'movie-6'],
            'visible_ids' => ['movie-1', 'movie-2', 'movie-3', 'movie-4', 'movie-5', 'movie-6'],
            'hidden_ids' => [],
        ]);

    $response->assertSessionHasErrors([
        'pinned_ids' => 'You can pin up to 5 categories per media type.',
    ]);

    expect(
        UserCategoryPreference::query()
            ->where('user_id', $user->id)
            ->count()
    )->toBe(0);
});

it('rejects invalid category ids for the requested media type snapshot', function (): void {
    $user = User::factory()->create();

    createCategory('movie-action', 'Action', inVod: true);
    createCategory('series-crime', 'Crime', inSeries: true);

    $response = test()->actingAs($user)
        ->from(route('movies'))
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Movie->value]), [
            'pinned_ids' => ['movie-action'],
            'visible_ids' => ['all-categories', 'series-crime'],
            'hidden_ids' => ['series-crime'],
        ]);

    $response->assertSessionHasErrors(['visible_ids', 'hidden_ids', 'pinned_ids']);
});

it('preserves stored non pinned order when pinning and isolates writes per user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createCategory('movie-action', 'Action', inVod: true);
    createCategory('movie-comedy', 'Comedy', inVod: true);
    createCategory('movie-drama', 'Drama', inVod: true);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-action',
        'pin_rank' => null,
        'sort_order' => 1,
        'is_hidden' => false,
    ]);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-comedy',
        'pin_rank' => null,
        'sort_order' => 2,
        'is_hidden' => false,
    ]);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-drama',
        'pin_rank' => null,
        'sort_order' => 3,
        'is_hidden' => false,
    ]);

    UserCategoryPreference::query()->create([
        'user_id' => $otherUser->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-drama',
        'pin_rank' => null,
        'sort_order' => 9,
        'is_hidden' => true,
    ]);

    $response = test()->actingAs($user)
        ->from(route('movies', ['category' => 'movie-drama']))
        ->patch(route('category-preferences.update', ['mediaType' => MediaType::Movie->value]), [
            'pinned_ids' => ['movie-drama'],
            'visible_ids' => ['movie-drama', 'movie-action', 'movie-comedy'],
            'hidden_ids' => [],
        ]);

    $response->assertRedirect(route('movies', ['category' => 'movie-drama']));

    assertDatabaseHas('user_category_preferences', [
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-drama',
        'pin_rank' => 1,
        'sort_order' => 3,
        'is_hidden' => false,
    ]);

    assertDatabaseHas('user_category_preferences', [
        'user_id' => $otherUser->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-drama',
        'pin_rank' => null,
        'sort_order' => 9,
        'is_hidden' => true,
    ]);
});

it('resets only the requested media type and redirects back to the browse url', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-action',
        'pin_rank' => 1,
        'sort_order' => 1,
        'is_hidden' => false,
    ]);

    UserCategoryPreference::query()->create([
        'user_id' => $user->id,
        'media_type' => MediaType::Series->value,
        'category_provider_id' => 'series-crime',
        'pin_rank' => null,
        'sort_order' => 1,
        'is_hidden' => true,
    ]);

    UserCategoryPreference::query()->create([
        'user_id' => $otherUser->id,
        'media_type' => MediaType::Series->value,
        'category_provider_id' => 'series-family',
        'pin_rank' => null,
        'sort_order' => 1,
        'is_hidden' => false,
    ]);

    $browseUrl = route('series', ['category' => 'series-crime', 'view' => 'grid']);

    $response = test()->actingAs($user)
        ->from($browseUrl)
        ->delete(route('category-preferences.reset', ['mediaType' => MediaType::Series->value]));

    $response->assertRedirect($browseUrl);

    assertDatabaseMissing('user_category_preferences', [
        'user_id' => $user->id,
        'media_type' => MediaType::Series->value,
        'category_provider_id' => 'series-crime',
    ]);

    assertDatabaseHas('user_category_preferences', [
        'user_id' => $user->id,
        'media_type' => MediaType::Movie->value,
        'category_provider_id' => 'movie-action',
    ]);

    assertDatabaseHas('user_category_preferences', [
        'user_id' => $otherUser->id,
        'media_type' => MediaType::Series->value,
        'category_provider_id' => 'series-family',
    ]);
});

function createCategory(string $providerId, string $name, bool $inVod = false, bool $inSeries = false): Category
{
    return Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => $inVod,
        'in_series' => $inSeries,
        'is_system' => false,
    ]);
}

function validSnapshot(): array
{
    return [
        'pinned_ids' => [],
        'visible_ids' => ['movie-action'],
        'hidden_ids' => [],
    ];
}
