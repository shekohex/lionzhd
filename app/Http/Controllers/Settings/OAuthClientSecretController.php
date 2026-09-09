<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

final class OAuthClientSecretController extends Controller
{
    public function __invoke(Client $client, ClientRepository $clients): RedirectResponse
    {
        abort_if($client->revoked || ! $client->hasGrantType('authorization_code'), 404);
        $clients->regenerateSecret($client);

        return to_route('oauth-clients.index')->with('oauth_client', [
            'id' => $client->id,
            'secret' => $client->plainSecret,
        ]);
    }
}
