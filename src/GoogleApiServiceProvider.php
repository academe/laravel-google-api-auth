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

        $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');
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
