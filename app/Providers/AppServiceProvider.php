<?php

namespace App\Providers;

use App\Models\HttpClientConfig;
use App\Models\XtreamCodeConfig;
use App\Services\XtreamCodesClient;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(ConfigServiceProvider::class);
        $this->app->bind(XtreamCodesClient::class, function (Application $app) {
            return new XtreamCodesClient(
                $app->make(XtreamCodeConfig::class),
                $app->make(HttpClientConfig::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
