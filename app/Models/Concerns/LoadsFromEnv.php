<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Throwable;

/*
 * Trait that loads the configuration from environment variables.
 */
trait LoadsFromEnv
{
    abstract public static function fromEnv(): self;

    abstract public static function query(): Builder;

    /**
     * Get the first model or create a new one from the environment variables.
     */
    public static function firstOrFromEnv(): self
    {
        try {
            return static::query()->firstOrFail();
        } catch (Throwable) {
            return static::fromEnv();
        }
    }
}
