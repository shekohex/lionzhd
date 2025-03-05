<?php

namespace App\Providers;

use App\Models\Aria2Config;
use App\Models\HttpClientConfig;
use App\Models\MeilisearchConfig;
use App\Models\XtreamCodeConfig;
use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(XtreamCodeConfig::class, function () {
            return XtreamCodeConfig::query()->firstOrCreate();
        });

        $this->app->singleton(MeilisearchConfig::class, function () {
            return MeilisearchConfig::query()->firstOrCreate();
        });

        $this->app->singleton(Aria2Config::class, function () {
            return Aria2Config::query()->firstOrCreate();
        });

        $this->app->singleton(HttpClientConfig::class, function () {
            return HttpClientConfig::query()->firstOrCreate();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
