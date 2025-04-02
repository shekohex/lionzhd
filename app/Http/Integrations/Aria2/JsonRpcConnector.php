<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2;

use App\Http\Integrations\Aria2\Auth\Aria2JsonRpcAuthenticator;
use App\Models\Aria2Config;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;
use Throwable;

final class JsonRpcConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use HasTimeout;

    public ?int $tries = 3;

    public ?bool $useExponentialBackoff = true;

    protected int $connectTimeout = 60;

    protected int $requestTimeout = 120;

    public function __construct(private readonly Aria2Config $aria2Config) {}

    /**
     * The Base URL of the API
     */
    public function resolveBaseUrl(): string
    {
        return $this->aria2Config->baseUrl();
    }

    public function getRequestException(Response $response, ?Throwable $senderException): JsonRpcException
    {
        return new JsonRpcException($response, $senderException);
    }

    protected function defaultAuth(): Aria2JsonRpcAuthenticator
    {
        return new Aria2JsonRpcAuthenticator($this->aria2Config);
    }
}
