<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Support;

/**
 * Times a piece of work for the performance tests, which don't run with the
 * unit tests.
 */
final class Benchmark
{
    /**
     * Runs the work once to warm up, then repeats it until it has run at least
     * `$minRuns` times and the runs add up to `$budget` seconds, or until it
     * has run `$maxRuns` times.
     *
     * The fastest run is the number to compare, because other activity on the
     * machine can only make a run longer, never shorter. The median and the
     * spread, which is how far the median is above the fastest run, show how
     * much to trust it.
     *
     * Times are in seconds. The peak is the most memory used above what was in
     * use when the runs began, in bytes. It is null before PHP 8.2, which can't
     * reset its peak. The result is what the last run returned.
     *
     * @param callable(): mixed $work The work to time.
     * @param int $minRuns The fewest timed runs.
     * @param float $budget The seconds to spend on timed runs, once there have
     *     been `$minRuns` of them.
     * @param int $maxRuns The most timed runs.
     *
     * @return array{runs: int, min: float, median: float, mean: float, max: float, spread: float, peak: int|null, result: mixed}
     *     The statistics.
     */
    public static function measure(callable $work, int $minRuns = 5, float $budget = 0.4, int $maxRuns = 200): array
    {
        $result = $work();

        gc_collect_cycles();

        $collecting = gc_enabled();
        gc_disable();

        $canMeasureMemory = function_exists('memory_reset_peak_usage');
        $before = memory_get_usage();

        if ($canMeasureMemory) {
            memory_reset_peak_usage();
        }

        $times = [];
        $total = 0.0;

        do {
            $start = hrtime(true);
            $result = $work();
            $seconds = (hrtime(true) - $start) / 1e9;

            $times[] = $seconds;
            $total += $seconds;
        } while (count($times) < $maxRuns && (count($times) < $minRuns || $total < $budget));

        $peak = $canMeasureMemory ? max(0, memory_get_peak_usage() - $before) : null;

        if ($collecting) {
            gc_enable();
        }

        sort($times);

        $count = count($times);
        $min = $times[0];
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1 ? $times[$middle] : ($times[$middle - 1] + $times[$middle]) / 2;

        return [
            'runs' => $count,
            'min' => $min,
            'median' => $median,
            'mean' => $total / $count,
            'max' => $times[$count - 1],
            'spread' => $min > 0 ? ($median - $min) / $min : 0.0,
            'peak' => $peak,
            'result' => $result,
        ];
    }
}
