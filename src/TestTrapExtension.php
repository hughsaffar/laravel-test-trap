<?php


namespace TestTrap;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\CLImate\CLImate;
use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;

final class TestTrapExtension implements AfterLastTestHook, BeforeTestHook, AfterTestHook
{
    private $results = [];
    private $thresholds;

    public function __construct($thresholds = [])
    {
        $this->thresholds = $thresholds;
    }

    public function results(): array
    {
        return $this->results;
    }

    public function executeBeforeTest(string $test): void
    {
        if (! $this->hasArguments()) {
            return;
        }

        GlobalManager::set('migrations_ended', false);
        GlobalManager::set('tt_queries', []);
    }


    public function executeAfterTest(string $test, float $time): void
    {
        if (! $this->hasArguments()) {
            return;
        }

        if ($this->hasQueryThreshold()) {
            data_set(
                $this->results,
                "{$test}.queries",
                $this->testsMatchingQueryThresholds()->groupBy('query')
            );
        }

        if ($this->hasThreshold('speed') && $this->isSlowTest($time)) {
            data_set($this->results, "{$test}.time", $time);
        }
    }

    public function executeAfterLastTest(): void
    {
        if (! $this->hasArguments()) {
            return;
        }

        $tests = $this->formatTests();

        $slowTests = $this->hasThreshold('speed') ? $this->slowTests($tests) : collect();

        $queryTests = $this->hasQueryThreshold() ? $this->queryTests($tests) : collect();

        $slowQueryTests = $this->hasThreshold('querySpeed') ? $this->slowQueryTests($queryTests) : collect();
        $repetitiveQueryTests = $this->hasThreshold('queryCalled') ? $this->repetitiveQueryTests($queryTests) : collect();

        $climate = new CLImate();
        $climate->br();

        if ($slowTests->isNotEmpty()) {
            $this->renderSlowTests($slowTests, $climate);
        }

        if ($slowQueryTests->isNotEmpty()) {
            $this->renderSlowQueryTests($slowQueryTests, $climate);
        }

        if ($repetitiveQueryTests->isNotEmpty()) {
            $this->renderRepetitiveQueryTests($repetitiveQueryTests, $climate);
        }
    }

    private function hasArguments(): bool
    {
        return ! empty($this->thresholds);
    }

    private function hasThreshold(string $key): bool
    {
        return isset($this->thresholds[$key]);
    }

    private function hasQueryThreshold(): bool
    {
        return $this->hasThreshold('queryCalled') || $this->hasThreshold('querySpeed');
    }

    private function formatTests(): Collection
    {
        return collect($this->results)->map(function ($item, $key) {
            [$testClass, $testName] = explode('::', $key);

            $testName = strlen($testName) > 140 ? Str::substr($testName, 0, 100) . '...' : $testName;

            return [
                'class' => $testClass,
                'name' => $testName,
                'time' => data_get($item, 'time'),
                'queries' => data_get($item, 'queries', collect())->map(function ($queries) {
                    return [
                        'called' => $queries->count(),
                        'time' => $queries->average('time'),
                    ];
                }),
            ];
        })->values();
    }


    private function slowTests(Collection $tests): Collection
    {
        return $tests->where('time', '>', $this->thresholds['speed']);
    }

    private function queryTests(Collection $tests): Collection
    {
        return $tests->filter(function ($test) {
            return $test['queries']->isNotEmpty();
        });
    }

    private function slowQueryTests(Collection $queryTests): Collection
    {
        return $queryTests->map(function ($test) {
            $slowQueries = data_get($test, 'queries')->where('time', '>', $this->thresholds['querySpeed']);

            if ($slowQueries->isEmpty()) {
                return;
            }

            data_set($test, 'queries', $slowQueries);

            return $test;
        })->filter();
    }


    private function repetitiveQueryTests(Collection $queryTests): Collection
    {
        return $queryTests->map(function ($test) {
            $repetitiveQueries = data_get($test, 'queries')->where('called', '>', $this->thresholds['queryCalled']);

            if ($repetitiveQueries->isEmpty()) {
                return;
            }

            data_set($test, 'queries', $repetitiveQueries);

            return $test;
        })->filter();
    }

    private function renderSlowTests(Collection $slowTests, CLImate $climate): void
    {
        $climate->border();
        $climate->bold()->out(sprintf('Slow Tests (>%dms)', $this->thresholds['speed']));
        $climate->border();

        foreach ($slowTests->groupBy('class') as $testClass => $slowTest) {
            $climate->tab()->out($testClass);
            foreach ($slowTest as $test) {
                $climate->tab(2)->out(sprintf('%fms - %s', $test['time'], $test['name']));
            }
        }

        $climate->border();
        $climate->out(sprintf('Total: %fms', $slowTests->sum('time')));
        $climate->border();
    }

    private function renderSlowQueryTests(Collection $slowQueryTests, CLImate $climate)
    {
        $climate->border();
        $climate->bold(sprintf('Slow Queries (>%dms)', $this->thresholds['querySpeed']));
        $climate->border();

        foreach ($slowQueryTests->groupBy('class') as $key => $tests) {
            $climate->tab(1)->out($key)->bold();
            foreach ($tests as $test) {
                $climate->tab(2)->out($test['name']);
                foreach ($test['queries'] as $query => $data) {
                    $query = strlen($query) > 100 ? Str::substr($query, 0, 100) . '...' : $query;
                    $climate->tab(3)->out(sprintf('%fms (average) - %s', $data['time'], $query));
                }
            }
        }

        $climate->border();
        $climate->out(sprintf('Total: %fms', $slowQueryTests->pluck('queries')->flatten(1)->sum('time')));
        $climate->border();
    }

    private function renderRepetitiveQueryTests(Collection $repetitiveQueryTests, CLImate $climate)
    {
        $climate->border();
        $climate->bold(sprintf('Repetitive Queries (>%dx)', $this->thresholds['queryCalled']));
        $climate->border();

        foreach ($repetitiveQueryTests->groupBy('class') as $key => $tests) {
            $climate->tab(1)->out($key)->bold();
            foreach ($tests as $test) {
                $climate->tab(2)->out($test['name']);
                foreach ($test['queries'] as $query => $data) {
                    $query = strlen($query) > 100 ? Str::substr($query, 0, 100) . '...' : $query;
                    $climate->tab(3)->out(sprintf('%d - %s', $data['called'], $query));
                }
            }
        }

        $climate->border();
        $climate->out(sprintf('Total: %dx', $repetitiveQueryTests->pluck('queries')->flatten(1)->sum('called')));
        $climate->border();
    }

    private function testsMatchingQueryThresholds(): Collection
    {
        $queryTests = collect(GlobalManager::get('tt_queries'));
        $slowTests = collect();
        $repetitiveTests = collect();

        if ($this->hasThreshold('querySpeed')) {
            $slowTests = $queryTests->where('time', '>', $this->thresholds['querySpeed']);
        }

        if ($this->hasThreshold('queryCalled')) {
            $repetitiveTests = $queryTests->groupBy('query')->filter(function (Collection $queries) {
                return $queries->count() > $this->thresholds['queryCalled'];
            })->flatten(1);
        }

        return $slowTests->merge($repetitiveTests);
    }

    private function isSlowTest(float $time): bool
    {
        return $time > $this->thresholds['speed'];
    }
}
