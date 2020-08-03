<?php

namespace TestTrap\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TestTrap\TestTrapServiceProvider;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            TestTrapServiceProvider::class,
        ];
    }
}
