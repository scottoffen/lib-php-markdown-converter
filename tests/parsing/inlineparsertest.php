<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Parsing;

use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\Options;
use ScottOffen\MarkdownConverter\Parsing\InlineParser;
use ScottOffen\MarkdownConverter\Parsing\UrlPolicy;
use ScottOffen\MarkdownConverter\Rendering\Styles;

/**
 * Tests the inline parser on its own. What the parser keeps while it reads the
 * text of a block must belong to that block, so one parser can read any number
 * of blocks in any order, and nothing in one affects another.
 */
final class InlineParserTest extends TestCase
{
    private const DEFINITION = ['a' => ['url' => 'https://x.test/ref', 'title' => null]];

    /**
     * Creates an inline parser with the given settings.
     *
     * @param array<string, mixed> $settings The arguments for `Options`, by
     *     name.
     */
    private function parser(array $settings = []): InlineParser
    {
        $options = new Options(...$settings);

        return new InlineParser($options, new UrlPolicy($options->imageHosts, $options->baseUrl, $options->linkSchemes), new Styles($options->inlineStyles));
    }

    public function testDefinitionsComeFromTheCallAndAreNotRemembered(): void
    {
        $parser = $this->parser(['referenceLinks' => true]);

        $this->assertStringContainsString('<a href="https://x.test/ref"', $parser->parse('[a]', self::DEFINITION));
        $this->assertSame('[a]', $parser->parse('[a]'), 'The next call is given no definitions, and must not use the last ones.');
        $this->assertStringContainsString('<a href="https://x.test/ref"', $parser->parse('[a]', self::DEFINITION), 'And they are used again when they are given again.');
    }

    public function testTheLimitOnBracketsIsForOneBlockAndNotForAllOfThem(): void
    {
        $parser = $this->parser();
        $block = str_repeat('[a](https://x.test) ', 150);

        // Each block can have 200 brackets turned into links. Three blocks of
        // 150 make 450, and the parser must refuse none.
        foreach ([1, 2, 3] as $round) {
            $this->assertSame(150, substr_count($parser->parse($block), '>a</a>'), 'Block ' . $round);
        }
    }

    public function testTheLimitOnBracketsStopsAtTwoHundredWithinABlock(): void
    {
        $this->assertSame(200, substr_count($this->parser()->parse(str_repeat('[a](https://x.test) ', 250)), '>a</a>'));
    }

    public function testTheDepthOfTagsIsForOneBlockAndNotCarriedOver(): void
    {
        $parser = $this->parser(['html' => 'sanitize']);

        // The parser removes tags nested more deeply than the limit and keeps
        // the text.
        $deep = str_repeat('<b>', 40) . 'x' . str_repeat('</b>', 40);

        $this->assertStringNotContainsString(str_repeat('<strong>', 40), $parser->parse($deep));
        $this->assertSame('<strong><em>y</em></strong>', $parser->parse('<b><i>y</i></b>'), 'A block read after it has all the depth it needs.');
    }

    public function testTheSameTextGivesTheSameResultNoMatterWhatWasReadBefore(): void
    {
        $parser = $this->parser(['html' => 'sanitize', 'referenceLinks' => true, 'inlineDiffs' => true]);
        $text = '*a* [b](https://x.test) [c] <b>d</b> {+ e +}';
        $expected = $parser->parse($text, self::DEFINITION);

        $parser->parse(str_repeat('[', 500) . str_repeat('<b>', 50));
        $parser->parse('[a]', self::DEFINITION);

        $this->assertSame($expected, $parser->parse($text, self::DEFINITION));
    }
}
