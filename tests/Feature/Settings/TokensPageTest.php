<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('renders token settings and creates one time visible tokens', function (): void {
    $user = User::factory()->memberInternal()->create();

    $this->actingAs($user)
        ->get('/settings/tokens')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/tokens')
            ->where('tokens', [])
            ->has('abilityOptions', 4)
        );

    $this->actingAs($user)
        ->post('/settings/tokens', ['name' => 'Automation', 'abilities' => ['read', 'monitoring:admin']])
        ->assertRedirect('/settings/tokens')
        ->assertSessionHas('api_token');

    expect(PersonalAccessToken::query()->where('name', 'Automation')->exists())->toBeTrue();
});

it('does not allow token abilities above the users account permissions', function (): void {
    $user = User::factory()->memberInternal()->create();

    $this->actingAs($user)
        ->post('/settings/tokens', ['name' => 'Escalation', 'abilities' => ['admin']])
        ->assertForbidden();

    expect(PersonalAccessToken::query()->where('name', 'Escalation')->exists())->toBeFalse();
});

it('revokes only the authenticated users token', function (): void {
    $user = User::factory()->memberInternal()->create();
    $other = User::factory()->memberInternal()->create();
    $tokenId = $user->createToken('Mine', ['read'])->accessToken->id;
    $otherTokenId = $other->createToken('Other', ['read'])->accessToken->id;

    $this->actingAs($user)
        ->delete("/settings/tokens/{$otherTokenId}")
        ->assertNotFound();

    $this->actingAs($user)
        ->delete("/settings/tokens/{$tokenId}")
        ->assertRedirect('/settings/tokens');

    expect(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->whereKey($otherTokenId)->exists())->toBeTrue();
});
