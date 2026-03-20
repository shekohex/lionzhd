<?php

declare(strict_types=1);

use App\Actions\BuildPersonalizedCategorySidebar;
use App\Data\CategorySidebarData;
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

    expect(personalizedSidebarVisibleIds($builder($user, MediaType::Movie)))->toBe(['gamma', 'alpha', 'beta']);
    expect(personalizedSidebarVisibleIds($builder($user, MediaType::Series)))->toBe(['beta', 'gamma', 'alpha']);
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

    expect(personalizedSidebarVisibleIds($builder($user, MediaType::Movie)))->toBe(['beta', 'alpha', 'gamma']);

    DB::table('user_category_preferences')
        ->where('user_id', $user->getKey())
        ->where('media_type', MediaType::Movie->value)
        ->where('category_provider_id', 'beta')
        ->update(['pin_rank' => null]);

    expect(personalizedSidebarVisibleIds($builder($user, MediaType::Movie)))->toBe(['alpha', 'gamma', 'beta']);
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

    expect(personalizedSidebarVisibleIds($builder($user, MediaType::Movie)))->toBe(['beta', 'alpha']);

    personalizedSidebarCreateCategory('gamma', 'Gamma');
    personalizedSidebarCreateMovie('gamma');

    expect(personalizedSidebarVisibleIds($builder($user, MediaType::Movie)))->toBe(['beta', 'alpha', 'gamma']);
});

it('keeps zero item categories editable even when they cannot navigate', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('editable-zero', 'Editable Zero');
    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateMovie('alpha');

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie);
    $zeroItem = personalizedSidebarVisibleItem($sidebar, 'editable-zero');

    expect($zeroItem->canNavigate)->toBeFalse();
    expect($zeroItem->canEdit)->toBeTrue();
    expect($zeroItem->disabled)->toBeTrue();
});

it('moves hidden categories into a hidden collection and exposes hidden selection metadata', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory('beta', 'Beta');
    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 1, isHidden: true);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie, 'beta');

    expect(personalizedSidebarVisibleIds($sidebar))->toBe(['alpha']);
    expect(personalizedSidebarHiddenIds($sidebar))->toBe(['beta']);
    expect($sidebar->selectedCategoryIsHidden)->toBeTrue();
    expect($sidebar->selectedCategoryName)->toBe('Beta');
    expect($sidebar->canReset)->toBeTrue();
});

it('keeps ignored categories visible and sorts them below non ignored rows', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory('beta', 'Beta');
    personalizedSidebarCreateCategory('gamma', 'Gamma');

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');
    personalizedSidebarCreateMovie('gamma');

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 1, isIgnored: true);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'gamma', sortOrder: 2);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie);

    expect(personalizedSidebarVisibleIds($sidebar))->toBe(['alpha', 'gamma', 'beta']);
    expect(personalizedSidebarVisibleItem($sidebar, 'beta')->isIgnored)->toBeTrue();
    expect(personalizedSidebarVisibleItem($sidebar, 'beta')->isHidden)->toBeFalse();
    expect($sidebar->visibleItems->last()->id)->toBe(Category::UNCATEGORIZED_VOD_PROVIDER_ID);
});

it('marks selected ignored categories without moving them into hidden items', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory('beta', 'Beta');

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 1, isIgnored: true);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie, 'beta');

    expect(personalizedSidebarVisibleIds($sidebar))->toBe(['alpha', 'beta']);
    expect(personalizedSidebarHiddenIds($sidebar))->toBe([]);
    expect($sidebar->selectedCategoryIsHidden)->toBeFalse();
    expect($sidebar->selectedCategoryIsIgnored)->toBeTrue();
    expect($sidebar->selectedCategoryName)->toBe('Beta');
});

it('keeps ignored and hidden categories in separate collections', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory('beta', 'Beta');
    personalizedSidebarCreateCategory('gamma', 'Gamma');

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');
    personalizedSidebarCreateMovie('gamma');

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 0, isIgnored: true);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 1, isHidden: true);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'gamma', sortOrder: 2);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie);

    expect(personalizedSidebarVisibleIds($sidebar))->toBe(['gamma', 'alpha']);
    expect(personalizedSidebarHiddenIds($sidebar))->toBe(['beta']);
    expect(personalizedSidebarVisibleItem($sidebar, 'alpha')->isIgnored)->toBeTrue();
    expect($sidebar->hiddenItems->first()?->isIgnored)->toBeFalse();
});

it('keeps all categories first and uncategorized last without exposing fixed rows as editable', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateMovie('alpha');

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie);

    expect($sidebar->visibleItems->first()->id)->toBe('all-categories');
    expect($sidebar->visibleItems->last()->id)->toBe(Category::UNCATEGORIZED_VOD_PROVIDER_ID);
    expect(personalizedSidebarVisibleItem($sidebar, 'all-categories')->canEdit)->toBeFalse();
    expect(personalizedSidebarVisibleItem($sidebar, Category::UNCATEGORIZED_VOD_PROVIDER_ID)->canEdit)->toBeFalse();
});

it('search derives visible inputs from visible items while keeping ignored rows searchable and hidden rows out of results', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory('beta', 'Beta');
    personalizedSidebarCreateCategory('gamma', 'Gamma');
    personalizedSidebarCreateCategory(Category::UNCATEGORIZED_VOD_PROVIDER_ID, 'Uncategorized');

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie('beta');
    personalizedSidebarCreateMovie('gamma');
    personalizedSidebarCreateMovie(Category::UNCATEGORIZED_VOD_PROVIDER_ID);

    personalizedSidebarInsertPreference($user, MediaType::Movie, 'alpha', sortOrder: 0);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'beta', sortOrder: 1, isHidden: true);
    personalizedSidebarInsertPreference($user, MediaType::Movie, 'gamma', sortOrder: 2, isIgnored: true);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie);
    $searchableVisibleIds = $sidebar->visibleItems
        ->toCollection()
        ->reject(static fn (CategorySidebarItemData $item): bool => $item->id === 'all-categories')
        ->pluck('id')
        ->values()
        ->all();

    expect($searchableVisibleIds)->toBe(['alpha', 'gamma', Category::UNCATEGORIZED_VOD_PROVIDER_ID]);
    expect(personalizedSidebarVisibleIds($sidebar))->toBe(['alpha', 'gamma']);
    expect(personalizedSidebarHiddenIds($sidebar))->toBe(['beta']);
    expect(personalizedSidebarVisibleItem($sidebar, 'gamma')->isIgnored)->toBeTrue();
    expect(personalizedSidebarVisibleItem($sidebar, 'gamma')->isHidden)->toBeFalse();
});

it('search keeps all categories synthetic and leaves uncategorized as the last visible match candidate', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    personalizedSidebarCreateCategory('alpha', 'Alpha');
    personalizedSidebarCreateCategory(Category::UNCATEGORIZED_VOD_PROVIDER_ID, 'Uncategorized');

    personalizedSidebarCreateMovie('alpha');
    personalizedSidebarCreateMovie(Category::UNCATEGORIZED_VOD_PROVIDER_ID);

    actingAs($user);

    $builder = app(BuildPersonalizedCategorySidebar::class);
    $sidebar = $builder($user, MediaType::Movie);
    $visibleItems = $sidebar->visibleItems->toCollection();
    $searchMatches = $visibleItems
        ->reject(static fn (CategorySidebarItemData $item): bool => $item->id === 'all-categories')
        ->values();

    expect($visibleItems->first()?->id)->toBe('all-categories');
    expect($searchMatches->last()?->id)->toBe(Category::UNCATEGORIZED_VOD_PROVIDER_ID);
    expect($searchMatches->last()?->isUncategorized)->toBeTrue();
    expect($searchMatches->last()?->canEdit)->toBeFalse();
});

function personalizedSidebarVisibleIds(CategorySidebarData $sidebar): array
{
    return $sidebar->visibleItems
        ->toCollection()
        ->filter(static fn (CategorySidebarItemData $item): bool => $item->id !== 'all-categories' && ! $item->isUncategorized)
        ->pluck('id')
        ->values()
        ->all();
}

function personalizedSidebarHiddenIds(CategorySidebarData $sidebar): array
{
    return $sidebar->hiddenItems
        ->toCollection()
        ->pluck('id')
        ->values()
        ->all();
}

function personalizedSidebarVisibleItem(CategorySidebarData $sidebar, string $id): CategorySidebarItemData
{
    /** @var CategorySidebarItemData|null $item */
    $item = $sidebar->visibleItems
        ->toCollection()
        ->first(static fn (CategorySidebarItemData $item): bool => $item->id === $id);

    if ($item === null) {
        throw new RuntimeException(sprintf('Visible item %s not found.', $id));
    }

    return $item;
}

function personalizedSidebarCreateCategory(string $providerId, string $name, bool $inVod = true, bool $inSeries = false): void
{
    Category::query()->updateOrCreate(['provider_id' => $providerId], [
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

function personalizedSidebarInsertPreference(User $user, MediaType $mediaType, string $categoryProviderId, int $sortOrder, ?int $pinRank = null, bool $isHidden = false, bool $isIgnored = false): void
{
    DB::table('user_category_preferences')->insert([
        'user_id' => $user->getKey(),
        'media_type' => $mediaType->value,
        'category_provider_id' => $categoryProviderId,
        'pin_rank' => $pinRank,
        'sort_order' => $sortOrder,
        'is_hidden' => $isHidden,
        'is_ignored' => $isIgnored,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
