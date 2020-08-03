<?php

use TestTrap\GlobalManager;
use TestTrap\Tests\TestCase;
use TestTrap\TestTrapExtension;

class TestTrapExtensionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        GlobalManager::flush();
    }

    /** @test */
    public function it_does_not_set_global_variables_if_there_is_no_threshold_arguments()
    {
        $extension = new TestTrapExtension();
        $extension->executeBeforeTest('test');

        $this->assertNull(GlobalManager::get('migrations_ended'));
        $this->assertNull(GlobalManager::get('tt_queries'));
    }

    /** @test */
    public function it_does_set_initial_values_in_global_state()
    {
        $extension = new TestTrapExtension(['speed' => 1]);
        $extension->executeBeforeTest('test');

        $this->assertFalse(GlobalManager::get('migrations_ended'));
        $this->assertEmpty(GlobalManager::get('tt_queries'));
    }

    /** @test */
    public function it_does_not_set_results_if_there_is_no_threshold_argument_available()
    {
        $extension = new TestTrapExtension();
        $extension->executeAfterTest('test', 1);

        $this->assertEmpty($extension->results());
    }

    /** @test */
    public function it_saves_tests_that_match_query_speed_threshold()
    {
        GlobalManager::set('tt_queries', [
            ['query' => 'sql', 'time' => 1],
            ['query' => 'sql', 'time' => 2],
        ]);

        $extension = new TestTrapExtension(['querySpeed' => 1]);
        $extension->executeAfterTest('test', 1);

        $result = $extension->results();

        $this->assertEquals(1, data_get($result, 'test.queries.sql')->count());
        $this->assertEquals('sql', data_get($result, 'test.queries.sql.0.query'));
        $this->assertEquals(2, data_get($result, 'test.queries.sql.0.time'));
    }

    /** @test */
    public function it_saves_tests_that_match_query_count_threshold()
    {
        GlobalManager::set('tt_queries', [
            ['query' => 'sql', 'time' => 1],
            ['query' => 'sql', 'time' => 1],
        ]);

        $extension = new TestTrapExtension(['queryCalled' => 1]);
        $extension->executeAfterTest('test', 1);

        $result = $extension->results();

        $this->assertEquals(2, data_get($result, 'test.queries.sql')->count());
        $this->assertEquals('sql', data_get($result, 'test.queries.sql.0.query'));
        $this->assertEquals(1, data_get($result, 'test.queries.sql.0.time'));
        $this->assertEquals('sql', data_get($result, 'test.queries.sql.1.query'));
        $this->assertEquals(1, data_get($result, 'test.queries.sql.1.time'));
    }

    /** @test */
    public function it_saves_tests_that_match_speed_threshold()
    {
        $extension = new TestTrapExtension(['speed' => 1]);
        $extension->executeAfterTest('test', 2);

        $this->assertCount(1, $extension->results());
        $this->assertEquals(2, data_get($extension->results(), 'test.time'));

        $extension->executeAfterTest('test', 1);
        $this->assertCount(1, $extension->results());
        $this->assertEquals(2, data_get($extension->results(), 'test.time'));
    }
}
