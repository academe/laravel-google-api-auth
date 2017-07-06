<?php

namespace Academe\GoogleApi;

use Illuminate\Support\ServiceProvider;

class GoogleApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // TODO: put the config in a vendor directory to avoid clashes.
        $this->publishes([
            __DIR__ . '/../config/googleapi.php' => config_path('googleapi.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../migrations/' => base_path('/database/migrations'),
        ], 'migrations');

        // Or use this and not have to publish the migrations at all?
        $this->loadMigrationsFrom(__DIR__ . '/../migrations/');

        // Load the routes.
        $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');

        // Supply some package views.
        $this->loadViewsFrom(__DIR__ . '/../views', 'academe/googleapi');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge the config with any override settings.
        $this->mergeConfigFrom(__DIR__ . '/../config/googleapi.php', 'googleapi');
    }
}
