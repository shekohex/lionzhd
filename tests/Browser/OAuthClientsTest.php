<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
        $this->artisan('passport:keys')->assertSuccessful();
    }
});

if (! extension_loaded('sockets')) {
    it('requires the sockets extension for OAuth browser tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('creates an OAuth client from the settings UI', function (): void {
        $admin = User::factory()->admin()->create();
        $page = browserLoginAndVisit($admin, route('oauth-clients.index'));

        expect(browserWaitForPath($page, '/settings/oauth-clients'))->toBeTrue();

        $page->waitForText('OAuth clients')
            ->fill('Client name', 'Browser client')
            ->fill('Callback URL', 'https://browser.example.com/oauth/callback')
            ->click('Create OAuth client')
            ->waitForText('Copy this client secret now')
            ->assertSee('Browser client')
            ->assertSee('Client secret')
            ->assertNoJavaScriptErrors();
    })->group('browser');

    it('lets users reduce permissions on the OAuth consent screen', function (): void {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->memberInternal()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'Consent browser client',
            redirectUris: ['https://browser.example.com/oauth/callback'],
            confidential: true,
            user: $admin,
        );
        $query = http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://browser.example.com/oauth/callback',
            'response_type' => 'code',
            'scope' => 'read monitoring:admin',
            'state' => 'browser-state',
        ]);
        $page = browserLoginAndVisit($user, url('/oauth/authorize?'.$query));

        expect(browserWaitForPath($page, '/oauth/authorize'))->toBeTrue();

        $page->waitForText('Consent browser client wants to connect')
            ->assertSee('Read API data')
            ->assertSee('Manage monitoring')
            ->assertSee('Allow access')
            ->assertNoJavaScriptErrors();

        $selectedScopes = $page->script(<<<'JS'
            async () => {
                const checkbox = Array.from(document.querySelectorAll('[data-slot="checkbox"]')).find((candidate) =>
                    candidate.closest('label')?.textContent?.includes('Manage monitoring')
                );

                checkbox?.click();
                await new Promise((resolve) => window.setTimeout(resolve, 100));

                return Array.from(document.querySelectorAll('input[name="scopes[]"]')).map((input) => input.value);
            }
        JS);

        expect($selectedScopes)->toBe(['read']);
    })->group('browser');
}
