<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Tests the `math` setting: math inside a line, display math, blocks between
 * `$$` lines, fenced `math` blocks, the three output formats, and the length
 * limit.
 */
final class MathTest extends MarkdownTestCase
{
    private const ON = ['math' => true];

    /** Wraps TeX in the pandoc format for math inside a line. */
    private function inline(string $tex): string
    {
        return '<span class="math inline">\\(' . $tex . '\\)</span>';
    }

    /** Wraps TeX in the pandoc format for display math inside a paragraph. */
    private function display(string $tex): string
    {
        return '<span class="math display">\\[' . $tex . '\\]</span>';
    }

    /** Wraps TeX in the pandoc format for a block of display math. */
    private function block(string $tex): string
    {
        return '<div class="math display">\\[' . $tex . '\\]</div>';
    }

    // ---- off by default ----

    public function testMathIsShownAsTextByDefault(): void
    {
        $this->assertAllConvert([
            'inline math' => ['$a_b$ and $c$', '<p>$a_b$ and $c$</p>'],
            'display math' => ['$$a$$', '<p>$$a$$</p>'],
            'a block' => ["$$\nx\n$$", '<p>$$<br>x<br>$$</p>'],
            'a fence' => ["```math\nx\n```", "<pre><code class=\"language-math\">x\n</code></pre>"],
        ]);
    }

    // ---- inside a line ----

    public function testMathInsideALine(): void
    {
        $this->assertAllConvert([
            'a simple span' => ['Let $x$ be 1.', '<p>Let ' . $this->inline('x') . ' be 1.</p>'],
            'underscores and stars are not emphasis' => ['$a_b^2 * c_d$', '<p>' . $this->inline('a_b^2 * c_d') . '</p>'],
            'backslashes and braces are kept' => ['$e^{i\pi}+1=0$', '<p>' . $this->inline('e^{i\pi}+1=0') . '</p>'],
            'less than and ampersand are escaped' => ['$a<b>c & d$', '<p>' . $this->inline('a&lt;b&gt;c &amp; d') . '</p>'],
            'quotes are escaped' => ['$"x"$', '<p>' . $this->inline('&quot;x&quot;') . '</p>'],
            'two spans' => ['$a$ and $b$', '<p>' . $this->inline('a') . ' and ' . $this->inline('b') . '</p>'],
            'next to punctuation' => ['($x$), $y$.', '<p>(' . $this->inline('x') . '), ' . $this->inline('y') . '.</p>'],
            'inside emphasis' => ['*$x_1$*', '<p><em>' . $this->inline('x_1') . '</em></p>'],
            'inside strong text' => ['**the $x$ case**', '<p><strong>the ' . $this->inline('x') . ' case</strong></p>'],
            'inside a heading' => ['# About $x$', '<h1>About ' . $this->inline('x') . '</h1>'],
            'inside a list item' => ['- $x$', "<ul>\n<li>" . $this->inline('x') . "</li>\n</ul>"],
            'inside a quote' => ['> $x$', "<blockquote>\n<p>" . $this->inline('x') . "</p>\n</blockquote>"],
            'across a line break' => ['$a +' . "\n" . 'b$', '<p>' . $this->inline("a +\nb") . '</p>'],
            'a line that starts a list ends the paragraph first' => ['$a' . "\n" . '+ b$', "<p>\$a</p>\n<ul>\n<li>b\$</li>\n</ul>"],
            'inside the text of a link' => ['[see $x$](https://x.test)', '<p>' . $this->a('https://x.test', 'see ' . $this->inline('x')) . '</p>'],
            'a code span wins when it comes first' => ['`$a$` and $b$', '<p><code>$a$</code> and ' . $this->inline('b') . '</p>'],
            'math wins when it comes first' => ['$`a`$', '<p>' . $this->inline('`a`') . '</p>'],
        ], self::ON);
    }

    public function testCurrencyStaysText(): void
    {
        $this->assertAllConvert([
            'two prices' => ['It costs $5 and $10 today.', '<p>It costs $5 and $10 today.</p>'],
            'a range' => ['$5-$10', '<p>$5-$10</p>'],
            'prices in parentheses' => ['($5) and ($6)', '<p>($5) and ($6)</p>'],
            'a closing dollar before a digit' => ['$x$5', '<p>$x$5</p>'],
            'a space after the opening dollar' => ['$ x$ and $ y $', '<p>$ x$ and $ y $</p>'],
            'a space before the closing dollar' => ['$x $', '<p>$x $</p>'],
            'a lone dollar sign' => ['a $ b', '<p>a $ b</p>'],
            'a dollar at the end' => ['cost: $', '<p>cost: $</p>'],
            'three dollars in a row' => ['$$$x$$$', '<p>$$$x$$$</p>'],
            'four dollars in a row' => ['$$$$', '<p>$$$$</p>'],
        ], self::ON);
    }

    public function testEscapedDollarSigns(): void
    {
        $this->assertAllConvert([
            'an escaped opening dollar' => ['\$x$ and $y$', '<p>$x' . '$ and ' . $this->inline('y') . '</p>'],
            'an escaped dollar is a dollar in the TeX' => ['$a \$ b$', '<p>' . $this->inline('a \$ b') . '</p>'],
            'an escaped backslash does not escape the dollar' => ['$a\\\\$', '<p>' . $this->inline('a\\\\') . '</p>'],
            'an escaped closing dollar does not close' => ['$a\$', '<p>$a$</p>'],
        ], self::ON);
    }

    // ---- display math inside a paragraph ----

    public function testDisplayMathInsideAParagraph(): void
    {
        $this->assertAllConvert([
            'on one line' => ['A $$x = 1$$ here.', '<p>A ' . $this->display('x = 1') . ' here.</p>'],
            'with spaces inside' => ['$$ x $$', '<p>' . $this->display(' x ') . '</p>'],
            'across lines' => ["$$ a\nb $$", '<p>' . $this->display(" a\nb ") . '</p>'],
            'with a single dollar inside' => ['$$a $ b$$', '<p>' . $this->display('a $ b') . '</p>'],
            'empty is not math' => ['$$ $$', '<p>$$ $$</p>'],
            'with no end' => ['$$x', '<p>$$x</p>'],
            'an escaped end does not close' => ['$$a \$$ b$$', '<p>' . $this->display('a \$$ b') . '</p>'],
        ], self::ON);
    }

    // ---- blocks between $$ lines ----

    public function testBlocksBetweenDollarLines(): void
    {
        $this->assertAllConvert([
            'a block' => ["$$\nx = 1\n$$", $this->block('x = 1')],
            'several lines' => ["$$\na &= b \\\\\n c &= d\n$$", $this->block("a &amp;= b \\\\\n c &amp;= d")],
            'text before and after' => ["Before\n\n$$\nx\n$$\n\nAfter", "<p>Before</p>\n" . $this->block('x') . "\n<p>After</p>"],
            'with no blank lines around it' => ["text\n$$\nx\n$$\nmore", "<p>text</p>\n" . $this->block('x') . "\n<p>more</p>"],
            'lines that look like other blocks' => ["$$\n- a\n# b\n> c\n---\n$$", $this->block("- a\n# b\n&gt; c\n---")],
            'a blank line inside' => ["$$\na\n\nb\n$$", $this->block("a\n\nb")],
            'trailing spaces on the marks' => ["$$  \nx\n$$  ", $this->block('x')],
            'up to three spaces of indentation' => ["   $$\nx\n   $$", $this->block('x')],
            'less than is escaped' => ["$$\nx < y\n$$", $this->block('x &lt; y')],
            'two blocks' => ["$$\na\n$$\n\n$$\nb\n$$", $this->block('a') . "\n" . $this->block('b')],
            'a block stops a table' => ["| a |\n| - |\n| 1 |\n$$\nx\n$$", "<table>\n<thead>\n<tr>\n<th>a</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>1</td>\n</tr>\n</tbody>\n</table>\n" . $this->block('x')],
        ], self::ON);
    }

    public function testADollarLineWithNoMatchIsText(): void
    {
        $this->assertAllConvert([
            'no closing line' => ["a\n$$\nb", '<p>a<br>$$<br>b</p>'],
            'only the opening line' => ['$$', '<p>$$</p>'],
            'an empty block' => ["$$\n$$", '<p>$$<br>$$</p>'],
            'a blank block' => ["$$\n\n$$", '<p>$$</p>' . "\n" . '<p>$$</p>'],
            'text on the marks lines' => ["$$ a\nb\n$$ c", '<p>' . $this->display(" a\nb\n") . ' c</p>'],
            'five spaces of indentation is not a block, but the paragraph is still math' => ["     $$\nx\n$$", '<p>' . $this->display("\nx\n") . '</p>'],
        ], self::ON);
    }

    public function testBlocksInsideOtherBlocks(): void
    {
        $this->assertAllConvert([
            'a quote' => ["> $$\n> x < y\n> $$\n> end", "<blockquote>\n" . $this->block('x &lt; y') . "\n<p>end</p>\n</blockquote>"],
            'a list item' => ["- a\n\n  $$\n  x\n  $$\n- b", "<ul>\n<li>\n<p>a</p>\n" . $this->block('x') . "\n</li>\n<li>\n<p>b</p>\n</li>\n</ul>"],
            'a tight list item' => ["1. a\n   $$\n   x\n   $$", "<ol>\n<li>a\n" . $this->block('x') . "\n</li>\n</ol>"],
            'a list item that follows text' => ["* a\n$$\nb\n$$", "<ul>\n<li>a</li>\n</ul>\n" . $this->block('b')],
            'a quote that follows text' => ["> text\n$$\nx\n$$", "<blockquote>\n<p>text</p>\n</blockquote>\n" . $this->block('x')],
            'an alert' => ["> [!NOTE]\n> $$\n> x\n> $$", "<div class=\"markdown-alert markdown-alert-note\">\n<p class=\"markdown-alert-title\">Note</p>\n" . $this->block('x') . "\n</div>"],
        ], self::ON);
    }

    public function testADollarLineInsideACodeFenceIsCode(): void
    {
        $this->assertConverts("<pre><code>\$\$\nx\n\$\$\n</code></pre>", "```\n\$\$\nx\n\$\$\n```", self::ON);
        $this->assertConverts("<pre><code>\$x\$\n</code></pre>", "```\n\$x\$\n```", self::ON);
    }

    // ---- fenced math ----

    public function testFencedMathBlocks(): void
    {
        $this->assertAllConvert([
            'a math fence' => ["```math\n\\frac{a}{b}\n```", $this->block('\\frac{a}{b}')],
            'several lines' => ["```math\na\nb\n```", $this->block("a\nb")],
            'a tilde fence' => ["~~~math\nx\n~~~", $this->block('x')],
            'text after the word math' => ["```math extra\nx\n```", $this->block('x')],
            'no closing fence' => ["```math\nx", $this->block('x')],
            'an empty fence is code' => ["```math\n```", '<pre><code class="language-math"></code></pre>'],
            'another language is code' => ["```tex\nx\n```", "<pre><code class=\"language-tex\">x\n</code></pre>"],
            'in a quote' => ["> ```math\n> x\n> ```", "<blockquote>\n" . $this->block('x') . "\n</blockquote>"],
        ], self::ON);
    }

    // ---- the output formats ----

    public function testTheClassFormat(): void
    {
        $this->assertAllConvert([
            'inside a line' => ['$a_b$', '<p><span class="math-inline">a_b</span></p>'],
            'display math inside a paragraph' => ['$$a$$', '<p><span class="math-display">a</span></p>'],
            'a block' => ["$$\na < b\n$$", '<div class="math-display">a &lt; b</div>'],
            'a fence' => ["```math\nx\n```", '<div class="math-display">x</div>'],
        ], ['math' => true, 'mathFormat' => 'class']);
    }

    public function testTheDelimitersFormat(): void
    {
        $this->assertAllConvert([
            'inside a line' => ['Let $a_b$ be', '<p>Let $a_b$ be</p>'],
            'display math inside a paragraph' => ['$$a < b$$', '<p>$$a &lt; b$$</p>'],
            'a block' => ["$$\na < b\n$$", "<p>$$\na &lt; b\n$$</p>"],
            'a fence' => ["```math\nx\n```", "<p>$$\nx\n$$</p>"],
        ], ['math' => true, 'mathFormat' => 'delimiters']);
    }

    public function testTheFormatIsIgnoredWhenMathIsOff(): void
    {
        $this->assertConverts('<p>$x$</p>', '$x$', ['mathFormat' => 'class']);
    }

    // ---- the length limit ----

    public function testMathLongerThanTheLimitIsText(): void
    {
        $long = str_repeat('a', 1001);

        $this->assertConverts('<p>$' . $long . '$</p>', '$' . $long . '$', self::ON);
        $this->assertConverts('<p>$$' . $long . '$$</p>', '$$' . $long . '$$', self::ON);
        $this->assertConverts('<p>' . $this->inline($long) . '</p>', '$' . $long . '$', self::ON + ['mathMaxLength' => 2000]);
        $this->assertConverts('<p>' . $this->inline(str_repeat('a', 1000)) . '</p>', '$' . str_repeat('a', 1000) . '$', self::ON);
    }

    public function testABlockLongerThanTheLimitIsText(): void
    {
        $line = str_repeat('a', 400);
        $html = $this->html("$$\n$line\n$line\n$line\n$$", self::ON);

        $this->assertStringNotContainsString('class="math', $html);
        $this->assertStringContainsString($line, $html);

        $this->assertStringContainsString('class="math display"', $this->html("$$\n$line\n$line\n$line\n$$", self::ON + ['mathMaxLength' => 2000]));
    }

    public function testAFenceLongerThanTheLimitIsCode(): void
    {
        $text = str_repeat('a', 1001);

        $this->assertConverts("<pre><code class=\"language-math\">$text\n</code></pre>", "```math\n$text\n```", self::ON);
        $this->assertStringContainsString('class="math display"', $this->html("```math\n$text\n```", self::ON + ['mathMaxLength' => 1001]));
    }

    public function testOnlyTheFirstTwoHundredPlacesAreTried(): void
    {
        $html = $this->html(str_repeat('$a$ ', 300), self::ON);

        $this->assertSame(200, substr_count($html, 'class="math inline"'));
        $this->assertStringContainsString('$a$', $html);
    }

    // ---- things that go with math ----

    public function testMathInATableCellNeedsAnEscapedPipe(): void
    {
        $table = "| a | b |\n| - | - |\n| " . '$a \| b$ | $$x_1$$ |';

        $this->assertStringContainsString('<td>' . $this->inline('a | b') . '</td>', $this->html($table, self::ON));
        $this->assertStringContainsString('<td>' . $this->display('x_1') . '</td>', $this->html($table, self::ON));
    }

    public function testMathWorksWithTheOtherSettings(): void
    {
        $options = ['math' => true, 'html' => 'sanitize', 'emoji' => true, 'inlineDiffs' => true, 'taskLists' => true, 'decodeEntities' => true, 'inlineStyles' => true];

        $this->assertConverts('<p>' . $this->inline('a:tada:b &amp; c') . ' ' . "\u{1F389}" . '</p>', '$a:tada:b & c$ :tada:', ['math' => true, 'emoji' => true]);
        $this->assertStringContainsString($this->inline('x'), $this->html('<b>$x$</b>', $options));
        $this->assertStringContainsString($this->block('x'), $this->html("$$\nx\n$$", $options));
        $this->assertSafe($this->html("$$\n<script>x</script>\n$$ $<b>y</b>$", $options));
    }
}
