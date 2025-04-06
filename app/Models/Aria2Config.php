<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LoadsFromEnv;
use App\Contracts\Models\EnvConfigurable;
use App\Data\Aria2ConfigData;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\WithData;

/**
 * @mixin IdeHelperAria2Config
 */
final class Aria2Config extends Model implements EnvConfigurable
{
    use LoadsFromEnv;
    use WithData;

    protected $dataClass = Aria2ConfigData::class;

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
     * Get the host of the RPC server.
     */
    public function baseUrl(): string
    {
        return "{$this->host}:{$this->port}";
    }

    /**
     * Get the full RPC endpoint URL.
     */
    public function getRpcEndpoint(): string
    {
        return "{$this->baseUrl()}/jsonrpc";
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
