<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TokenAbilityRegistry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

final class OAuthClientsController extends Controller
{
    public function index(TokenAbilityRegistry $abilities): Response
    {
        $clients = Passport::client()->newQuery()
            ->where('revoked', false)
            ->latest()
            ->get()
            ->filter(static fn (Client $client): bool => $client->hasGrantType('authorization_code'))
            ->map(static fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'redirect_uri' => $client->redirect_uris[0] ?? '',
                'created_at' => $client->created_at,
            ])
            ->values();

        return Inertia::render('settings/oauth-clients', [
            'clients' => $clients,
            'scopeOptions' => $abilities->options(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $validated = $this->validateClient($request);
        $client = $user->oauthApps()->forceCreate([
            'name' => $validated['name'],
            'secret' => Str::random(40),
            'provider' => null,
            'redirect_uris' => [$validated['redirect_uri']],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);

        return to_route('oauth-clients.index')->with('oauth_client', [
            'id' => $client->id,
            'secret' => $client->plainSecret,
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->ensureAuthorizationClient($client);
        $validated = $this->validateClient($request);

        $client->forceFill([
            'name' => $validated['name'],
            'redirect_uris' => [$validated['redirect_uri']],
        ])->save();

        return to_route('oauth-clients.index')->with('success', 'OAuth client updated.');
    }

    public function destroy(Client $client, ClientRepository $clients): RedirectResponse
    {
        $this->ensureAuthorizationClient($client);
        $clients->delete($client);

        return to_route('oauth-clients.index')->with('success', 'OAuth client revoked.');
    }

    /** @return array{name: string, redirect_uri: string} */
    private function validateClient(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'string', 'max:2048', 'url'],
        ]);
    }

    private function ensureAuthorizationClient(Client $client): void
    {
        abort_if($client->revoked || ! $client->hasGrantType('authorization_code'), 404);
    }
}
