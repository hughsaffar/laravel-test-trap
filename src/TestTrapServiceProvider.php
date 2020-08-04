<?php


namespace TestTrap;

use Illuminate\Support\ServiceProvider;

final class TestTrapServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(QueryWatcher::class);
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/test-trap.php' => config_path('test-trap.php'),
        ], 'config');

        if (! config('test-trap.enable', true)) {
            return;
        }

        if (! $this->app->environment(config('test-trap.environment_name', 'testing'))) {
            return;
        }

        resolve(QueryWatcher::class)->listen();
    }
}
