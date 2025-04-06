<?php

declare(strict_types=1);

namespace App\Contracts\Models;

interface EnvConfigurable
{
    /**
     * Create an instance of the model from the environment variables or the configuration files.
     */
    public static function fromEnv(): self;
}
