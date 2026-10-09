<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Tests behavior that the CommonMark specification describes and the converter
 * once got wrong: tabs after list and quote markers, numeric character
 * references that aren't characters, heading underlines on lines that continue
 * a paragraph, and backslash escapes in the first line of a code fence.
 */
final class ConformanceTest extends MarkdownTestCase
{
    // ---- tabs after a list marker ----

    public function testATabAfterAListMarkerStartsAnItem(): void
    {
        $this->assertAllConvert([
            'a dash' => ["-\tfoo", "<ul>\n<li>foo</li>\n</ul>"],
            'a star' => ["*\tfoo", "<ul>\n<li>foo</li>\n</ul>"],
            'a plus' => ["+\tfoo", "<ul>\n<li>foo</li>\n</ul>"],
            'a number and a dot' => ["1.\tfoo", "<ol>\n<li>foo</li>\n</ol>"],
            'a number and a parenthesis' => ["1)\tfoo", "<ol>\n<li>foo</li>\n</ol>"],
            'a big number' => ["10.\tfoo", "<ol start=\"10\">\n<li>foo</li>\n</ol>"],
            'a space and then a tab' => ["- \tfoo", "<ul>\n<li>foo</li>\n</ul>"],
            'two items' => ["-\tfoo\n-\tbar", "<ul>\n<li>foo</li>\n<li>bar</li>\n</ul>"],
            'a nested item that a tab indents' => ["-\tfoo\n\t-\tbar", "<ul>\n<li>foo\n<ul>\n<li>bar</li>\n</ul>\n</li>\n</ul>"],
            'a marker with nothing after the tab' => ["-\t", "<ul>\n<li></li>\n</ul>"],
            'a rule is still a rule' => ["-\t-\t-", '<hr>'],
            'a tab after text is only a tab' => ["foo\t-\tbar", "<p>foo\t-\tbar</p>"],
        ]);
    }

    public function testTwoTabsAfterAListMarkerStartCode(): void
    {
        $this->assertConverts("<ul>\n<li>\n<pre><code>  foo\n</code></pre>\n</li>\n</ul>", "-\t\tfoo", ['indentedCode' => true]);
        $this->assertConverts("<ul>\n<li>foo</li>\n</ul>", "-\t\tfoo", [], 'Without indented code, the item holds text.');
    }

    public function testATabInsideCodeIsKept(): void
    {
        $this->assertConverts("<pre><code>1.\tfoo\n-\tbar\n</code></pre>", "```\n1.\tfoo\n-\tbar\n```");
    }

    // ---- tabs after a quote marker ----

    public function testATabAfterAQuoteMarker(): void
    {
        $this->assertAllConvert([
            'one tab' => [">\tfoo", "<blockquote>\n<p>foo</p>\n</blockquote>"],
            'a space and a tab' => ["> \tfoo", "<blockquote>\n<p>foo</p>\n</blockquote>"],
            'a tab before a list' => [">\t- a", "<blockquote>\n<ul>\n<li>a</li>\n</ul>\n</blockquote>"],
            'spaces are unchanged' => [">   foo", "<blockquote>\n<p>foo</p>\n</blockquote>"],
        ]);
    }

    public function testTwoTabsAfterAQuoteMarkerStartCode(): void
    {
        $this->assertConverts("<blockquote>\n<pre><code>  foo\n</code></pre>\n</blockquote>", ">\t\tfoo", ['indentedCode' => true]);
    }

    // ---- numeric character references ----

    public function testNumericReferencesThatAreNotCharactersBecomeTheReplacementCharacter(): void
    {
        $replacement = "\u{FFFD}";

        $this->assertAllConvert([
            'zero' => ['&#0;', "<p>$replacement</p>"],
            'zero in hexadecimal' => ['&#x0;', "<p>$replacement</p>"],
            'a surrogate' => ['&#xD800;', "<p>$replacement</p>"],
            'a number past the end' => ['&#1114112;', "<p>$replacement</p>"],
            'a hexadecimal number past the end' => ['&#x110000;', "<p>$replacement</p>"],
            'a control character' => ['&#1;', "<p>$replacement</p>"],
            'a control character from the old Windows range' => ['&#x80;', "<p>$replacement</p>"],
            'a character' => ['&#35; &#1234;', "<p># \u{4D2}</p>"],
            'a character outside the basic plane' => ['&#128512;', "<p>\u{1F600}</p>"],
            'the last character' => ['&#x10FFFF;', "<p>\u{10FFFF}</p>"],
        ], ['decodeEntities' => true]);
    }

    public function testNumericReferencesAreLeftAloneWhenDecodingIsOff(): void
    {
        $this->assertConverts('<p>&amp;#0; &amp;#35;</p>', '&#0; &#35;');
    }

    // ---- heading underlines on lines that continue a paragraph ----

    public function testALineWithNoMarkerCannotUnderlineAQuotedParagraph(): void
    {
        $this->assertAllConvert([
            'equals signs after a quote' => ["> foo\nbar\n===", "<blockquote>\n<p>foo<br>bar<br>===</p>\n</blockquote>"],
            'dashes after a quote' => ["> foo\nbar\n--", "<blockquote>\n<p>foo<br>bar<br>--</p>\n</blockquote>"],
            'equals signs after a list item' => ["- foo\nbar\n===", "<ul>\n<li>foo<br>bar<br>===</li>\n</ul>"],
            'an underline with its marker still works' => ["> foo\n> ===", "<blockquote>\n<h1>foo</h1>\n</blockquote>"],
            'a plain underline still works' => ["foo\n===", '<h1>foo</h1>'],
            'a dash underline still works' => ["foo\n---", '<h2>foo</h2>'],
            'an indented underline in an item still works' => ["- foo\n  ===", "<ul>\n<li>\n<h1>foo</h1>\n</li>\n</ul>"],
        ], ['setextHeadings' => true]);
    }

    public function testALineOfEqualsSignsIsTextWhenHeadingsAreOff(): void
    {
        $this->assertConverts("<blockquote>\n<p>foo<br>bar<br>===</p>\n</blockquote>", "> foo\nbar\n===");
    }

    // ---- backslash escapes in the first line of a fence ----

    public function testABackslashEscapeInTheLanguageOfAFence(): void
    {
        $this->assertAllConvert([
            'an escaped plus' => ["``` foo\\+bar\nfoo\n```", "<pre><code class=\"language-foo+bar\">foo\n</code></pre>"],
            'an escaped character that a class can not hold' => ["``` foo\\;bar\nfoo\n```", "<pre><code>foo\n</code></pre>"],
            'a plain language' => ["```php\nfoo\n```", "<pre><code class=\"language-php\">foo\n</code></pre>"],
        ]);
    }
}
