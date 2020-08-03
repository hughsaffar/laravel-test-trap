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
        if (! $this->app->environment(config('test-trap.environment_name', 'testing'))) {
            return;
        }

        resolve(QueryWatcher::class)->listen();
    }
}
