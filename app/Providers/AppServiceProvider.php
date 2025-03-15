<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Aria2Config;
use App\Models\XtreamCodesConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
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
        // Indicate that models should prevent lazy loading, silently discarding attributes, and accessing missing attributes.
        Model::shouldBeStrict(! app()->isProduction());

        // By default, Laravel uses Carbon for dates. and if we modify the date
        // it will modify the original date. To avoid this, we can use
        // CarbonImmutable instead of Carbon.
        Date::use(CarbonImmutable::class);

        // Better to disable destructive commands in production
        DB::prohibitDestructiveCommands(app()->isProduction());

    }
}
