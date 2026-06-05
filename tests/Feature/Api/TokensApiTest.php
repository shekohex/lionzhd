<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('creates lists and revokes api tokens without exposing stored secrets', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;

    $createResponse = $this->withToken($token)
        ->postJson('/api/v1/tokens', [
            'name' => 'Automation',
            'abilities' => ['read', 'download-operations'],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'tokens')
        ->assertJsonPath('data.attributes.name', 'Automation')
        ->assertJsonPath('data.attributes.abilities', ['read', 'download-operations'])
        ->assertJsonPath('data.attributes.plain_text_token', fn (mixed $value): bool => is_string($value) && str_contains($value, '|'));

    $createdTokenId = (int) $createResponse->json('data.id');
    $plainTextToken = (string) $createResponse->json('data.attributes.plain_text_token');

    expect(PersonalAccessToken::query()->whereKey($createdTokenId)->value('token'))->not->toBe($plainTextToken);

    $listResponse = $this->withToken($token)
        ->getJson('/api/v1/tokens', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.0.type', 'tokens')
        ->assertJsonMissing(['plain_text_token' => $plainTextToken]);

    expect(collect($listResponse->json('data'))->pluck('id')->all())->toContain((string) $createdTokenId);

    $this->withToken($token)
        ->deleteJson("/api/v1/tokens/{$createdTokenId}", [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $createdTokenId);

    expect(PersonalAccessToken::query()->whereKey($createdTokenId)->exists())->toBeFalse();
});

it('validates token abilities and immediately invalidates revoked tokens', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    $tokenId = (int) str($token)->before('|')->toString();

    $this->withToken($token)
        ->postJson('/api/v1/tokens', [
            'name' => 'Bad token',
            'abilities' => ['root'],
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.source.parameter', 'abilities.0');

    $this->withToken($token)
        ->deleteJson("/api/v1/tokens/{$tokenId}", [], ['Accept' => 'application/vnd.api+json'])
        ->assertOk();

    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertUnauthorized();
});

it('does not allow revoking another users token', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = $user->createToken('external-api', ['read'])->plainTextToken;
    $otherTokenId = $other->createToken('other', ['read'])->accessToken->id;

    $this->withToken($token)
        ->deleteJson("/api/v1/tokens/{$otherTokenId}", [], ['Accept' => 'application/vnd.api+json'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/vnd.api+json');
});
