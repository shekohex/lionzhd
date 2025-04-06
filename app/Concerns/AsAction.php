<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Fluent;

trait AsAction
{
    /**
     * Create a new instance of the class using the application service container.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @see static::handle()
     */
    public static function run(mixed ...$arguments): mixed
    {
        return static::make()->handle(...$arguments);
    }

    /**
     * Run the action if the given condition is true.
     */
    public static function runIf(bool $boolean, mixed ...$arguments): mixed
    {
        return $boolean ? static::run(...$arguments) : new Fluent;
    }

    /**
     * Run the action unless the given condition is true.
     */
    public static function runUnless(bool $boolean, mixed ...$arguments): mixed
    {
        return static::runIf(! $boolean, ...$arguments);
    }

    /**
     * Execute the action.
     *
     * @see static::handle()
     */
    public static function execute(mixed ...$arguments): mixed
    {
        return static::make()->handle(...$arguments);
    }

    /**
     * Execute the action.
     */
    public function handle(mixed ...$arguments): mixed
    {
        return $this(...$arguments);
    }
}
