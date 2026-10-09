<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Support;

/**
 * Collects the results of the performance test, writes them as text and as
 * JSON, and compares them with an earlier run, so that you can judge a change
 * as faster, slower, or about the same.
 */
final class PerformanceReport
{
    /**
     * @var array<string, array{group: string, name: string, bytes: int, runs: int, min: float, median: float, mean: float, max: float, spread: float, peak: int|null}>
     */
    private array $results = [];

    /**
     * Creates a report that calls a change faster or slower beyond the given
     * percentage.
     *
     * @param float $threshold The percentage a scenario must change to count as
     *     faster or slower. Runs on a busy machine vary by a few percent, so
     *     smaller changes are noise.
     */
    public function __construct(private readonly float $threshold = 10.0)
    {
    }

    /**
     * Adds the results of one scenario to the report.
     *
     * @param int $bytes The size of the text converted in one run, or 0 if the
     *     work isn't about a text.
     * @param array{runs: int, min: float, median: float, mean: float, max: float, spread: float, peak: int|null, result?: mixed} $stats
     *     What `Benchmark::measure()` returned.
     */
    public function add(string $group, string $name, int $bytes, array $stats): void
    {
        $this->results[$group . '/' . $name] = [
            'group' => $group,
            'name' => $name,
            'bytes' => $bytes,
            'runs' => $stats['runs'],
            'min' => $stats['min'],
            'median' => $stats['median'],
            'mean' => $stats['mean'],
            'max' => $stats['max'],
            'spread' => $stats['spread'],
            'peak' => $stats['peak'],
        ];
    }

    /**
     * Returns what can make two runs differ for reasons that have nothing to do
     * with the code. The machine is a short code made from its name, so a
     * committed baseline doesn't give the name away, and a run on another
     * machine can still be told from one on this machine.
     *
     * @return array<string, string> The environment, keyed by name.
     */
    public static function environment(): array
    {
        $opcache = function_exists('opcache_get_status') && filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN);

        return [
            'php' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'machine' => substr(sha1(php_uname('n') . '|' . php_uname('m')), 0, 8),
            'opcache' => $opcache ? 'on' : 'off',
            'jit' => $opcache && (string) ini_get('opcache.jit') !== '' && (string) ini_get('opcache.jit') !== '0' && (string) ini_get('opcache.jit') !== 'disable' ? 'on' : 'off',
            'xdebug' => extension_loaded('xdebug') ? 'on' : 'off',
            'recorded' => gmdate('Y-m-d H:i:s') . ' UTC',
        ];
    }

    /**
     * Finds the baseline to compare with: the file that the setting names, if
     * there is one; otherwise the working copy in `build/performance`, which
     * git ignores; otherwise the committed copy in `tests/performance`. The
     * setting `none` turns the comparison off.
     *
     * @param string $root The root folder of the repository.
     * @param string|false $setting What the `PERF_BASELINE` environment
     *     variable holds, or false if it isn't set.
     *
     * @return string|null A file that might not exist, if the setting names
     *     one, and null if there is none to use.
     */
    public static function locateBaseline(string $root, string|false $setting): ?string
    {
        if ($setting === 'none') {
            return null;
        }

        if ($setting !== false && $setting !== '') {
            return $setting;
        }

        foreach (['/build/performance/baseline.json', '/tests/performance/baseline.json'] as $candidate) {
            if (is_file($root . $candidate)) {
                return $root . $candidate;
            }
        }

        return null;
    }

    /**
     * Returns the report as an array, for saving as JSON.
     *
     * @return array{environment: array<string, string>, results: array<string, mixed>}
     *     The environment and the results.
     */
    public function toArray(): array
    {
        return ['environment' => self::environment(), 'results' => $this->results];
    }

    public function save(string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create ' . $directory);
        }

        file_put_contents($path, json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Loads a saved report.
     *
     * @param string $path The file.
     *
     * @return array{environment: array<string, string>, results: array<string, array<string, mixed>>}|null
     *     The report, or null if there is no usable file.
     */
    public static function load(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && isset($data['results']) && is_array($data['results']) ? ['environment' => (array) ($data['environment'] ?? []), 'results' => $data['results']] : null;
    }

    /**
     * Returns the report as text. With a baseline, each scenario also shows how
     * much it changed.
     *
     * @param array{environment: array<string, string>, results: array<string, array<string, mixed>>}|null $baseline
     *     The earlier report to compare with, or null for none.
     * @param string $baselineName The name to show for the baseline.
     *
     * @return string The text.
     */
    public function render(?array $baseline = null, string $baselineName = ''): string
    {
        $environment = self::environment();
        $out = "\nPerformance\n";
        $out .= sprintf("  PHP %s on %s, machine %s, opcache %s, jit %s, xdebug %s\n", $environment['php'], $environment['os'], $environment['machine'], $environment['opcache'], $environment['jit'], $environment['xdebug']);
        $out .= "  Every time is the fastest run of that scenario. The median and the spread, which is how far the median is above the\n  fastest run, show how steady the machine was. A large spread means the numbers are not to be trusted.\n";

        if ($environment['xdebug'] === 'on') {
            $out .= "\n  WARNING: Xdebug is loaded, which slows PHP down and makes the numbers meaningless. Run this without it.\n";
        }

        if ($baseline !== null) {
            $out .= sprintf("\n  Compared with %s (%s, PHP %s). A change under %s is called the same.\n", $baselineName !== '' ? $baselineName : 'the baseline', $baseline['environment']['recorded'] ?? 'unknown time', $baseline['environment']['php'] ?? 'unknown', self::percent($this->threshold, false));

            foreach (['php', 'os', 'machine', 'opcache', 'jit'] as $key) {
                if (($baseline['environment'][$key] ?? null) !== null && $baseline['environment'][$key] !== $environment[$key]) {
                    $out .= sprintf("  NOTE: the baseline was recorded with %s %s and this run has %s, so the comparison may not be fair.\n", $key, $baseline['environment'][$key], $environment[$key]);
                }
            }

            if (!isset($baseline['environment']['machine'])) {
                $out .= "  NOTE: the baseline does not say which machine it was recorded on. Record it again to find out when a run is on another one.\n";
            }
        }

        $groups = [];

        foreach ($this->results as $key => $result) {
            $groups[$result['group']][$key] = $result;
        }

        $ratios = [];

        foreach ($groups as $group => $rows) {
            $out .= "\n" . $this->renderGroup($group, $rows, $baseline, $ratios);
        }

        if ($baseline !== null) {
            $out .= $this->renderSummary($ratios);
        }

        return $out;
    }

    /**
     * Returns the table for one group of scenarios.
     *
     * @param array<string, array<string, mixed>> $rows
     * @param array{environment: array<string, string>, results: array<string, array<string, mixed>>}|null $baseline
     * @param array<string, list<float>> $ratios Filled with how each scenario
     *     changed, by group.
     */
    private function renderGroup(string $group, array $rows, ?array $baseline, array &$ratios): string
    {
        $width = max(array_map(static fn (array $row): int => strlen((string) $row['name']), $rows));
        $width = max($width, 8);

        $header = sprintf('  %-' . $width . 's  %8s  %5s  %10s  %10s  %7s  %9s  %8s', $group, 'size', 'runs', 'fastest', 'median', 'spread', 'MB/s', 'memory');

        if ($baseline !== null) {
            $header .= sprintf('  %10s  %9s', 'before', 'change');
        }

        $out = $header . "\n";

        foreach ($rows as $key => $row) {
            $line = sprintf(
                '  %-' . $width . 's  %8s  %5d  %10s  %10s  %7s  %9s  %8s',
                $row['name'],
                $row['bytes'] > 0 ? number_format($row['bytes'] / 1024, 0) . ' KB' : '-',
                $row['runs'],
                self::time((float) $row['min']),
                self::time((float) $row['median']),
                self::percent((float) $row['spread'] * 100, false),
                $row['bytes'] > 0 && $row['min'] > 0 ? number_format($row['bytes'] / 1e6 / $row['min'], 1) : '-',
                $row['peak'] !== null ? number_format($row['peak'] / 1048576, 1) . ' MB' : '-',
            );

            if ($baseline !== null) {
                $line .= $this->compare($key, (float) $row['min'], $baseline, $group, $ratios);
            }

            $out .= $line . "\n";
        }

        return $out;
    }

    /**
     * Returns the columns that compare one scenario with the baseline.
     *
     * @param array{environment: array<string, string>, results: array<string, array<string, mixed>>} $baseline
     * @param array<string, list<float>> $ratios
     */
    private function compare(string $key, float $min, array $baseline, string $group, array &$ratios): string
    {
        $before = $baseline['results'][$key]['min'] ?? null;

        if (!is_numeric($before) || (float) $before <= 0) {
            return sprintf('  %10s  %9s', 'new', '');
        }

        $ratio = $min / (float) $before;
        $ratios[$group][] = $ratio;
        $change = ($ratio - 1) * 100;
        $verdict = abs($change) < $this->threshold ? 'same' : ($change < 0 ? 'faster' : 'slower');

        return sprintf('  %10s  %9s  %s', self::time((float) $before), self::percent($change, true), $verdict);
    }

    /**
     * Returns the overall change by group.
     *
     * @param array<string, list<float>> $ratios
     */
    private function renderSummary(array $ratios): string
    {
        if ($ratios === []) {
            return "\n  Nothing in the baseline matches this run, so there is nothing to compare.\n";
        }

        $out = "\n  Overall change against the baseline. This is the geometric mean of every scenario in the group, so one\n  scenario cannot hide the rest, and a negative number means faster.\n";
        $all = [];

        foreach ($ratios as $group => $values) {
            $all = array_merge($all, $values);
            $out .= sprintf("    %-10s %9s  (%d scenarios)\n", $group, self::percent((self::geometricMean($values) - 1) * 100, true), count($values));
        }

        return $out . sprintf("    %-10s %9s  (%d scenarios)\n", 'all', self::percent((self::geometricMean($all) - 1) * 100, true), count($all));
    }

    /**
     * Returns the geometric mean of the ratios.
     *
     * @param list<float> $values
     */
    private static function geometricMean(array $values): float
    {
        return exp(array_sum(array_map('log', $values)) / count($values));
    }

    private static function time(float $seconds): string
    {
        return $seconds >= 1 ? number_format($seconds, 3) . ' s' : number_format($seconds * 1000, $seconds >= 0.01 ? 1 : 3) . ' ms';
    }

    private static function percent(float $value, bool $signed): string
    {
        $text = number_format(abs($value), abs($value) >= 10 ? 0 : 1);

        return ($signed ? ($value < 0 ? '-' : '+') : '') . $text . '%';
    }
}
