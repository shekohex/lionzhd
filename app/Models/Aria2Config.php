<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Models\EnvConfigurable;
use App\Models\Concerns\LoadsFromEnv;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAria2Config
 */
final class Aria2Config extends Model implements EnvConfigurable
{
    use LoadsFromEnv;

    protected $fillable = [
        'host',
        'port',
        'secret',
        'use_ssl',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Get Aria2 configuration.
     */
    public static function first(): ?self
    {
        return self::query()->first();
    }

    /**
     * Get Aria2 configuration based on environment variables.
     */
    public static function fromEnv(): self
    {
        return new self(
            self::envAttributes()
        );
    }

    /**
     * Get configuration as array for client initialization.
     *
     * @return array{endpoint: string, secret: string}
     */
    public function toClientConfig(): array
    {
        return [
            'endpoint' => $this->getRpcEndpoint(),
            'secret' => $this->secret,
        ];
    }

    /**
     * Get the full RPC endpoint URL.
     */
    public function getRpcEndpoint(): string
    {
        $protocol = $this->use_ssl ? 'https' : 'http';

        return "{$protocol}://{$this->host}:{$this->port}/jsonrpc";
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'use_ssl' => 'boolean',
            'port' => 'integer',
            'secret' => 'encrypted',
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
            'host' => config('services.aria2.host'),
            'port' => config('services.aria2.port'),
            'secret' => config('services.aria2.secret'),
            'use_ssl' => false,
        ];
    }
}
