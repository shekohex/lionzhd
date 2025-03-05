<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Meilisearch configuration model.
 */
class MeilisearchConfig extends Model
{
    /** @use HasFactory<\Database\Factories\MeilisearchConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'api_endpoint',
        'api_key',
    ];

    /**
     * Get Meilisearch configuration.
     */
    public static function first(): ?self
    {
        return static::query()->first();
    }

    /**
     * Get configuration as array for client initialization.
     *
     * @return array<string, string>
     */
    public function toClientConfig(): array
    {
        return [
            'host' => $this->api_endpoint,
            'key' => $this->api_key,
        ];
    }
}
