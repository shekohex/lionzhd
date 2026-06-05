<?php

declare(strict_types=1);

namespace App\Concerns;

use Throwable;

/*
 * Trait that loads the configuration from environment variables.
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait LoadsFromEnv
{
    abstract public static function fromEnv(): self;

    /**
     * Get the first model or create a new one from the environment variables.
     */
    public static function firstOrFromEnv(): self
    {
        try {
            return static::query()->sole();
        } catch (Throwable) {
            return static::fromEnv();
        }
    }
}
