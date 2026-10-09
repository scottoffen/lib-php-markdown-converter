<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Tests input made to be slow. Each case is a way of writing Markdown that can
 * make a converter do far more work than the size of the input suggests.
 */
final class LimitsTest extends MarkdownTestCase
{
    private const SECONDS = 5.0;

    /**
     * Returns the inputs that are made to be slow.
     *
     * @return array<string, string> The inputs, keyed by a name for each.
     */
    private function slowInputs(): array
    {
        $nested = '';

        for ($depth = 0; $depth < 100; $depth++) {
            $nested .= str_repeat('  ', $depth) . "- a\n";
        }

        return [
            'many open brackets' => str_repeat('[', 60000),
            'many unfinished links' => str_repeat('[a](', 20000),
            'many unfinished images' => str_repeat('![a](', 20000),
            'many bracketed words' => str_repeat('[a]', 20000),
            'many finished links' => str_repeat('[a](https://x.test) ', 5000),
            'many open emphasis marks' => str_repeat('*a ', 30000),
            'many finished emphasis marks' => str_repeat('*a* ', 20000),
            'many underscores' => str_repeat('_a', 30000),
            'many tildes' => str_repeat('~~a', 30000),
            'a wall of tildes' => str_repeat('~', 100000),
            'a wall of stars' => str_repeat('*', 100000),
            'a wall of backticks' => str_repeat('`', 100000),
            'backtick runs of growing length' => implode(' ', array_map(static fn (int $n): string => str_repeat('`', $n), range(1, 400))),
            'deeply nested emphasis' => str_repeat('*', 1000) . 'a' . str_repeat('*', 1000),
            'interleaved emphasis' => str_repeat('*a_', 5000) . str_repeat('_a*', 5000),
            'many nested quotes' => str_repeat('> ', 5000) . 'a',
            'many nested list markers' => str_repeat('- ', 5000) . 'a',
            'nested lists by indentation' => $nested,
            'a long list' => str_repeat("- a\n", 20000),
            'a long numbered list' => str_repeat("1. a\n", 20000),
            'a long table' => "| a |\n| - |\n" . str_repeat("| b |\n", 20000),
            'a wide table' => str_repeat('| a ', 5000) . "|\n" . str_repeat('| - ', 5000) . "|\n" . str_repeat('| b ', 5000) . '|',
            'many angle brackets' => str_repeat('<', 100000),
            'an unclosed comment' => '<!--' . str_repeat('a', 100000),
            'many comments' => str_repeat('<!-- a -->', 10000),
            'many comment starts' => str_repeat('<!--', 20000),
            'many comment starts inside a line' => str_repeat('a<!--', 24000) . 'x>',
            'many backslashes' => str_repeat('\\', 100000),
            'one long line' => str_repeat('a', 125000),
            'one long word with a link' => str_repeat('a', 60000) . ' https://x.test/' . str_repeat('b', 1900),
            'many bare addresses' => str_repeat('https://x.test/a ', 5000),
            'many headings' => str_repeat("# a\n", 20000),
            'many fences' => str_repeat("```\na\n```\n", 5000),
            'many blank lines' => str_repeat("\n", 100000),
            'many alerts' => str_repeat("> [!NOTE]\n> a\n\n", 5000),

            // Input that uses the optional settings
            'a wall of ampersands' => str_repeat('&', 100000),
            'many character references' => str_repeat('&amp;&#169;&copy;', 10000),
            'many unfinished character references' => str_repeat('&#x', 30000) . str_repeat('&a', 30000),
            'many www starts' => str_repeat('www.', 30000),
            'many www addresses' => str_repeat('www.a.b ', 10000),
            'one huge www address' => str_repeat('www.a.b', 20000),
            'one huge bare address' => str_repeat('https://x.test/a', 20000),
            'many at signs' => str_repeat('a@', 50000),
            'many mail addresses' => str_repeat('a@b.c ', 10000),
            'a long mail name' => str_repeat('a', 60000) . '@x.test',
            'a long mail domain' => 'a@' . str_repeat('b', 60000) . '.test',
            'many long mail domains' => str_repeat('a@' . str_repeat('b', 200) . '! ', 2000),
            'many definitions' => str_repeat("[a]: https://x.test\n", 20000),
            'many references' => str_repeat('[a][a] ', 10000) . "\n\n[a]: https://x.test",
            'many unfinished references' => str_repeat('[a][', 20000),
            'many indented lines' => str_repeat("    a\n", 20000),
            'many indented blocks' => str_repeat("    a\n\n", 10000),
            'many setext candidates' => str_repeat("a\n---\n", 10000),
            'a long paragraph before an underline' => str_repeat("a\n", 20000) . '===',
            'many task items' => str_repeat("- [ ] a\n", 20000),
            'many tags' => str_repeat('<b>a</b>', 10000),
            'many unclosed tags' => str_repeat('<b>a', 20000),
            'many unclosed block tags' => str_repeat("<details>\n", 20000),
            'many block tag pairs' => str_repeat("<details>\n\na\n\n</details>\n", 5000),
            'many tags in a row' => str_repeat('<b><i><u>', 10000),
            'many images' => str_repeat('<img src="https://x.test/a.png">', 5000),
            'many scripts' => str_repeat('<script>a</script>', 10000),
            'many unfinished scripts' => str_repeat('<script>a', 20000),
            'many tag starts' => str_repeat('<b a="', 20000),
            'many diff starts' => str_repeat('{+ a ', 20000) . str_repeat('[- b ', 20000),
            'many finished diffs' => str_repeat('{+ a +}', 10000),
            'many nested diff marks' => str_repeat('{+ ', 5000) . 'a' . str_repeat(' +}', 5000),
            'many colons' => str_repeat(':', 100000),
            'many emoji names' => str_repeat(':tada:', 20000),
            'many unfinished emoji names' => str_repeat(':tada', 20000) . str_repeat(':a', 20000),
            'a long name between colons' => ':' . str_repeat('a', 100000) . ':',
            'many quote fences' => str_repeat(">>>\n", 20000),
            'many finished quote fences' => str_repeat(">>>\na\n>>>\n", 5000),
            'many nested alert fences' => str_repeat(">>> [!NOTE]\n", 5000),
            'many dollar signs' => str_repeat('$a ', 40000),
            'many dollar signs with no gaps' => str_repeat('$a', 60000),
            'many display math marks' => str_repeat('$$a ', 30000),
            'many finished math spans' => str_repeat('$a$ ', 30000),
            'many math fences' => str_repeat("$$\n", 40000),
            'many finished math blocks' => str_repeat("$$\na\n$$\n", 10000),
            'math fences too far apart to close' => str_repeat("$$\n" . str_repeat("a\n", 600), 100),
            'many diagram fences' => str_repeat("```mermaid\n", 20000),
            'many finished diagrams' => str_repeat("```mermaid\na\n```\n", 10000),
            'a very long diagram' => "```mermaid\n" . str_repeat("A --> B\n", 15000) . "```",
            'diagram fences in quotes and lists' => str_repeat("> ```mermaid\n- ```mermaid\n", 10000),
            'many math fences inside a paragraph' => str_repeat("a\n$$\n", 20000),
            'math fences inside lists and quotes' => str_repeat("> $$\n- $$\n", 10000),
            'math that never ends' => '$' . str_repeat('a$ ', 40000),
            'a very long math block' => "$$\n" . str_repeat('a ', 50000) . "\n$$",
        ];
    }

    /**
     * Returns the settings to try each slow input with: none, and every setting
     * at once.
     *
     * @return array<string, array<string, mixed>> The settings, keyed by a name
     *     for each set.
     */
    private function settings(): array
    {
        return [
            'the defaults' => [],
            'html removed' => ['html' => 'strip'],
            'every setting on' => [
                'html' => 'sanitize', 'imageHosts' => ['*'], 'decodeEntities' => true, 'baseUrl' => 'https://x.test/docs',
                'linkSchemes' => ['http', 'https', 'mailto', 'ftp'], 'autolinkWww' => true, 'autolinkEmails' => true,
                'indentedCode' => true, 'setextHeadings' => true, 'referenceLinks' => true, 'taskLists' => true,
                'emoji' => true, 'inlineDiffs' => true, 'fencedQuotes' => true, 'math' => true, 'mathMaxLength' => 10000, 'mermaid' => true,
            ],
        ];
    }

    public function testNothingTakesLong(): void
    {
        foreach ($this->settings() as $label => $options) {
            foreach ($this->slowInputs() as $name => $input) {
                $start = microtime(true);
                $html = $this->html($input, $options);
                $elapsed = microtime(true) - $start;

                $this->assertLessThan(self::SECONDS, $elapsed, sprintf('%s with %s took %.2f seconds.', $name, $label, $elapsed));
                $this->assertIsString($html, $name);
            }
        }
    }

    /**
     * Tests that comment starts inside a line aren't read again each time.
     *
     * The general limit above is generous. This one is tight, because a comment
     * start inside a line that was never closed once made every later start
     * read to the end of the text again, so the work grew with the square of
     * the size.
     */
    public function testCommentStartsInsideALineAreNotReadAgainEachTime(): void
    {
        $input = str_repeat('a<!--', 40000) . 'x>';

        $start = microtime(true);
        $html = $this->html($input, ['maxLength' => 400000]);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(1.5, $elapsed, sprintf('Took %.2f seconds.', $elapsed));
        $this->assertStringContainsString('&lt;!--', $html);
    }

    public function testTheOutputOfSlowInputIsStillSafe(): void
    {
        foreach ($this->settings() as $label => $options) {
            foreach ($this->slowInputs() as $name => $input) {
                // Large outputs are slow to parse, so the check runs on the
                // start of each.
                $html = $this->html($input, $options + ['maxLength' => 20000]);

                $this->assertSafe($html, $name . ' with ' . $label, $options === [] || ($options['html'] ?? '') === 'strip' ? ['http', 'https', 'mailto'] : ['http', 'https', 'mailto', 'ftp']);
            }
        }
    }

    public function testNestingBeyondTheLimitIsShownAsText(): void
    {
        $html = $this->html(str_repeat('- ', 40) . 'a');

        $this->assertLessThanOrEqual(9, substr_count($html, '<ul>'));
        $this->assertStringContainsString('a', $html);
        $this->assertSafe($html);
    }

    public function testManyEmphasisMarksLeaveTheRestAsText(): void
    {
        $html = $this->html(str_repeat('*a ', 2000));

        $this->assertStringContainsString('*a *a', $html);
        $this->assertSafe($html);
    }

    public function testAnEnormousLabelIsNotALink(): void
    {
        $html = $this->html('[' . str_repeat('a', 2000) . '](https://x.test)');

        $this->assertStringStartsWith('<p>[aaaa', $html, 'The opening bracket is shown as text.');
        $this->assertSafe($html);
    }
}
