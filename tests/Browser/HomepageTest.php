<?php

declare(strict_types=1);

if (! extension_loaded('sockets')) {
    it('requires the sockets extension for browser tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('renders the homepage in light mode', function (): void {
        $this->visit('/')
            ->inLightMode()
            ->assertTitleContains('Welcome')
            ->assertSee('Log in')
            ->assertSee('Register')
            ->assertNoJavaScriptErrors();
    })->group('browser');
}
