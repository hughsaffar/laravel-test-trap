<?php


namespace TestTrap;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\CLImate\CLImate;
use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;
use PHPUnit\Runner\BeforeTestHook;

final class TestTrapExtension implements BeforeFirstTestHook, AfterLastTestHook, BeforeTestHook, AfterTestHook
{
    private $results = [];
    private $thresholds;
    private $disabled;

    public function __construct($thresholds = [])
    {
        $this->thresholds = $thresholds;
        $this->disabled = env('TEST_TRAP_DISABLE', false);
    }

    public function results(): array
    {
        return $this->results;
    }

    public function executeBeforeFirstTest(): void
    {
        $this->disabled = env('TEST_TRAP_DISABLE', false);
    }

    public function executeBeforeTest(string $test): void
    {
        if ($this->disabled) {
            return;
        }

        if (! $this->hasArguments()) {
            return;
        }

        GlobalManager::set('migrations_ended', false);
        GlobalManager::set('tt_queries', []);
    }


    public function executeAfterTest(string $test, float $time): void
    {
        if ($this->disabled) {
            return;
        }

        if (! $this->hasArguments()) {
            return;
        }

        $queries = collect(GlobalManager::get('tt_queries'));

        if ($this->hasThreshold('querySpeed')) {
            data_set(
                $this->results,
                "{$test}.slowQueries",
                $this->slowQueries($queries)->groupBy('query')
            );
        }

        if ($this->hasThreshold('queryCalled')) {
            data_set(
                $this->results,
                "{$test}.repetitiveQueries",
                $this->repetitiveQueries($queries)->groupBy('query')
            );
        }

        if ($this->hasThreshold('speed') && $this->isSlowTest($time)) {
            data_set($this->results, "{$test}.time", $time);
        }
    }

    public function executeAfterLastTest(): void
    {
        if ($this->disabled) {
            return;
        }

        if (! $this->hasArguments()) {
            return;
        }

        $tests = $this->formatTests();

        $slowTests = $this->hasThreshold('speed') ? $this->slowTests($tests) : collect();
        $slowQueryTests = $this->hasThreshold('querySpeed') ? $this->slowQueryTests() : collect();
        $repetitiveQueryTests = $this->hasThreshold('queryCalled') ? $this->repetitiveQueryTests() : collect();
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

    private function slowQueryTests(): Collection
    {
        return collect($this->results)->filter(function ($test) {
            return $test['slowQueries']->isNotEmpty();
        })->map(function ($test) {
            return data_get($test, 'slowQueries')->map(function ($queries) {
                return $queries->filter(function ($query) {
                    return data_get($query, 'time') > $this->thresholds['querySpeed'];
                })->average('time');
            });
        });
    }


    private function repetitiveQueryTests(): Collection
    {
        return collect($this->results)->filter(function ($test) {
            return $test['repetitiveQueries']->isNotEmpty();
        })->map(function ($test) {
            return data_get($test, 'repetitiveQueries')->filter(function ($queries) {
                return $queries->count() > $this->thresholds['queryCalled'];
            });
        })->map(function ($queries) {
            return $queries->map(function ($query) {
                return $query->count();
            });
        });
    }

    private function renderSlowTests(Collection $slowTests, CLImate $climate): void
    {
        $climate->border();
        $climate->bold()->out(sprintf('Slow Tests (> %f ms)', $this->thresholds['speed']));
        $climate->border();

        foreach ($slowTests->groupBy('class') as $testClass => $slowTest) {
            $climate->tab()->out($testClass);
            foreach ($slowTest as $test) {
                $climate->tab(2)->out(sprintf('%f ms - %s', $test['time'], $test['name']));
            }
        }

        $climate->border();
        $climate->out(sprintf('Total: %f ms', $slowTests->sum('time')));
        $climate->border();
    }

    private function renderSlowQueryTests(Collection $slowQueryTests, CLImate $climate)
    {
        $climate->border();
        $climate->bold(sprintf('Slow Queries (> %f ms)', $this->thresholds['querySpeed']));
        $climate->border();

        foreach ($slowQueryTests as $testName => $queries) {
            [$testClass, $testMethod] = explode('::', $testName);
            $climate->tab(1)->out($testClass)->bold();
            $climate->tab(2)->out($testMethod)->bold();
            foreach ($queries as $query => $time) {
                $query = strlen($query) > 100 ? Str::substr($query, 0, 100) . '...' : $query;
                $climate->tab(3)->out(sprintf('%f ms (average) - %s', $time, $query));
            }
        }

        $climate->border();
        $climate->out(sprintf('Total: %f ms', $slowQueryTests->flatten(1)->sum()));
        $climate->border();
    }

    private function renderRepetitiveQueryTests(Collection $repetitiveQueryTests, CLImate $climate)
    {
        $climate->border();
        $climate->bold(sprintf('Repetitive Queries (> %dx)', $this->thresholds['queryCalled']));
        $climate->border();

        foreach ($repetitiveQueryTests as $testName => $queries) {
            [$testClass, $testMethod] = explode('::', $testName);
            $climate->tab(1)->out($testClass)->bold();
            $climate->tab(2)->out($testMethod)->bold();

            foreach ($queries as $query => $count) {
                $query = strlen($query) > 100 ? Str::substr($query, 0, 100) . '...' : $query;
                $climate->tab(3)->out(sprintf('%sx - %s', number_format($count), $query));
            }
        }

        $climate->border();
        $climate->out(sprintf('Total: %dx', $repetitiveQueryTests->flatten(1)->sum()));
        $climate->border();
    }

    private function isSlowTest(float $time): bool
    {
        return $time > $this->thresholds['speed'];
    }

    private function slowQueries(Collection $queries): Collection
    {
        return $queries->where('time', '>', $this->thresholds['querySpeed']);
    }

    private function repetitiveQueries(Collection $queries): Collection
    {
        return $queries->groupBy('query')->filter(function (Collection $queries) {
            return $queries->count() > $this->thresholds['queryCalled'];
        })->flatten(1);
    }
}
