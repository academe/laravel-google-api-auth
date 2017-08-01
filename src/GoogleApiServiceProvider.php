<?php

namespace Academe\GoogleApi;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class GoogleApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->defineAssetPublishing();
        $this->registerRoutes();
        $this->registerResources();
    }

    /**
     * Define the asset publishing configuration.
     *
     * @return void
     */
    public function defineAssetPublishing()
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
    }

    /**
     * Register the GAPI routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        Route::group([
            'prefix' => 'gapi',
            'middleware' => 'web',
            'namespace' => 'Academe\GoogleApi\Controllers'
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');
        });
    }

    /**
     * Register the GAPI resources.
     *
     * @return void
     */
    protected function registerResources()
    {
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
