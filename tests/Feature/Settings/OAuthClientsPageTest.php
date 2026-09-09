<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\TokenAbilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

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

it('preserves additional redirect URIs when editing a client', function (): void {
    $admin = User::factory()->admin()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Multi callback client',
        redirectUris: [
            'https://client.example.com/oauth/primary',
            'https://client.example.com/oauth/secondary',
        ],
        user: $admin,
    );

    $this->actingAs($admin)->patch("/settings/oauth-clients/{$client->id}", [
        'name' => 'Updated multi callback client',
        'redirect_uri' => 'https://client.example.com/oauth/updated',
    ])->assertRedirect('/settings/oauth-clients');

    expect($client->fresh()->redirect_uris)->toBe([
        'https://client.example.com/oauth/updated',
        'https://client.example.com/oauth/secondary',
    ]);
});

it('does not offer or allow secret rotation for public PKCE clients', function (): void {
    $admin = User::factory()->admin()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Public client',
        redirectUris: ['https://client.example.com/oauth/callback'],
        confidential: false,
        user: $admin,
    );

    $this->actingAs($admin)
        ->get('/settings/oauth-clients')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('clients.0.id', $client->id)
            ->where('clients.0.confidential', false)
        );

    $this->actingAs($admin)
        ->post("/settings/oauth-clients/{$client->id}/secret")
        ->assertNotFound();

    expect($client->fresh()->confidential())->toBeFalse();
});
