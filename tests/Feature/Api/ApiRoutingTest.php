<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires sanctum authentication for api v1 routes', function (): void {
    $this->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '401');
});

it('returns json api content for authenticated api v1 routes', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['profile:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.attributes.email', $user->email);
});

it('requires the profile read ability for the profile endpoint', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['movies:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '403');
});
