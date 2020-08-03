<?php


namespace TestTrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;

final class QueryWatcher
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function listen()
    {
        $this->app['events']->listen(MigrationsEnded::class, function () {
            GlobalManager::set('migrations_ended', true);
        });

        $this->app['events']->listen(QueryExecuted::class, function (QueryExecuted $event) {
            if (! GlobalManager::get('migrations_ended')) {
                return;
            }

            GlobalManager::push('tt_queries', [
                'query' => $event->sql,
                'time' => $event->time,
            ]);
        });
    }
}
