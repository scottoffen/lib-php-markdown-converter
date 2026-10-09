<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Parsing;

use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\Nodes\HtmlBlock;
use ScottOffen\MarkdownConverter\Nodes\Paragraph;
use ScottOffen\MarkdownConverter\Options;
use ScottOffen\MarkdownConverter\Parsing\BlockParser;
use ScottOffen\MarkdownConverter\Parsing\TableParser;

/**
 * Tests the block parser on its own. What the parser keeps while it reads a
 * text must belong to that text, so one parser can read any number of texts in
 * any order, and what it finds in one doesn't affect another.
 */
final class BlockParserTest extends TestCase
{
    private function parser(): BlockParser
    {
        return new BlockParser(new Options(html: 'sanitize', referenceLinks: true), new TableParser());
    }

    public function testADocumentHoldsTheBlocksAndTheDefinitions(): void
    {
        $document = $this->parser()->parse("Some text.\n\n[a]: https://x.test \"Title\"");

        $this->assertCount(1, $document->nodes);
        $this->assertInstanceOf(Paragraph::class, $document->nodes[0]);
        $this->assertSame(['a' => ['url' => 'https://x.test', 'title' => 'Title']], $document->definitions);
    }

    public function testTheDefinitionsOfOneTextAreNotThoseOfTheNext(): void
    {
        $parser = $this->parser();

        $first = $parser->parse('[a]: https://x.test');
        $second = $parser->parse('Nothing is defined here.');

        $this->assertSame([], $second->definitions);
        $this->assertSame(['a'], array_keys($first->definitions), 'Reading another text does not change the first result.');
    }

    public function testTheFirstDefinitionOfALabelWinsEvenAcrossBlocks(): void
    {
        $document = $this->parser()->parse("[a]: https://x.test/1\n\n> [a]: https://x.test/2\n\n- [a]: https://x.test/3");

        $this->assertSame('https://x.test/1', $document->definitions['a']['url']);
    }

    public function testTheSameTextGivesTheSameResultEveryTime(): void
    {
        $parser = $this->parser();
        $text = "# Title\n\n- one\n- two\n\n[a]: https://x.test\n\n<details>\nBody\n</details>";

        $this->assertEquals($parser->parse($text), $parser->parse($text));
    }

    /**
     * Tests that the limit on searching for closing tags applies to each text
     * separately.
     *
     * Looking for the tag that closes a block can read only so many lines in
     * one text. A text that only opens tags uses up the limit, and the next
     * text must still have all of it.
     */
    public function testTheLimitOnSearchingForClosingTagsIsPerText(): void
    {
        $parser = $this->parser();

        $parser->parse(str_repeat("<details>\n", 800));
        $document = $parser->parse("<details>\nBody\n</details>");

        $this->assertCount(1, $document->nodes);
        $this->assertInstanceOf(HtmlBlock::class, $document->nodes[0], 'The tag was closed, so the limit of the text before it was not carried over.');
    }

    public function testTheLimitOnSearchingIsReallyUsedUpWithinOneText(): void
    {
        // Many unclosed tags followed by a closed one, all in one text, show
        // that the limit is what stops the search.
        $document = $this->parser()->parse(str_repeat("<details>\n", 800) . "<details>\nBody\n</details>");

        $blocks = array_filter($document->nodes, static fn ($node): bool => $node instanceof HtmlBlock);

        $this->assertSame([], $blocks, 'With the limit used up, not even the closed tag at the end is found.');
    }
}
