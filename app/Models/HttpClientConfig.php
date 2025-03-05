<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HttpClientConfig extends Model
{
    /** @use HasFactory<\Database\Factories\HttpClientConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'user_agent',
        'timeout',
        'connect_timeout',
        'verify_ssl',
        'default_headers',
    ];

    protected $casts = [
        'timeout' => 'integer',
        'connect_timeout' => 'integer',
        'verify_ssl' => 'boolean',
        'default_headers' => 'array',
    ];

    /**
     * Get HTTP client configuration.
     */
    public static function first(): ?self
    {
        return static::query()->first();
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

        if ($this->default_headers) {
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
}
