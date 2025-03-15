<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv;

use App\Models\XtreamCodesConfig;
use Saloon\Http\Auth\MultiAuthenticator;
use Saloon\Http\Auth\QueryAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

final class XtreamCodesConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 60;

    protected int $requestTimeout = 120;

    public function __construct(private readonly XtreamCodesConfig $xtreamCodesConfig) {}

    /**
     * The Base URL of the API
     */
    public function resolveBaseUrl(): string
    {
        return $this->xtreamCodesConfig->baseUrl();
    }

    /**
     * Default authenticator used.
     */
    protected function defaultAuth(): MultiAuthenticator
    {
        $credentials = $this->xtreamCodesConfig->credentials();

        return new MultiAuthenticator(
            new QueryAuthenticator('username', $credentials['username']),
            new QueryAuthenticator('password', $credentials['password']),
        );
    }

    /**
     * Default headers for every request
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => config('saloon.default_user_agent'),
        ];
    }

    /**
     * Default HTTP client options
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'stream' => false,
        ];
    }
}
