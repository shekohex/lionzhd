<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Aria2Config;
use App\Models\XtreamCodesConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(XtreamCodesConfig::class, static fn () => XtreamCodesConfig::firstOrFromEnv());
        $this->app->bind(Aria2Config::class, static fn () => Aria2Config::firstOrFromEnv());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // SQLite specific settings and optimizations
        if (DB::getDriverName() === 'sqlite') {
            try {
                DB::unprepared(<<<'SQL'
                -- PRAGMA busy_timeout = 5000;
                -- PRAGMA cache_size = -20000;
                -- PRAGMA foreign_keys = ON;
                -- PRAGMA incremental_vacuum;
                -- PRAGMA mmap_size = 2147483648;
                -- PRAGMA temp_store = MEMORY;
                -- PRAGMA synchronous = NORMAL;
                SQL,
                );
            } catch (QueryException $e) {
                throw_unless(str_contains($e->getMessage(), 'does not exist.'), $e);
            }
        }

        // Indicate that models should prevent lazy loading, silently discarding attributes, and accessing missing attributes.
        Model::shouldBeStrict(! app()->isProduction());

        // By default, Laravel uses Carbon for dates. If we modify the date
        // it will modify the original date. To avoid this, we can use
        // CarbonImmutable instead of Carbon.
        Date::use(CarbonImmutable::class);

        // Better to disable destructive commands in production
        DB::prohibitDestructiveCommands(app()->isProduction());

    }
}
