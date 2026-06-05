<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates hashed personal access tokens with scoped abilities', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken('external-api', ['read', 'download-operations']);

    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue();
    expect($token->accessToken->tokenable_id)->toBe($user->getKey());
    expect($token->accessToken->tokenable_type)->toBe($user->getMorphClass());
    expect($token->accessToken->can('read'))->toBeTrue();
    expect($token->accessToken->cant('settings:write'))->toBeTrue();
    expect($token->accessToken->token)->not->toBe($token->plainTextToken);
});
