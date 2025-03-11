<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Throwable;

/*
 * Trait that loads the configuration from environment variables.
 */
trait LoadsFromEnv
{
    /**
     * Get the first model or create a new one from the environment variables.
     */
    public static function firstOrFromEnv(): self
    {
        try {
            $query = static::query();

            return $query->firstOrFail();
        } catch (Throwable) {
            return static::fromEnv();
        }
    }
}
