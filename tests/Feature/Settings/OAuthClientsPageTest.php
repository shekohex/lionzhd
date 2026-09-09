<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\TokenAbilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('allows only admins to manage OAuth clients', function (): void {
    $member = User::factory()->memberInternal()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($member)->get('/settings/oauth-clients')->assertForbidden();

    $this->actingAs($admin)
        ->get('/settings/oauth-clients')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/oauth-clients')
            ->where('clients', [])
            ->has('scopeOptions', count(TokenAbilityRegistry::ALLOWED_ABILITIES))
        );
});

it('creates updates rotates and revokes confidential authorization code clients', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/settings/oauth-clients', [
        'name' => 'Home Assistant',
        'redirect_uri' => 'https://home.example.com/oauth/callback',
    ]);

    $response->assertRedirect('/settings/oauth-clients')->assertSessionHas('oauth_client');
    $credentials = session('oauth_client');
    $client = Client::query()->findOrFail($credentials['id']);

    expect($client->name)->toBe('Home Assistant')
        ->and($client->redirect_uris)->toBe(['https://home.example.com/oauth/callback'])
        ->and($client->hasGrantType('authorization_code'))->toBeTrue()
        ->and(Hash::check($credentials['secret'], $client->getRawOriginal('secret')))->toBeTrue();

    $this->actingAs($admin)->patch("/settings/oauth-clients/{$client->id}", [
        'name' => 'Updated client',
        'redirect_uri' => 'https://home.example.com/oauth/updated',
    ])->assertRedirect('/settings/oauth-clients');

    $oldSecret = $client->getRawOriginal('secret');
    $this->actingAs($admin)
        ->post("/settings/oauth-clients/{$client->id}/secret")
        ->assertRedirect('/settings/oauth-clients')
        ->assertSessionHas('oauth_client');

    $client->refresh();
    expect($client->name)->toBe('Updated client')
        ->and($client->redirect_uris)->toBe(['https://home.example.com/oauth/updated'])
        ->and($client->getRawOriginal('secret'))->not->toBe($oldSecret);

    $this->actingAs($admin)
        ->delete("/settings/oauth-clients/{$client->id}")
        ->assertRedirect('/settings/oauth-clients');

    expect($client->fresh()->revoked)->toBeTrue();
});
