<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    $this->originalKeyPath = dirname(Passport::keyPath('oauth-private.key'));
    $this->originalConfiguredKeyPath = config('passport.key_path');
    $this->originalPrivateKey = config('passport.private_key');
    $this->originalPublicKey = config('passport.public_key');
    $this->keyPath = storage_path('framework/testing/passport-keys-'.str()->random(12));

    config()->set('passport.key_path', $this->keyPath);
    config()->set('passport.private_key');
    config()->set('passport.public_key');
});

afterEach(function (): void {
    Passport::loadKeysFrom($this->originalKeyPath);
    config()->set('passport.key_path', $this->originalConfiguredKeyPath);
    config()->set('passport.private_key', $this->originalPrivateKey);
    config()->set('passport.public_key', $this->originalPublicKey);
    File::deleteDirectory($this->keyPath);
});

it('generates missing Passport keys and preserves existing keys', function (): void {
    $this->artisan('passport:keys:ensure', ['--length' => 1024])
        ->expectsOutputToContain('Encryption keys generated successfully.')
        ->assertSuccessful();

    $privateKeyPath = $this->keyPath.'/oauth-private.key';
    $publicKeyPath = $this->keyPath.'/oauth-public.key';
    $privateKey = File::get($privateKeyPath);
    $publicKey = File::get($publicKeyPath);

    $this->artisan('passport:keys:ensure', ['--length' => 1024])
        ->expectsOutputToContain('Passport encryption keys already exist.')
        ->assertSuccessful();

    expect(File::get($privateKeyPath))->toBe($privateKey)
        ->and(File::get($publicKeyPath))->toBe($publicKey);
});

it('uses configured PEM keys without generating files', function (): void {
    config()->set('passport.private_key', 'private-key');
    config()->set('passport.public_key', 'public-key');

    $this->artisan('passport:keys:ensure')
        ->expectsOutputToContain('Passport encryption keys are configured through the environment.')
        ->assertSuccessful();

    expect(File::isDirectory($this->keyPath))->toBeFalse();
});

it('fails when only one PEM key is configured', function (): void {
    config()->set('passport.private_key', 'private-key');

    $this->artisan('passport:keys:ensure')
        ->expectsOutputToContain('Both PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY must be configured together.')
        ->assertFailed();
});

it('fails rather than replacing an incomplete stored key pair', function (): void {
    File::ensureDirectoryExists($this->keyPath);
    File::put($this->keyPath.'/oauth-private.key', 'existing-private-key');

    $this->artisan('passport:keys:ensure')
        ->expectsOutputToContain('Only one Passport encryption key exists.')
        ->assertFailed();

    expect(File::get($this->keyPath.'/oauth-private.key'))->toBe('existing-private-key')
        ->and(File::exists($this->keyPath.'/oauth-public.key'))->toBeFalse();
});
