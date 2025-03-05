<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aria2Config extends Model
{
    /** @use HasFactory<\Database\Factories\Aria2ConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'host',
        'port',
        'secret',
        'use_ssl',
    ];

    protected $casts = [
        'port' => 'integer',
        'use_ssl' => 'boolean',
    ];

    /**
     * Get Aria2 configuration.
     */
    public static function first(): ?self
    {
        return static::query()->first();
    }

    /**
     * Get configuration as array for client initialization.
     *
     * @return array<string,mixed>
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
}
