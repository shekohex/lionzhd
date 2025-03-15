<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Auth;

use App\Models\Aria2Config;
use Illuminate\Support\Arr;
use Saloon\Contracts\Authenticator;
use Saloon\Http\PendingRequest;

final readonly class Aria2JsonRpcAuthenticator implements Authenticator
{
    public function __construct(private Aria2Config $araia2Config) {}

    /**
     * Apply the authentication to the request.
     */
    public function set(PendingRequest $pendingRequest): void
    {
        /** @var array<string, mixed> $oldBody */
        $oldBody = $pendingRequest->body()->all();
        // Add the token to the params
        $token = $this->araia2Config->secret;
        // Token is always the first param
        $params = Arr::prepend($oldBody['params'], "token:{$token}");
        $oldBody['params'] = $params;
        $pendingRequest->body()->set($oldBody);
    }
}
