<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Auth;

use App\Models\Aria2Config;
use Illuminate\Support\Arr;
use Saloon\Contracts\Authenticator;
use Saloon\Http\PendingRequest;

final readonly class Aria2JsonRpcAuthenticator implements Authenticator
{
    public function __construct(private Aria2Config $aria2Config) {}

    /**
     * Apply the authentication to the request.
     */
    public function set(PendingRequest $pendingRequest): void
    {
        /** @var array<string, mixed> $oldBody */
        $oldBody = $pendingRequest->body()->all();
        // Check if we are sending a batch request
        // If we are, we need to add the token to each request
        // a batch request is an array of requests
        if (array_is_list($oldBody)) {
            foreach ($oldBody as $key => $body) {
                if (is_array($body)) {
                    $oldBody[$key] = $this->addTokenToRequest($body);
                }
            }

            // Set the new body
            $pendingRequest->body()->set($oldBody);
        } else {
            $pendingRequest->body()->set($this->addTokenToRequest($oldBody));
        }
    }

    /**
     * @param  array<mixed>  $body
     * @return mixed[]
     */
    private function addTokenToRequest(array $body): array
    {
        // Add the token to the params
        $token = $this->aria2Config->secret;
        // Token is always the first param
        $params = Arr::prepend($body['params'], "token:{$token}");
        $body['params'] = $params;

        return $body;
    }
}
