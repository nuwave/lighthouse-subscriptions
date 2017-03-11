<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\ServiceProvider;
use Illuminate\Broadcasting\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Support\Broadcasters\RedisBroadcaster;

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

        $this->regsiterBroadcaster();
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
            Support\Console\Commands\SubscriptionMakeCommand::class,
            Support\Console\Commands\WebSocketServerCommand::class,
        ]);
    }

    protected function regsiterBroadcaster()
    {
        $this->app->make(BroadcastManager::class)->extend('lighthouse', function ($app, $config) {
            $redis = $app->make('redis');
            $connection = array_get($config, 'connection');

            return new RedisBroadcaster($redis, $connection);
        });
    }
}
