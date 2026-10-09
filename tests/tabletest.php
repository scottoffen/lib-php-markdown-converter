<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

final class TableTest extends MarkdownTestCase
{
    /**
     * Returns the markup for a table, as the converter writes it.
     *
     * @param list<string|null> $alignments The alignment of each column.
     * @param list<string> $head The header cells.
     * @param list<list<string>> $rows The body rows.
     */
    private function table(array $alignments, array $head, array $rows): string
    {
        $cell = static fn (string $tag, string $content, ?string $align): string
            => '<' . $tag . ($align !== null ? ' align="' . $align . '"' : '') . '>' . $content . '</' . $tag . '>';

        $html = "<table>\n<thead>\n<tr>\n";

        foreach ($head as $i => $content) {
            $html .= $cell('th', $content, $alignments[$i]) . "\n";
        }

        $html .= "</tr>\n</thead>\n";

        if ($rows !== []) {
            $html .= "<tbody>\n";

            foreach ($rows as $row) {
                $html .= "<tr>\n";

                foreach ($row as $i => $content) {
                    $html .= $cell('td', $content, $alignments[$i]) . "\n";
                }

                $html .= "</tr>\n";
            }

            $html .= "</tbody>\n";
        }

        return $html . '</table>';
    }

    public function testABasicTable(): void
    {
        $this->assertConverts(
            $this->table([null, null], ['a', 'b'], [['1', '2'], ['3', '4']]),
            "| a | b |\n| - | - |\n| 1 | 2 |\n| 3 | 4 |",
        );
    }

    public function testRowsWithoutOuterPipes(): void
    {
        $this->assertConverts(
            $this->table([null, null], ['a', 'b'], [['1', '2']]),
            "a | b\n- | -\n1 | 2",
        );
    }

    public function testColumnAlignment(): void
    {
        $this->assertConverts(
            $this->table(['left', 'center', 'right', null], ['a', 'b', 'c', 'd'], [['1', '2', '3', '4']]),
            "| a | b | c | d |\n|:--|:-:|--:|---|\n| 1 | 2 | 3 | 4 |",
        );
    }

    public function testAHeaderWithNoRows(): void
    {
        $this->assertConverts($this->table([null], ['a'], []), "| a |\n| - |");
    }

    public function testASingleColumn(): void
    {
        $this->assertConverts($this->table([null], ['a'], [['b']]), "| a |\n|---|\n| b |");
    }

    public function testShortRowsArePaddedAndLongRowsAreCut(): void
    {
        $this->assertConverts(
            $this->table([null, null], ['a', 'b'], [['1', ''], ['2', '3']]),
            "| a | b |\n| - | - |\n| 1 |\n| 2 | 3 | 4 | 5 |",
        );
    }

    public function testEmptyCells(): void
    {
        $this->assertConverts(
            $this->table([null, null, null], ['a', 'b', 'c'], [['', '2', '']]),
            "| a | b | c |\n| - | - | - |\n|   | 2 |   |",
        );
    }

    public function testAnEscapedPipeStaysInsideItsCell(): void
    {
        $this->assertConverts(
            $this->table([null, null], ['a', 'b'], [['x | y', '2']]),
            "| a | b |\n| - | - |\n| x \\| y | 2 |",
        );
    }

    public function testAnEscapedPipeInsideCode(): void
    {
        $this->assertConverts(
            $this->table([null], ['a'], [['<code>x|y</code>']]),
            "| a |\n| - |\n| `x\\|y` |",
        );
    }

    public function testInlineMarkupInCells(): void
    {
        $this->assertConverts(
            $this->table([null, null], ['<strong>a</strong>', '<code>b</code>'], [['<em>c</em>', $this->a('https://x.test', 'd')]]),
            "| **a** | `b` |\n| - | - |\n| *c* | [d](https://x.test) |",
        );
    }

    public function testHtmlInCellsIsEscaped(): void
    {
        $this->assertConverts(
            $this->table([null], ['&lt;b&gt;'], [['&lt;script&gt;alert(1)&lt;/script&gt;']]),
            "| <b> |\n| - |\n| <script>alert(1)</script> |",
        );
    }

    public function testATableNeedsADelimiterRowWithTheSameNumberOfCells(): void
    {
        $this->assertAllConvert([
            'too few delimiter cells' => ["| a | b |\n| - |\n| 1 | 2 |", "<p>| a | b |<br>| - |<br>| 1 | 2 |</p>"],
            'a delimiter with letters in it' => ["| a |\n| x |\n| 1 |", "<p>| a |<br>| x |<br>| 1 |</p>"],
            'no pipes at all' => ["a\n-", '<p>a<br>-</p>'],
        ]);
    }

    public function testATableEndsAtABlankLine(): void
    {
        $this->assertConverts(
            $this->table([null], ['a'], [['1']]) . "\n<p>text</p>",
            "| a |\n| - |\n| 1 |\n\ntext",
        );
    }

    public function testATableEndsWhereAnotherBlockStarts(): void
    {
        $this->assertConverts(
            $this->table([null], ['a'], [['1']]) . "\n<blockquote>\n<p>q</p>\n</blockquote>",
            "| a |\n| - |\n| 1 |\n> q",
        );
    }

    public function testATableInsideAListItemAndAQuote(): void
    {
        $this->assertConverts(
            "<ul>\n<li>\n" . $this->table([null], ['a'], [['1']]) . "\n</li>\n</ul>",
            "- | a |\n  | - |\n  | 1 |",
        );
        $this->assertConverts(
            "<blockquote>\n" . $this->table([null], ['a'], [['1']]) . "\n</blockquote>",
            "> | a |\n> | - |\n> | 1 |",
        );
    }

    public function testInlineStylesAreAddedWhenAsked(): void
    {
        $html = $this->html("| a |\n| - |\n| 1 |", ['inlineStyles' => true]);

        $this->assertStringContainsString('<table style="border-collapse:collapse;margin:0 0 16px">', $html);
        $this->assertStringContainsString('<th style="border:1px solid #c3c4c7;padding:4px 8px">a</th>', $html);
        $this->assertStringContainsString('<td style="border:1px solid #c3c4c7;padding:4px 8px">1</td>', $html);
        $this->assertSafe($html);
    }
}
