<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('lighthouse-subscriptions.php')
        ]);

        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'lighthouse-subscriptions');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('graphql-subscriptions', function ($app) {
            return new Server(
                config('lighthouse-subscriptions.port'),
                config('lighthouse-subscriptions.keep_alive')
            );
        });

        $this->commands([
            Support\Console\Commands\WebSocketServerCommand::class,
        ]);
    }
}
