<?php

declare(strict_types=1);

use App\Actions\BuildPersonalizedCategorySidebar;
use App\Data\CategorySidebarItemData;
use App\Enums\MediaType;
use App\Models\Category;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('reads preference rows only for the requested media type', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha', inVod: true, inSeries: true);
    personalizedSidebarCreateCategory('beta', 'Beta', inVod: true, inSeries: true);
    personalizedSidebarCreateCategory('gamma', 'Gamma', inVod: true, inSeries: true);

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');
    personalizedSidebarCreateMovie('gamma');
    personalizedSidebarCreateSeries('alpha');
    personalizedSidebarCreateSeries('beta');
    personalizedSidebarCreateSeries('gamma');

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'gamma', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 1);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 2);

    personalizedSidebarInsertPreference($user, MediaType::Series, 'beta', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Series, 'gamma', sortOrder: 1);
    personalizedSidebarInsertPreference($user, MediaType::Series, 'alpha', sortOrder: 2);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);

    expect(personalizedSidebarIds($builder($user, MediaType::Movie)))->toBe(['gamma', 'alpha', 'beta']);
    expect(personalizedSidebarIds($builder($user, MediaType::Series)))->toBe(['beta', 'gamma', 'alpha']);
});

it('renders pinned rows above non pinned rows and restores stored order after unpin', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory('beta', 'Beta');
    personalizedSidebarCreateCategory('gamma', 'Gamma');

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');
    personalizedSidebarCreateMovie('gamma');

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 2, pinRank: 1);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'gamma', sortOrder: 1);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);

    expect(personalizedSidebarIds($builder($user, MediaType::Movie)))->toBe(['beta', 'alpha', 'gamma']);

    DB::table('user_category_preferences')
        ->where('user_id', $user->getKey())
        ->where('media_type', MediaType::Movie->value)
        ->where('category_provider_id', 'beta')
        ->update(['pin_rank' => null]);

    expect(personalizedSidebarIds($builder($user, MediaType::Movie)))->toBe(['alpha', 'gamma', 'beta']);
});

it('appends newly synced categories after the user ordered non pinned rows', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory('beta', 'Beta');

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 1);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);

    expect(personalizedSidebarIds($builder($user, MediaType::Movie)))->toBe(['beta', 'alpha']);

    personalizedSidebarCreateCategory('gamma', 'Gamma');
    personalizedSidebarCreateMovie('gamma');

    expect(personalizedSidebarIds($builder($user, MediaType::Movie)))->toBe(['beta', 'alpha', 'gamma']);
});

function personalizedSidebarIds(array $items): array
{
    return collect($items)
        ->filter(fn (CategorySidebarItemData $item): bool => ! $item->isUncategorized)
        ->pluck('id')
        ->values()
        ->all();
}

function personalizedSidebarCreateCategory(string $providerId, string $name, bool $inVod = true, bool $inSeries = false): void
{
    Category::query()->create([
        'provider_id' => $providerId,
        'name' => $name,
        'in_vod' => $inVod,
        'in_series' => $inSeries,
        'is_system' => false,
    ]);
}

function personalizedSidebarCreateMovie(string $categoryId): void
{
    static $streamId = 1000;

    $streamId++;

    VodStream::withoutSyncingToSearch(static function () use ($streamId, $categoryId): void {
        VodStream::unguarded(static function () use ($streamId, $categoryId): void {
            VodStream::query()->create([
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
}

function personalizedSidebarCreateSeries(string $categoryId): void
{
    static $seriesId = 2000;

    $seriesId++;

    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => sprintf('Series %d', $seriesId),
        'category_id' => $categoryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function personalizedSidebarInsertPreference(User $user, MediaType $mediaType, string $categoryProviderId, int $sortOrder, ?int $pinRank = null): void
{
    DB::table('user_category_preferences')->insert([
        'user_id' => $user->getKey(),
        'media_type' => $mediaType->value,
        'category_provider_id' => $categoryProviderId,
        'pin_rank' => $pinRank,
        'sort_order' => $sortOrder,
        'is_hidden' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
