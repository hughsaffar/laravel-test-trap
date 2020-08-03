<?php


namespace TestTrap\Tests;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\SQLiteConnection;
use Mockery\Mock;
use TestTrap\GlobalManager;

class QueryWatcherTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        GlobalManager::flush();
    }

    /** @test */
    public function it_toggle_migration_state_when_the_migration_is_done()
    {
        $this->assertNull(GlobalManager::get('migrations_ended'));

        event(new MigrationsEnded());

        $this->assertTrue(GlobalManager::get('migrations_ended'));
    }

    /** @test */
    public function it_does_not_record_migration_related_queries()
    {
        $databaseConnection = \Mockery::mock(SQLiteConnection::class)
            ->shouldReceive('getName')
            ->andReturn('ConnectionName')
            ->getMock();

        event(new QueryExecuted('sql', [], 1, $databaseConnection));

        $this->assertEmpty(GlobalManager::get('tt_queries'));
    }

    /** @test */
    public function it_logs_query_in_global_state()
    {
        GlobalManager::set('migrations_ended', true);

        $databaseConnection = \Mockery::mock(SQLiteConnection::class)
            ->shouldReceive('getName')
            ->andReturn('ConnectionName')
            ->getMock();

        event(new QueryExecuted('sql', [], 1, $databaseConnection));

        $this->assertEquals(['query' => 'sql', 'time' => 1], data_get(GlobalManager::get('tt_queries'), '0'));
    }
}
