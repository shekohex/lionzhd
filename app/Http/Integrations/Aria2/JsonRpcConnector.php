<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2;

use App\Http\Integrations\Aria2\Auth\Aria2JsonRpcAuthenticator;
use App\Models\Aria2Config;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

final class JsonRpcConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 60;

    protected int $requestTimeout = 120;

    protected function __construct(private readonly Aria2Config $araia2Config) {}

    /**
     * The Base URL of the API
     */
    public function resolveBaseUrl(): string
    {
        return $this->araia2Config->getRpcEndpoint();
    }

    public function hasRequestFailed(Response $response): bool
    {
        if ($response->failed()) {
            return true;
        }

        return $response->json('error') !== null;
    }

    protected function defaultAuth(): Aria2JsonRpcAuthenticator
    {
        return new Aria2JsonRpcAuthenticator($this->araia2Config);
    }

    /**
     * Default HTTP client options
     */
    protected function defaultConfig(): array
    {
        return [
            'stream' => true,
        ];
    }
}
