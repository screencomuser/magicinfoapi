<?php

namespace Screencom\MagicinfoApi;

use Illuminate\Support\ServiceProvider;

class MagicinfoApiServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'screencom');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'screencom');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/magicinfoapi.php', 'magicinfoapi');

        // Register the service the package provides.
        $this->app->singleton('magicinfoapi', function ($app) {
            return new MagicinfoApi;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['magicinfoapi'];
    }
    
    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/magicinfoapi.php' => config_path('magicinfoapi.php'),
        ], 'magicinfoapi.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/screencom'),
        ], 'magicinfoapi.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/screencom'),
        ], 'magicinfoapi.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/screencom'),
        ], 'magicinfoapi.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
