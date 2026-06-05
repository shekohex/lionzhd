<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('updates and resets category preferences through the api', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    apiPreferencesCreateCategory('action', true, false);
    apiPreferencesCreateCategory('hidden', true, false);
    apiPreferencesCreateCategory('ignored', true, false);

    $this->withToken($token)
        ->patchJson('/api/v1/preferences/categories/movie', [
            'pinned_ids' => ['action'],
            'visible_ids' => ['action'],
            'hidden_ids' => ['hidden'],
            'ignored_ids' => ['ignored'],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'category-preferences')
        ->assertJsonPath('data.id', 'movie')
        ->assertJsonPath('data.attributes.pinned_ids', ['action'])
        ->assertJsonPath('data.attributes.visible_ids', ['action'])
        ->assertJsonPath('data.attributes.hidden_ids', ['hidden'])
        ->assertJsonPath('data.attributes.ignored_ids', ['ignored']);

    expect(UserCategoryPreference::query()->where('user_id', $user->id)->where('media_type', 'movie')->count())->toBe(3);

    $this->withToken($token)
        ->deleteJson('/api/v1/preferences/categories/movie', [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.hidden_ids', [])
        ->assertJsonPath('data.attributes.ignored_ids', []);

    expect(UserCategoryPreference::query()->where('user_id', $user->id)->where('media_type', 'movie')->count())->toBe(0);
});

it('validates category preference payloads and requires read ability', function (): void {
    $user = User::factory()->create();
    $wrongScopeToken = $user->createToken('external-api', ['server-download'])->plainTextToken;
    apiPreferencesCreateCategory('drama', false, true);

    $this->withToken($wrongScopeToken)
        ->patchJson('/api/v1/preferences/categories/series', [], ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json');

    Sanctum::actingAs($user, ['read']);

    $this->flushHeaders()
        ->patchJson('/api/v1/preferences/categories/movie', [
            'visible_ids' => ['drama'],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.source.parameter', 'visible_ids');
});

function apiPreferencesCreateCategory(string $providerId, bool $inVod, bool $inSeries): Category
{
    return Category::query()->create([
        'provider_id' => $providerId,
        'name' => ucfirst($providerId),
        'in_vod' => $inVod,
        'in_series' => $inSeries,
        'is_system' => false,
    ]);
}
