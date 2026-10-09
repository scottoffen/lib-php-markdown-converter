<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Performance;

use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\MarkdownConverter;
use ScottOffen\MarkdownConverter\Tests\Support\Benchmark;
use ScottOffen\MarkdownConverter\Tests\Support\HtmlChecker;
use ScottOffen\MarkdownConverter\Tests\Support\PerformanceReport;

/**
 * Measures how long the converter takes and prints the results. Compare a run
 * after a change with a run before it to see whether the change made the
 * converter faster or slower.
 *
 * This test isn't part of `composer test`, because timings vary from one
 * machine and one moment to the next. Nothing here fails because something was
 * slow.
 *
 *     composer perf
 *
 * There are three groups of scenarios:
 *
 * - `realistic` is the work the converter was made for: a long page of release
 *   notes and many small texts.
 * - `features` has one scenario for each kind of syntax, so a change that slows
 *   one of them shows up on its own line.
 * - `hostile` is input that once made a converter do far more work than its
 *   size suggests. It must stay fast however large it is.
 *
 * To judge a change:
 *
 * 1. On the code as it was, run `composer perf`. Copy
 *    `build/performance/latest.json` to `tests/performance/baseline.json`,
 *    which git keeps, or to `build/performance/baseline.json`, which git
 *    ignores.
 * 2. Make the change and run `composer perf` again. With a baseline in place,
 *    each line shows how much it changed and whether that is more than noise.
 *    The end shows the overall change by group.
 *
 * A baseline belongs to the machine it was recorded on, and the report says so
 * when a run is on another machine. Record the baseline again at a release, or
 * when the PHP version or the machine changes. An out-of-date baseline gives
 * misleading comparisons. If `build/performance/baseline.json` exists, the test
 * uses it in place of `tests/performance/baseline.json`, so a working baseline
 * never touches what is committed.
 *
 * Set these environment variables to change the behavior:
 *
 * - `PERF_BASELINE`: The file to compare with. Default:
 *   `build/performance/baseline.json` if it exists, otherwise
 *   `tests/performance/baseline.json`. Set it to `none` to skip the comparison.
 * - `PERF_OUTPUT`: Where to write this run as JSON. Default:
 *   `build/performance/latest.json`.
 * - `PERF_BUDGET`: The seconds to spend on each scenario. Default: 0.4. Use
 *   0.05 for a quick look, and more for steadier numbers.
 * - `PERF_THRESHOLD`: The percentage a scenario must change to count as faster
 *   or slower. Default: 10.
 * - `PERF_IN_CI`: Set it to run this test where `CI` is set. Otherwise the test
 *   is skipped there.
 *
 * For numbers that mean something, run on a machine that isn't busy, without
 * Xdebug, and compare runs made with the same PHP version.
 */
final class PerformanceTest extends TestCase
{
    /**
     * The settings of a site that shows GitHub release notes in a WordPress
     * administration screen.
     */
    private const WORDPRESS = [
        'imageHosts' => ['github.com', '*.githubusercontent.com'],
        'html' => 'sanitize',
        'inlineStyles' => true,
        'headingOffset' => 4,
    ];

    /**
     * Every setting turned on, for the most work the converter can be asked to
     * do.
     */
    private const EVERYTHING = [
        'html' => 'sanitize',
        'imageHosts' => ['*'],
        'math' => true,
        'mermaid' => true,
        'decodeEntities' => true,
        'baseUrl' => 'https://example.com/docs',
        'linkSchemes' => ['http', 'https', 'mailto', 'ftp'],
        'autolinkWww' => true,
        'autolinkEmails' => true,
        'indentedCode' => true,
        'setextHeadings' => true,
        'referenceLinks' => true,
        'taskLists' => true,
        'emoji' => true,
        'inlineDiffs' => true,
        'fencedQuotes' => true,
    ];

    public function testMeasurePerformance(): void
    {
        if (getenv('CI') !== false && getenv('PERF_IN_CI') === false) {
            $this->markTestSkipped('The performance test is for running by hand. Set PERF_IN_CI to run it here.');
        }

        $budget = (float) (getenv('PERF_BUDGET') ?: 0.4);
        $threshold = (float) (getenv('PERF_THRESHOLD') ?: 10);
        $root = dirname(__DIR__, 2);

        $report = new PerformanceReport($threshold);

        foreach ($this->scenarios() as [$group, $name, $bytes, $work, $isHtml]) {
            $stats = Benchmark::measure($work, 5, $budget);

            $this->assertIsString($stats['result'], $name);

            // Input in the hostile group can produce no output, as when it is
            // only tags that never close.
            if ($group !== 'hostile') {
                $this->assertNotSame('', $stats['result'], $name . ' produced nothing.');
            }

            if ($isHtml) {
                $this->assertSame([], HtmlChecker::violations($stats['result'], ['http', 'https', 'mailto', 'ftp']), $name . ' produced unsafe output.');
            }

            $report->add($group, $name, $bytes, $stats);
        }

        $output = getenv('PERF_OUTPUT') ?: $root . '/build/performance/latest.json';
        $setting = getenv('PERF_BASELINE');
        $baselinePath = PerformanceReport::locateBaseline($root, $setting);
        $baseline = $baselinePath === null ? null : PerformanceReport::load($baselinePath);
        $text = $report->render($baseline, $baselinePath === null ? '' : ltrim(str_replace([$root, '\\'], ['', '/'], $baselinePath), '/'));

        if ($baseline === null && $setting !== 'none') {
            $text .= sprintf(
                "\n  No baseline to compare with. To make this run the baseline, copy %s\n  to tests/performance/baseline.json to share it through git, or to build/performance/baseline.json to keep it to yourself,\n  and run this again after a change.\n",
                $output,
            );
        }

        $report->save($output);

        // PHPUnit doesn't capture standard error, so the report appears
        // whatever the PHPUnit settings and version.
        fwrite(STDERR, $text . sprintf("\n  This run was saved to %s\n\n", $output));

        $this->assertFileExists($output);
    }

    /**
     * Returns the scenarios to measure.
     *
     * @return list<array{0: string, 1: string, 2: int, 3: callable(): string, 4: bool}>
     *     The scenarios. Each has a group, a name, the size of the text in
     *     bytes (0 if the work isn't about a text), the work to time, and
     *     whether the work returns HTML to check.
     */
    private function scenarios(): array
    {
        $scenarios = [];

        $make = static fn (array $settings): MarkdownConverter => new MarkdownConverter(...$settings);
        $reuse = static function (array $settings, string $text) use ($make): callable {
            $converter = $make($settings);

            return static fn (): string => $converter->toHtml($text);
        };

        // ---- realistic ----

        $notes = $this->fixtures(['handwritten-notes', 'generated-notes'], 100000);
        $documents = $this->smallDocuments(2000);
        $documentBytes = array_sum(array_map('strlen', $documents));

        $scenarios[] = ['realistic', 'a long page of release notes', strlen($notes), $reuse(self::WORDPRESS, $notes), true];
        $scenarios[] = ['realistic', 'the same, with every setting on', strlen($notes), $reuse(self::EVERYTHING, $notes), true];
        $scenarios[] = ['realistic', '2,000 small texts, one converter', $documentBytes, static function () use ($reuse, $documents): string {
            $converter = new MarkdownConverter(...self::WORDPRESS);
            $last = '';

            foreach ($documents as $text) {
                $last = $converter->toHtml($text);
            }

            return $last;
        }, true];
        $scenarios[] = ['realistic', '2,000 small texts, a new converter each', $documentBytes, static function () use ($documents): string {
            $last = '';

            foreach ($documents as $text) {
                $last = (new MarkdownConverter(...self::WORDPRESS))->toHtml($text);
            }

            return $last;
        }, true];
        $fixtureBytes = 200 * (strlen((string) file_get_contents(__DIR__ . '/../fixtures/edge-cases.md')) + strlen((string) file_get_contents(__DIR__ . '/../fixtures/hostile-notes.md')));
        $scenarios[] = ['realistic', 'the edge case and hostile fixtures', $fixtureBytes, static function (): string {
            $converter = new MarkdownConverter(...self::WORDPRESS);
            $last = '';

            foreach (['edge-cases', 'hostile-notes'] as $name) {
                for ($i = 0; $i < 200; $i++) {
                    $last = $converter->toHtml((string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $name . '.md'));
                }
            }

            return $last;
        }, true];
        $scenarios[] = ['realistic', 'setting up 2,000 converters', 0, static function (): string {
            $count = 0;

            for ($i = 0; $i < 2000; $i++) {
                $count += (new MarkdownConverter(...self::EVERYTHING)) instanceof MarkdownConverter ? 1 : 0;
            }

            return (string) $count;
        }, false];

        // ---- features ----

        $features = [
            'paragraphs, emphasis, code, and links' => ["A paragraph with *emphasis*, **strong text**, `inline code`, a [link](https://example.com/docs \"Title\"), ~~strikethrough~~\nand a bare address https://example.com/a/b. It goes on with more words, so the line has some length.\n\n", self::WORDPRESS],
            'links and images' => ["See [the guide](https://example.com/guide), [issue #12](https://github.com/o/r/issues/12), ![badge](https://github.com/o/r/badge.svg) and <https://example.com/x>.\n\n", self::WORDPRESS],
            'lists and nested lists' => ["- item one with `code`\n- item two with [a link](https://example.com)\n  - nested item\n    - deeper item\n- item three\n\n1. first\n2. second\n\n", self::WORDPRESS],
            'code blocks' => ["```php\n\$a = 1 < 2 && \"x\";\necho \$a;\n```\n\n", self::WORDPRESS],
            'tables' => ["| Name | Value | Notes |\n| --- | :---: | ---: |\n| a | 1 | first |\n| b | 2 | second *row* with [a link](https://example.com) |\n\n", self::WORDPRESS],
            'quotes and alerts' => ["> quoted text\n> with a [link](https://example.com)\n\n> [!NOTE]\n> Back up your site.\n\n> [!WARNING]\n> **Careful** with `code`.\n\n", self::WORDPRESS],
            'html that is kept' => ["<details>\n<summary>More <b>detail</b></summary>\n\nHidden *text* and <kbd>Ctrl</kbd>.\n\n</details>\n\n<div align=\"center\">\n\n<img src=\"https://github.com/o/r/a.png\" width=\"20\"> centered\n\n</div>\n\n", self::WORDPRESS],
            'headings' => ["# Title\n\n## Section with *emphasis*\n\n### A sub section\n\nText.\n\n", self::WORDPRESS],
            'reference links' => ["Read [the guide][g] and [the notes] or ![logo][l].\n\n[g]: https://example.com/guide \"Guide\"\n[the notes]: https://example.com/notes\n[l]: https://github.com/o/r/logo.png\n\n", self::EVERYTHING],
            'math' => ['Euler wrote $e^{i\pi} + 1 = 0$ and the sum is $$\sum_{k=1}^{n} k = \frac{n(n+1)}{2}$$ for every $n$. A price of $5 stays text.' . "\n\n" . '$$' . "\n" . '\int_0^1 x^2 \, dx = \frac{1}{3}' . "\n" . '$$' . "\n\n" . '```math' . "\n" . 'a^2 + b^2 = c^2' . "\n" . '```' . "\n\n", self::EVERYTHING],
            'diagrams' => ["```mermaid\ngraph TD\n  A[Start] --> B{Check}\n  B -->|yes| C[Done]\n  B -->|no| D[Retry]\n```\n\nText between the diagrams.\n\n```mermaid\nsequenceDiagram\n  Alice->>Bob: Hello\n  Bob-->>Alice: Hi\n```\n\n", self::EVERYTHING],
            'emoji, diffs, and task lists' => ["- [x] done :tada: with {+ added +} and {- removed -}\n- [ ] to do :bug: &copy; www.example.com me@example.com\n\n", self::EVERYTHING],
        ];

        foreach ($features as $name => [$unit, $settings]) {
            $text = $this->repeat($unit, 60000);
            $scenarios[] = ['features', $name, strlen($text), $reuse($settings, $text), true];
        }

        // ---- hostile ----

        $limit = 125000;
        $hostile = [
            'a "<!--" in every few characters' => str_repeat('a<!--', 24000) . 'x>',
            'brackets that never close' => str_repeat('[', $limit),
            'closing brackets with nothing open' => str_repeat(']', $limit),
            'links that never finish' => str_repeat('[a](b "', intdiv($limit, 7)),
            'links inside links' => str_repeat('[a [b](https://example.com) c](https://example.org) ', intdiv($limit, 53)),
            'links around backticks' => str_repeat('[`a`](https://example.com) [`', intdiv($limit, 28)),
            'a long line of emphasis marks' => str_repeat('*_a ', intdiv($limit, 4)),
            'backtick runs of every length' => implode(' ', array_map(static fn (int $n): string => str_repeat('`', $n), range(1, 400))),
            'quotes nested without end' => str_repeat('> ', 20000) . 'a',
            'lists nested without end' => implode("\n", array_map(static fn (int $i): string => str_repeat('  ', $i) . '- a', range(0, 300))),
            'a very long list' => str_repeat("- a\n", intdiv($limit, 4)),
            'a very long table' => "| a |\n| - |\n" . str_repeat("| b |\n", intdiv($limit, 6)),
            'tags that never close' => str_repeat('<b>a', intdiv($limit, 4)),
            'blocks that never close' => str_repeat("<details>\n", intdiv($limit, 10)),
            'bare addresses' => str_repeat('https://example.com/a www.example.com/b me@example.com ', intdiv($limit, 55)),
            'one very long word' => str_repeat('a', $limit),
            'dollar signs that never close' => str_repeat('$a ', intdiv($limit, 3)),
            'math blocks between paragraphs' => str_repeat("a\n$$\n", intdiv($limit, 5)),
            'math fences too far apart to close' => str_repeat("$$\n" . str_repeat("a\n", 600), intdiv($limit, 1203)),
            'many finished math spans' => str_repeat('$a$ $$b$$ ', intdiv($limit, 10)),
            'many finished diagrams' => str_repeat("```mermaid\na\n```\n", intdiv($limit, 16)),
        ];

        foreach ($hostile as $name => $text) {
            $scenarios[] = ['hostile', $name, strlen($text), $reuse(self::EVERYTHING, $text), true];
        }

        return $scenarios;
    }

    /**
     * Returns some of the release note fixtures, repeated to make a text as
     * long as a long page of release notes.
     *
     * @param list<string> $names The fixture names, without the `.md`
     *     extension.
     * @param int $size The target size in bytes.
     *
     * @return string The text.
     */
    private function fixtures(array $names, int $size): string
    {
        $text = '';

        foreach ($names as $name) {
            $text .= (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $name . '.md') . "\n\n";
        }

        return $this->repeat($text, $size);
    }

    private function repeat(string $unit, int $size): string
    {
        return str_repeat($unit, max(1, intdiv($size, strlen($unit))));
    }

    /**
     * Returns many small texts that mix every kind of syntax. A fixed formula
     * picks them, not a random number generator, so every run on every PHP
     * version converts exactly the same texts.
     *
     * @param int $count The number of texts.
     *
     * @return list<string> The texts.
     */
    private function smallDocuments(int $count): array
    {
        $lines = [
            '# Heading', '## Sub heading', 'Plain text here.', '**Bold** and *emphasis* and ~~strikethrough~~.', '> a quote line',
            '> [!NOTE]', '> inside an alert', '- an item', '- [ ] a task', '- [x] a finished task', '1. one', '2. two', '   nested text',
            '```php', '```', '| a | b |', '| - | :-: |', '| 1 | 2 |', '---', '![alt](https://github.com/o/r/a.png "title")',
            '[a link](https://example.com "title")', '<details>', '<summary>Summary</summary>', '</details>', '<b>bold</b> and <kbd>key</kbd>',
            '[reference]: https://example.com/ref', 'See [reference] and [the other][reference].', '`code` and \\*escaped\\*', '&copy; :tada: www.example.com',
            'A longer line of ordinary words that make up a sentence in the notes, going on for a while so it has some length to read.',
            '',
            '',
        ];

        $seed = 2024;
        $documents = [];

        for ($i = 0; $i < $count; $i++) {
            $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
            $length = 4 + ($seed >> 8) % 12;
            $text = [];

            for ($j = 0; $j < $length; $j++) {
                $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                $text[] = $lines[($seed >> 8) % count($lines)];
            }

            $documents[] = implode("\n", $text);
        }

        return $documents;
    }
}
