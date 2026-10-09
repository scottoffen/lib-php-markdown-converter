<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\Tests\Support\Benchmark;
use ScottOffen\MarkdownConverter\Tests\Support\PerformanceReport;

/**
 * Tests the tools behind the performance test. The performance test itself
 * doesn't run with these tests, because its timings vary, but how it times,
 * compares, and reports must be right for its numbers to be useful.
 */
final class PerformanceReportTest extends TestCase
{
    /**
     * Returns timing statistics for a scenario with the given fastest run.
     *
     * @return array{runs: int, min: float, median: float, mean: float, max: float, spread: float, peak: int|null}
     */
    private function stats(float $min): array
    {
        return ['runs' => 5, 'min' => $min, 'median' => $min * 1.1, 'mean' => $min * 1.1, 'max' => $min * 2, 'spread' => 0.1, 'peak' => 1048576];
    }

    /**
     * Creates a report with one scenario for each given fastest run.
     *
     * @param array<string, float> $minimums The fastest time in seconds, by
     *     scenario name. All scenarios are in the group `"g"`.
     */
    private function report(array $minimums, float $threshold = 10.0): PerformanceReport
    {
        $report = new PerformanceReport($threshold);

        foreach ($minimums as $name => $min) {
            $report->add('g', $name, 2048, $this->stats($min));
        }

        return $report;
    }

    /**
     * Creates a baseline report with one scenario for each given fastest run.
     *
     * @param array<string, float> $minimums
     *
     * @return array{environment: array<string, string>, results: array<string, array<string, mixed>>}
     */
    private function baseline(array $minimums): array
    {
        return ['environment' => PerformanceReport::environment(), 'results' => $this->report($minimums)->toArray()['results']];
    }

    public function testTheWorkIsDoneOnceToWarmUpAndThenAtLeastTheMinimumNumberOfTimes(): void
    {
        $calls = 0;

        $stats = Benchmark::measure(static function () use (&$calls): int {
            return ++$calls;
        }, 5, 0.0);

        $this->assertSame(5, $stats['runs']);
        $this->assertSame(6, $calls, 'One call to warm up and five that were timed.');
        $this->assertSame(6, $stats['result'], 'The result is what the last run returned.');
    }

    public function testTheWorkStopsAtTheMaximumNumberOfRuns(): void
    {
        $stats = Benchmark::measure(static fn (): string => '', 1, 60.0, 7);

        $this->assertSame(7, $stats['runs']);
    }

    public function testTheTimesAreInOrderAndTheSpreadIsHowFarTheMedianIsAboveTheFastest(): void
    {
        $stats = Benchmark::measure(static function (): void {
            usleep(200);
        }, 5, 0.0);

        $this->assertGreaterThan(0.0, $stats['min']);
        $this->assertLessThanOrEqual($stats['median'], $stats['min'], 'The fastest run is no slower than the median.');
        $this->assertLessThanOrEqual($stats['max'], $stats['median'], 'The median is no slower than the slowest run.');
        $this->assertEqualsWithDelta(($stats['median'] - $stats['min']) / $stats['min'], $stats['spread'], 1e-9);
    }

    public function testTheReportShowsEveryScenarioWithItsSizeAndThroughput(): void
    {
        $text = $this->report(['first scenario' => 0.002, 'second scenario' => 0.5])->render();

        $this->assertStringContainsString('first scenario', $text);
        $this->assertStringContainsString('second scenario', $text);
        $this->assertStringContainsString('2 KB', $text);
        $this->assertStringContainsString('2.000 ms', $text, 'A time under a tenth of a second is in milliseconds.');
        $this->assertStringContainsString('500.0 ms', $text);
        $this->assertStringNotContainsString('change', $text, 'Without a baseline there is nothing to compare.');
    }

    public function testASizeOfZeroShowsNoThroughput(): void
    {
        $report = new PerformanceReport();
        $report->add('g', 'setting up', 0, $this->stats(0.01));

        $this->assertMatchesRegularExpression('/setting up\s+-\s+5\s/', $report->render());
    }

    public function testAChangeSmallerThanTheThresholdIsCalledTheSame(): void
    {
        $text = $this->report(['a' => 0.0105])->render($this->baseline(['a' => 0.01]));

        $this->assertMatchesRegularExpression('/\+5\.0%\s+same/', $text);
    }

    public function testAChangeBeyondTheThresholdIsFasterOrSlower(): void
    {
        $text = $this->report(['fast' => 0.005, 'slow' => 0.02])->render($this->baseline(['fast' => 0.01, 'slow' => 0.01]));

        $this->assertMatchesRegularExpression('/fast.*-50%\s+faster/', $text);
        $this->assertMatchesRegularExpression('/slow.*\+100%\s+slower/', $text);
    }

    public function testTheThresholdCanBeChanged(): void
    {
        $baseline = $this->baseline(['a' => 0.01]);

        $this->assertMatchesRegularExpression('/\+5\.0%\s+same/', $this->report(['a' => 0.0105], 10.0)->render($baseline));
        $this->assertMatchesRegularExpression('/\+5\.0%\s+slower/', $this->report(['a' => 0.0105], 2.0)->render($baseline));
    }

    public function testAScenarioThatIsNotInTheBaselineIsNew(): void
    {
        $text = $this->report(['old' => 0.01, 'added' => 0.01])->render($this->baseline(['old' => 0.01]));

        $this->assertMatchesRegularExpression('/added.*\bnew\b/', $text);
        $this->assertStringContainsString('(1 scenarios)', $text, 'A new scenario is left out of the overall change.');
    }

    public function testTheOverallChangeIsTheGeometricMeanSoOneScenarioCannotHideTheRest(): void
    {
        // Twice as fast and twice as slow cancel out to no change, where an
        // average of the changes would say +25%.
        $text = $this->report(['a' => 0.005, 'b' => 0.02])->render($this->baseline(['a' => 0.01, 'b' => 0.01]));

        $this->assertMatchesRegularExpression('/all\s+\+0\.0%\s+\(2 scenarios\)/', $text);
    }

    public function testNothingToCompareIsSaidSo(): void
    {
        $text = $this->report(['a' => 0.01])->render($this->baseline(['other' => 0.01]));

        $this->assertStringContainsString('Nothing in the baseline matches', $text);
    }

    public function testADifferenceInThePhpVersionIsPointedOut(): void
    {
        $baseline = $this->baseline(['a' => 0.01]);
        $baseline['environment']['php'] = '5.6.0';

        $this->assertStringContainsString('NOTE: the baseline was recorded with php 5.6.0', $this->report(['a' => 0.01])->render($baseline));
    }

    public function testTheMachineIsAShortCodeThatDoesNotGiveItsNameAway(): void
    {
        $machine = PerformanceReport::environment()['machine'];

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $machine);
        $this->assertStringNotContainsString(php_uname('n'), json_encode($this->report(['a' => 0.01])->toArray(), JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('machine ' . $machine, $this->report(['a' => 0.01])->render());
    }

    public function testARunOnAnotherMachineIsPointedOut(): void
    {
        $baseline = $this->baseline(['a' => 0.01]);
        $baseline['environment']['machine'] = 'deadbeef';

        $this->assertStringContainsString('NOTE: the baseline was recorded with machine deadbeef', $this->report(['a' => 0.01])->render($baseline));
    }

    public function testABaselineThatDoesNotSayWhichMachineIsAskedToBeRecordedAgain(): void
    {
        $baseline = $this->baseline(['a' => 0.01]);
        unset($baseline['environment']['machine']);

        $text = $this->report(['a' => 0.01])->render($baseline);

        $this->assertStringContainsString('does not say which machine', $text);
        $this->assertStringNotContainsString('was recorded with machine', $text);
    }

    public function testARunOnTheSameMachineHasNothingToSayAboutTheMachine(): void
    {
        $text = $this->report(['a' => 0.01])->render($this->baseline(['a' => 0.01]));

        $this->assertStringNotContainsString('does not say which machine', $text);
        $this->assertStringNotContainsString('was recorded with machine', $text);
    }

    /**
     * Makes a folder that looks like a repository, with a committed baseline, a
     * working baseline, both, or neither.
     *
     * @return array{0: string, 1: string} The path of a folder made to look
     *     like a repository, and the path of its committed baseline.
     */
    private function repository(bool $committed, bool $working): array
    {
        $root = sys_get_temp_dir() . '/markdown-performance-root-' . bin2hex(random_bytes(4));
        mkdir($root . '/tests/performance', 0777, true);
        mkdir($root . '/build/performance', 0777, true);

        if ($committed) {
            file_put_contents($root . '/tests/performance/baseline.json', '{}');
        }

        if ($working) {
            file_put_contents($root . '/build/performance/baseline.json', '{}');
        }

        return [$root, $root . '/tests/performance/baseline.json'];
    }

    private function remove(string $root): void
    {
        foreach (['/tests/performance/baseline.json', '/build/performance/baseline.json'] as $file) {
            @unlink($root . $file);
        }

        foreach (['/tests/performance', '/tests', '/build/performance', '/build', ''] as $directory) {
            @rmdir($root . $directory);
        }
    }

    public function testTheBaselineIsLookedForInTheWorkingCopyBeforeTheCommittedOne(): void
    {
        [$root] = $this->repository(true, true);

        try {
            $this->assertSame($root . '/build/performance/baseline.json', PerformanceReport::locateBaseline($root, false));
            $this->assertSame($root . '/build/performance/baseline.json', PerformanceReport::locateBaseline($root, ''), 'An empty setting is no setting.');
        } finally {
            $this->remove($root);
        }
    }

    public function testTheCommittedBaselineIsUsedWhenThereIsNoWorkingCopy(): void
    {
        [$root, $committed] = $this->repository(true, false);

        try {
            $this->assertSame($committed, PerformanceReport::locateBaseline($root, false));
        } finally {
            $this->remove($root);
        }
    }

    public function testThereIsNoBaselineWhenNeitherExists(): void
    {
        [$root] = $this->repository(false, false);

        try {
            $this->assertNull(PerformanceReport::locateBaseline($root, false));
        } finally {
            $this->remove($root);
        }
    }

    public function testTheSettingNamesTheBaselineOrTurnsTheComparisonOff(): void
    {
        [$root] = $this->repository(true, true);

        try {
            $this->assertSame('/elsewhere/before.json', PerformanceReport::locateBaseline($root, '/elsewhere/before.json'), 'A file that is named is used, whether or not it exists.');
            $this->assertNull(PerformanceReport::locateBaseline($root, 'none'), '"none" leaves the comparison out even when baselines exist.');
        } finally {
            $this->remove($root);
        }
    }

    public function testAReportCanBeSavedAndLoadedAgain(): void
    {
        $path = sys_get_temp_dir() . '/markdown-performance-' . bin2hex(random_bytes(4)) . '/nested/latest.json';

        try {
            $this->report(['a' => 0.01, 'b' => 0.02])->save($path);
            $loaded = PerformanceReport::load($path);

            $this->assertNotNull($loaded);
            $this->assertSame(['g/a', 'g/b'], array_keys($loaded['results']));
            $this->assertSame(0.02, $loaded['results']['g/b']['min']);
            $this->assertSame(PHP_VERSION, $loaded['environment']['php']);
        } finally {
            @unlink($path);
            @rmdir(dirname($path));
            @rmdir(dirname($path, 2));
        }
    }

    public function testAMissingOrBrokenFileIsNotABaseline(): void
    {
        $path = sys_get_temp_dir() . '/markdown-performance-broken-' . bin2hex(random_bytes(4)) . '.json';

        $this->assertNull(PerformanceReport::load($path));

        file_put_contents($path, '{ not json');

        try {
            $this->assertNull(PerformanceReport::load($path));
        } finally {
            @unlink($path);
        }
    }
}
