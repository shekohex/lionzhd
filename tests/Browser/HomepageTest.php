<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

if (! extension_loaded('sockets')) {
    it('requires the sockets extension for browser tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('renders the homepage in light mode', function (): void {
        $this->visit('/')
            ->inLightMode()
            ->assertNoJavaScriptErrors()
            ->waitForText('Log in')
            ->assertTitleContains('Welcome')
            ->assertSee('Log in')
            ->assertSee('Register');
    })->group('browser');
}
