<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\ClientRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();

    if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
        $this->artisan('passport:keys')->assertSuccessful();
    }
});

it('redirects logged-out authorization requests to login', function (): void {
    $admin = User::factory()->admin()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'External service',
        redirectUris: ['https://client.example.com/callback'],
        confidential: true,
        user: $admin,
    );
    $query = http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.example.com/callback',
        'response_type' => 'code',
        'scope' => 'read',
        'state' => 'expected-state',
    ]);
    $authorizationUrl = '/oauth/authorize?'.$query;

    $this->get($authorizationUrl)
        ->assertRedirect('/login')
        ->assertSessionHas('url.intended', url($authorizationUrl));
});

it('lets a user reduce requested scopes and use the resulting OAuth access token', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->memberInternal()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'External service',
        redirectUris: ['https://client.example.com/callback'],
        confidential: true,
        user: $admin,
    );
    $secret = $client->plainSecret;
    $query = http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.example.com/callback',
        'response_type' => 'code',
        'scope' => 'read admin',
        'state' => 'expected-state',
    ]);

    $this->actingAs($user)
        ->get('/oauth/authorize?'.$query)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('auth/oauth/authorize')
            ->where('client.name', 'External service')
            ->where('scopes.0.id', 'read')
            ->where('scopes.0.allowed', true)
            ->where('scopes.1.id', 'admin')
            ->where('scopes.1.allowed', false)
        );

    $authToken = session('authToken');
    $approval = $this->actingAs($user)->post('/oauth/authorize', [
        'client_id' => $client->id,
        'state' => 'expected-state',
        'auth_token' => $authToken,
        'scopes' => ['read'],
    ]);

    $approval->assertRedirect();
    parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);

    expect($callback['state'])->toBe('expected-state')->and($callback['code'])->toBeString();

    $tokenResponse = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $secret,
        'redirect_uri' => 'https://client.example.com/callback',
        'code' => $callback['code'],
    ])->assertOk();

    $accessToken = $tokenResponse->json('access_token');

    $this->withToken($accessToken)
        ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $user->id);

    $this->withToken($accessToken)
        ->getJson('/api/v1/settings/users', ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden();
});
