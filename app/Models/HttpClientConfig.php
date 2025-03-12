<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LoadsFromEnv;
use App\Contracts\Models\EnvConfigurable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * @mixin IdeHelperHttpClientConfig
 */
final class HttpClientConfig extends Model implements EnvConfigurable
{
    use LoadsFromEnv;

    protected $fillable = [
        'user_agent',
        'timeout',
        'connect_timeout',
        'verify_ssl',
        'default_headers',
    ];

    /**
     * Get HTTP client configuration.
     */
    public static function first(): ?self
    {
        return self::query()->first();
    }

    /**
     * Get HTTP client configuration based on environment variables.
     */
    public static function fromEnv(): self
    {
        return new self(
            self::envAttributes()
        );
    }

    /**
     * Create a new HTTP client with the configured settings.
     */
    public function createClient(): PendingRequest
    {
        $client = Http::withUserAgent($this->user_agent)
            ->timeout($this->timeout)
            ->connectTimeout($this->connect_timeout)
            ->acceptJson()
            ->retry(5, throw: false);

        if (! $this->verify_ssl) {
            $client->withoutVerifying();
        }

        if (! empty($this->default_headers)) {
            $client->withHeaders($this->default_headers);
        }

        return $client;
    }

    /**
     * Get configuration as array.
     *
     * @return array<string,mixed>
     */
    public function toClientConfig(): array
    {
        return [
            'user_agent' => $this->user_agent,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connect_timeout,
            'verify_ssl' => $this->verify_ssl,
            'default_headers' => $this->default_headers ?? [],
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'timeout' => 'integer',
            'connect_timeout' => 'integer',
            'verify_ssl' => 'boolean',
            'default_headers' => 'array',
        ];
    }

    /**
     * Get the attributes that should be retrieved from the environment.
     *
     * @return array<string,mixed>
     */
    private static function envAttributes(): array
    {
        return [
            'user_agent' => env('HTTP_CLIENT_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:133.0) Gecko/20100101 Firefox/133.0'),
            'timeout' => 120,
            'connect_timeout' => 30,
            'verify_ssl' => true,
        ];
    }
}
