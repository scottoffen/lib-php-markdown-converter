<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Rendering;

use LogicException;
use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\Nodes\Alert;
use ScottOffen\MarkdownConverter\Nodes\AlertKind;
use ScottOffen\MarkdownConverter\Nodes\CodeBlock;
use ScottOffen\MarkdownConverter\Nodes\Heading;
use ScottOffen\MarkdownConverter\Nodes\HtmlBlock;
use ScottOffen\MarkdownConverter\Nodes\HtmlLine;
use ScottOffen\MarkdownConverter\Nodes\ListBlock;
use ScottOffen\MarkdownConverter\Nodes\Node;
use ScottOffen\MarkdownConverter\Nodes\Paragraph;
use ScottOffen\MarkdownConverter\Nodes\Quote;
use ScottOffen\MarkdownConverter\Nodes\Rule;
use ScottOffen\MarkdownConverter\Nodes\Table;
use ScottOffen\MarkdownConverter\Options;
use ScottOffen\MarkdownConverter\Parsing\InlineParser;
use ScottOffen\MarkdownConverter\Parsing\UrlPolicy;
use ScottOffen\MarkdownConverter\Rendering\HtmlRenderer;
use ScottOffen\MarkdownConverter\Rendering\Styles;

/**
 * Tests the renderer with blocks built by hand, which is the contract between
 * the renderer and `BlockParser`. The other tests reach the renderer only
 * through the parser, so a fault on either side looks the same there.
 */
final class HtmlRendererTest extends TestCase
{
    /**
     * Creates a renderer with the given settings and definitions.
     *
     * @param array<string, mixed> $settings The arguments for `Options`, by
     *     name.
     * @param array<string, array{url: string, title: string|null}> $definitions
     *     The reference link definitions of the text.
     */
    private function renderer(array $settings = [], array $definitions = []): HtmlRenderer
    {
        $options = new Options(...$settings);
        $styles = new Styles($options->inlineStyles);
        $inline = new InlineParser($options, new UrlPolicy($options->imageHosts, $options->baseUrl, $options->linkSchemes), $styles);

        return new HtmlRenderer($options, $inline, $styles, $definitions);
    }

    /**
     * Asserts that blocks render as the expected HTML.
     *
     * @param list<Node> $nodes
     * @param array<string, mixed> $settings
     */
    private function assertRenders(string $expected, array $nodes, array $settings = [], string $message = ''): void
    {
        $this->assertSame($expected, $this->renderer($settings)->render($nodes), $message);
    }

    /**
     * Creates a list block.
     *
     * @param list<list<Node>> $items
     */
    private function list(array $items, bool $ordered = false, int $start = 1, bool $loose = false): ListBlock
    {
        return new ListBlock($ordered, $start, $loose, $items);
    }

    public function testNothingRendersNothing(): void
    {
        $this->assertRenders('', []);
    }

    public function testABlockOfAKindTheRendererDoesNotKnowIsAnErrorNotSilentlyDropped(): void
    {
        try {
            $this->renderer()->render([new class () extends Node {
            }]);
            $this->fail('A block with no renderer was accepted.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testParagraphsAreReadForInlineSyntaxAndJoinedByNewlines(): void
    {
        $this->assertRenders("<p>a <em>b</em></p>\n<p>c</p>", [new Paragraph('a *b*'), new Paragraph('c')]);
        $this->assertRenders("<p>a</p>\n<p>c</p>", [new Paragraph('a'), new Paragraph(''), new Paragraph('c')], [], 'An empty paragraph leaves no blank line behind.');
    }

    public function testPlainTextIsEscapedAndNotReadAgain(): void
    {
        $this->assertRenders('<p>a &lt;b&gt; *c* &amp; d</p>', [new Paragraph('a <b> *c* & d', true)]);
    }

    public function testReferenceLinksAreResolvedFromTheDefinitionsTheRendererWasGiven(): void
    {
        $definitions = ['g' => ['url' => 'https://example.com/guide', 'title' => 'Guide']];
        $nodes = [new Paragraph('See [g]'), new Heading(2, 'About [g]'), new Table([null], ['[g]'], [['[g]']])];

        $with = $this->renderer(['referenceLinks' => true], $definitions)->render($nodes);

        $this->assertSame(4, substr_count($with, 'href="https://example.com/guide"'), 'One in the paragraph, the heading, the header cell, and the body cell.');
    }

    public function testWithoutDefinitionsABracketIsText(): void
    {
        $html = $this->renderer(['referenceLinks' => true])->render([new Paragraph('See [g]')]);

        $this->assertSame('<p>See [g]</p>', $html);
    }

    public function testHeadings(): void
    {
        $this->assertRenders('<h3>T <em>x</em></h3>', [new Heading(3, 'T *x*')]);
        $this->assertRenders('<h6></h6>', [new Heading(6, '')]);
    }

    public function testHorizontalRules(): void
    {
        $this->assertRenders('<hr>', [new Rule()]);
    }

    public function testCodeBlocks(): void
    {
        $this->assertRenders("<pre><code>a&lt;b &amp; c\n</code></pre>", [new CodeBlock('', 'a<b & c')]);
        $this->assertRenders("<pre><code class=\"language-php\">x\n</code></pre>", [new CodeBlock('php', 'x')]);
        $this->assertRenders('<pre><code></code></pre>', [new CodeBlock('', '')], [], 'No text means no trailing newline.');
        $this->assertRenders("<pre><code class=\"language-x&quot;y\">z\n</code></pre>", [new CodeBlock('x"y', 'z')], [], 'The language is escaped even though the parser limits it.');
    }

    public function testQuotes(): void
    {
        $this->assertRenders("<blockquote>\n<p>a</p>\n</blockquote>", [new Quote([new Paragraph('a')])]);
        $this->assertRenders("<blockquote>\n</blockquote>", [new Quote([])]);
        $this->assertRenders("<blockquote>\n<blockquote>\n<p>a</p>\n</blockquote>\n</blockquote>", [new Quote([new Quote([new Paragraph('a')])])]);
    }

    public function testAlerts(): void
    {
        $this->assertRenders(
            "<div class=\"markdown-alert markdown-alert-warning\">\n<p class=\"markdown-alert-title\">Warning</p>\n<p>b</p>\n</div>",
            [new Alert(AlertKind::Warning, [new Paragraph('b')])],
        );
        $this->assertRenders(
            "<div class=\"markdown-alert markdown-alert-note\">\n<p class=\"markdown-alert-title\">Note</p>\n</div>",
            [new Alert(AlertKind::Note, [])],
            [],
            'An alert with no body is still a title.',
        );
    }

    public function testEveryKindOfAlertHasATitleMatchingItsName(): void
    {
        foreach (AlertKind::cases() as $kind) {
            $html = $this->renderer()->render([new Alert($kind, [])]);

            $this->assertStringContainsString('markdown-alert-' . $kind->value . '"', $html);
            $this->assertStringContainsString('>' . ucfirst($kind->value) . '</p>', $html);
        }
    }

    public function testInlineStylesAreAddedOnlyWhenAskedFor(): void
    {
        $nodes = [new Alert(AlertKind::Tip, [new Paragraph('a')])];

        $this->assertStringNotContainsString('style=', $this->renderer()->render($nodes));
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-tip\" style=\"border-left:4px solid #1a7f37;margin:0 0 16px;padding:0 1em\">\n"
            . "<p class=\"markdown-alert-title\" style=\"color:#1a7f37;font-weight:600\">Tip</p>\n<p>a</p>\n</div>",
            $this->renderer(['inlineStyles' => true])->render($nodes),
        );
    }

    public function testTightListsWriteTheirTextWithoutParagraphs(): void
    {
        $this->assertRenders("<ul>\n<li>a</li>\n<li>b <em>c</em></li>\n</ul>", [$this->list([[new Paragraph('a')], [new Paragraph('b *c*')]])]);
    }

    public function testLooseListsWriteTheirTextInParagraphs(): void
    {
        $this->assertRenders(
            "<ul>\n<li>\n<p>a</p>\n</li>\n<li>\n<p>b</p>\n</li>\n</ul>",
            [$this->list([[new Paragraph('a')], [new Paragraph('b')]], false, 1, true)],
        );
    }

    public function testNumberedListsKeepTheirStartOnlyWhenItIsNotOne(): void
    {
        $this->assertRenders("<ol>\n<li>a</li>\n</ol>", [$this->list([[new Paragraph('a')]], true)]);
        $this->assertRenders("<ol start=\"3\">\n<li>a</li>\n</ol>", [$this->list([[new Paragraph('a')]], true, 3)]);
        $this->assertRenders("<ol start=\"0\">\n<li>a</li>\n</ol>", [$this->list([[new Paragraph('a')]], true, 0)]);
        $this->assertRenders("<ul>\n<li>a</li>\n</ul>", [$this->list([[new Paragraph('a')]], false, 5)], [], 'A bulleted list has no start.');
    }

    public function testListItemsThatDoNotStartOrEndWithTextGetTheirOwnLines(): void
    {
        $code = new CodeBlock('', 'x');
        $inner = $this->list([[new Paragraph('b')]]);

        $this->assertRenders("<ul>\n<li></li>\n</ul>", [$this->list([[]])], [], 'An empty item.');
        $this->assertRenders("<ul>\n<li>a\n<pre><code>x\n</code></pre>\n</li>\n</ul>", [$this->list([[new Paragraph('a'), $code]])]);
        $this->assertRenders("<ul>\n<li>\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ul>", [$this->list([[$inner]])]);
        $this->assertRenders("<ul>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ul>", [$this->list([[new Paragraph('a'), $inner]])]);
    }

    public function testPlainTextInAListItemIsStillTreatedAsAParagraph(): void
    {
        $this->assertRenders("<ul>\n<li>a &lt;b&gt;</li>\n</ul>", [$this->list([[new Paragraph('a <b>', true)]])]);
    }

    public function testTaskBoxesAppearOnlyWhenTaskListsAreOn(): void
    {
        $items = [[new Paragraph('[ ] todo')], [new Paragraph('[x] done')], [new Paragraph('[X] shouting')], [new Paragraph('[ ]no space')]];
        $on = "<ul>\n<li>\u{2610} todo</li>\n<li>\u{2611} done</li>\n<li>\u{2611} shouting</li>\n<li>[ ]no space</li>\n</ul>";
        $off = "<ul>\n<li>[ ] todo</li>\n<li>[x] done</li>\n<li>[X] shouting</li>\n<li>[ ]no space</li>\n</ul>";

        $this->assertRenders($on, [$this->list($items)], ['taskLists' => true]);
        $this->assertRenders($off, [$this->list($items)]);
    }

    public function testATaskBoxIsOnlyLookedForInTheFirstParagraphOfAnItem(): void
    {
        $this->assertRenders(
            "<ul>\n<li>\n<pre><code>[ ] x\n</code></pre>\n</li>\n</ul>",
            [$this->list([[new CodeBlock('', '[ ] x')]])],
            ['taskLists' => true],
        );
        $this->assertRenders("<ul>\n<li>a<br>[ ] b</li>\n</ul>", [$this->list([[new Paragraph("a\n[ ] b")]])], ['taskLists' => true], 'Only the start of the item can be a task box.');
    }

    public function testPlainTextIsNeverTurnedIntoATaskBox(): void
    {
        $this->assertRenders("<ul>\n<li>[ ] a</li>\n</ul>", [$this->list([[new Paragraph('[ ] a', true)]])], ['taskLists' => true]);
    }

    public function testTablesWithAlignmentsAndInlineSyntax(): void
    {
        $table = new Table(['left', null, 'center', 'right'], ['a', 'b', 'c', 'd'], [['*1*', '2', '3', '4']]);

        $this->assertRenders(
            "<table>\n<thead>\n<tr>\n<th align=\"left\">a</th>\n<th>b</th>\n<th align=\"center\">c</th>\n<th align=\"right\">d</th>\n</tr>\n</thead>\n"
            . "<tbody>\n<tr>\n<td align=\"left\"><em>1</em></td>\n<td>2</td>\n<td align=\"center\">3</td>\n<td align=\"right\">4</td>\n</tr>\n</tbody>\n</table>",
            [$table],
        );
    }

    public function testATableWithNoRowsHasNoBody(): void
    {
        $this->assertRenders("<table>\n<thead>\n<tr>\n<th>a</th>\n</tr>\n</thead>\n</table>", [new Table([null], ['a'], [])]);
    }

    public function testTableStylesGoOnTheTableAndEveryCell(): void
    {
        $html = $this->renderer(['inlineStyles' => true])->render([new Table([null], ['a'], [['b']])]);

        $this->assertSame(1, substr_count($html, '<table style="border-collapse:collapse;margin:0 0 16px">'));
        $this->assertSame(2, substr_count($html, 'style="border:1px solid #c3c4c7;padding:4px 8px"'));
    }

    public function testHtmlLinesKeepTheirTagAndAreReadForInlineSyntax(): void
    {
        $this->assertRenders('<summary>Title <em>x</em></summary>', [new HtmlLine('summary', '', 'Title *x*')]);
        $this->assertRenders('<p align="center">a</p>', [new HtmlLine('p', ' align="center"', 'a')], [], 'A paragraph tag that holds only text stays a paragraph.');
    }

    public function testHtmlBlocksKeepTheirTagAndAttributes(): void
    {
        $this->assertRenders("<details open>\n<p>x</p>\n</details>", [new HtmlBlock('details', ' open', [new Paragraph('x')])]);
        $this->assertRenders("<div align=\"center\">\n</div>", [new HtmlBlock('div', ' align="center"', [])]);
    }

    public function testAParagraphTagThatHoldsBlocksIsWrittenAsADiv(): void
    {
        $this->assertRenders("<div align=\"right\">\n<p>x</p>\n</div>", [new HtmlBlock('p', ' align="right"', [new Paragraph('x')])]);
    }

    public function testBlocksInsideBlocksAreWrittenOnTheirOwnLines(): void
    {
        $this->assertRenders(
            "<blockquote>\n<h2>t</h2>\n<ul>\n<li>a</li>\n</ul>\n<hr>\n</blockquote>",
            [new Quote([new Heading(2, 't'), $this->list([[new Paragraph('a')]]), new Rule()])],
        );
    }
}
