<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Tests the `html` setting. `"escape"` shows tags as text, `"strip"` removes
 * them, and `"sanitize"` writes a short list of safe tags from scratch and
 * removes the rest.
 */
final class HtmlTest extends MarkdownTestCase
{
    private const SANITIZE = ['html' => 'sanitize'];

    public function testHtmlIsShownAsTextByDefault(): void
    {
        $this->assertAllConvert([
            'a tag' => ['<b>x</b>', '<p>&lt;b&gt;x&lt;/b&gt;</p>'],
            'a script' => ['<script>alert(1)</script>', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
            'a block tag' => ["<details>\n\nx\n\n</details>", "<p>&lt;details&gt;</p>\n<p>x</p>\n<p>&lt;/details&gt;</p>"],
        ]);
    }

    public function testStripRemovesTagsAndKeepsText(): void
    {
        $this->assertAllConvert([
            'inline tags' => ['a <b>x</b> and <span class="c">y</span> z', '<p>a x and y z</p>'],
            'a script is removed with its content' => ['a <script>alert(1)</script> b', '<p>a  b</p>'],
            'a style is removed with its content' => ['a <style>p{color:red}</style> b', '<p>a  b</p>'],
            'a script that never ends only loses its tag' => ['a <script>alert(1)', '<p>a alert(1)</p>'],
            'an image' => ['a <img src="https://x.test/a.png"> b', '<p>a  b</p>'],
            'a line of only tags leaves nothing' => ["<details>\n<summary>More</summary>\n\nBody\n\n</details>", "<p>More</p>\n<p>Body</p>"],
            'an address in angle brackets is not a tag' => ['<https://x.test>', '<p>' . $this->a('https://x.test', 'https://x.test') . '</p>'],
            'a comparison is not a tag' => ['1 < 2 and 3 > 2', '<p>1 &lt; 2 and 3 &gt; 2</p>'],
            'comments are dropped' => ['a <!-- b --> c', '<p>a  c</p>'],
        ], ['html' => 'strip']);
    }

    public function testSanitizeWritesTheAllowedInlineTags(): void
    {
        $this->assertAllConvert([
            'bold and italic' => ['<b>a</b> <strong>b</strong> <i>c</i> <em>d</em>', '<p><strong>a</strong> <strong>b</strong> <em>c</em> <em>d</em></p>'],
            'underline and strike' => ['<u>a</u> <s>b</s> <strike>c</strike> <del>d</del> <ins>e</ins>', '<p><u>a</u> <del>b</del> <del>c</del> <del>d</del> <ins>e</ins></p>'],
            'keys, sub, and sup' => ['<kbd>Ctrl</kbd> H<sub>2</sub>O x<sup>2</sup> <mark>m</mark> <small>s</small>', '<p><kbd>Ctrl</kbd> H<sub>2</sub>O x<sup>2</sup> <mark>m</mark> <small>s</small></p>'],
            'upper case tags' => ['<B>a</B>', '<p><strong>a</strong></p>'],
            'line breaks' => ['a<br>b<br/>c<br />d', '<p>a<br>b<br>c<br>d</p>'],
            'markdown inside a tag' => ['<b>*a* and `b`</b>', '<p><strong><em>a</em> and <code>b</code></strong></p>'],
            'tags inside emphasis' => ['**<i>a</i>**', '<p><strong><em>a</em></strong></p>'],
            'code is not read as markdown' => ['<code>*a* <b></code>', '<p><code>*a* &lt;b&gt;</code></p>'],
            'attributes are dropped' => ['<b onclick="alert(1)" style="x" class="y">a</b>', '<p><strong>a</strong></p>'],
            'nested tags of the same name' => ['<b>a <b>b</b> c</b>', '<p><strong>a <strong>b</strong> c</strong></p>'],
        ], self::SANITIZE);
    }

    public function testSanitizeRemovesWhatIsNotOnTheList(): void
    {
        $this->assertAllConvert([
            'an unknown tag keeps its text' => ['<span class="x">hi</span> <font color="red">there</font>', '<p>hi there</p>'],
            'a script is removed with its content' => ['a <script>alert(1)</script> b', '<p>a  b</p>'],
            'an iframe' => ['a <iframe src="https://x.test"></iframe> b', '<p>a  b</p>'],
            'a form' => ['<form action="https://evil.test"><input name="x"></form>', ''],
            'an unclosed tag' => ['<b>unclosed', '<p>unclosed</p>'],
            'a closing tag with no opening' => ['</b>text</i>', '<p>text</p>'],
            'a self closing tag that needs a pair' => ['<b/>text', '<p>text</p>'],
            'a block tag in the middle of a line' => ['a <div>b</div> c', '<p>a b c</p>'],
            'comments are dropped' => ['a <!-- <b>x</b> --> c', '<p>a  c</p>'],
            'a comparison is not a tag' => ['1 < 2 and 3 > 2', '<p>1 &lt; 2 and 3 &gt; 2</p>'],
        ], self::SANITIZE);
    }

    public function testSanitizedLinks(): void
    {
        $this->assertAllConvert([
            'a link' => ['<a href="https://x.test/a">x</a>', '<p>' . $this->a('https://x.test/a', 'x') . '</p>'],
            'a title' => ['<a href="https://x.test/a" title="T">x</a>', '<p>' . $this->a('https://x.test/a', 'x', 'T') . '</p>'],
            'an entity in the address is read first' => ['<a href="https://x.test/?a=1&amp;b=2">x</a>', '<p>' . $this->a('https://x.test/?a=1&b=2', 'x') . '</p>'],
            'javascript keeps only the text' => ['<a href="javascript:alert(1)">x</a>', '<p>x</p>'],
            'javascript hidden with an entity' => ['<a href="jav&#x09;ascript:alert(1)">x</a>', '<p>x</p>'],
            'javascript written with a numeric entity' => ['<a href="&#106;avascript:alert(1)">x</a>', '<p>x</p>'],
            'a data address' => ['<a href="data:text/html,x">x</a>', '<p>x</p>'],
            'a relative address' => ['<a href="docs/page">x</a>', '<p>x</p>'],
            'no address' => ['<a name="top">x</a>', '<p>x</p>'],
            'a target and a handler are dropped' => ['<a href="https://x.test" target="_top" onclick="alert(1)">x</a>', '<p>' . $this->a('https://x.test', 'x') . '</p>'],
            'a link inside a link' => ['<a href="https://x.test/a"><a href="https://x.test/b">x</a></a>', '<p>' . $this->a('https://x.test/a', 'x') . '</p>'],
            'a markdown link inside a link stays as text' => ['<a href="https://x.test/a">[x](https://x.test/b)</a>', '<p>' . $this->a('https://x.test/a', '[x](https://x.test/b)') . '</p>'],
            'bold inside a link' => ['<a href="https://x.test/a"><b>x</b></a>', '<p>' . $this->a('https://x.test/a', '<strong>x</strong>') . '</p>'],
        ], self::SANITIZE);
    }

    public function testSanitizedImages(): void
    {
        $options = ['html' => 'sanitize', 'imageHosts' => ['x.test']];
        $src = 'https://x.test/a.png';

        $this->assertAllConvert([
            'an image' => ["<img src=\"$src\" alt=\"A\">", '<p>' . $this->img($src, 'A') . '</p>'],
            'a size' => ["<img src=\"$src\" alt=\"A\" width=\"100\" height=\"50\">", '<p><img src="' . $src . '" alt="A" width="100" height="50" loading="lazy" referrerpolicy="no-referrer"></p>'],
            'a size with units is dropped' => ["<img src=\"$src\" alt=\"A\" width=\"100px\" height=\"1;x\">", '<p>' . $this->img($src, 'A') . '</p>'],
            'a handler is dropped' => ["<img src=\"$src\" alt=\"A\" onerror=\"alert(1)\">", '<p>' . $this->img($src, 'A') . '</p>'],
            'a srcset is dropped' => ["<img src=\"$src\" alt=\"A\" srcset=\"javascript:alert(1)\">", '<p>' . $this->img($src, 'A') . '</p>'],
            'another host becomes a link' => ['<img src="https://y.test/a.png" alt="A">', '<p>' . $this->a('https://y.test/a.png', 'A') . '</p>'],
            'no address' => ['<img alt="A">', '<p>A</p>'],
            'javascript' => ['<img src="javascript:alert(1)" alt="A">', '<p>A</p>'],
        ], $options);

        $this->assertConverts('<p>' . $this->a($src, 'A') . '</p>', "<img src=\"$src\" alt=\"A\">", self::SANITIZE, 'No image is shown unless a host is allowed.');
    }

    public function testSanitizedBlocks(): void
    {
        $this->assertAllConvert([
            'details with a summary' => [
                "<details>\n<summary>More</summary>\n\nBody **text**\n\n</details>",
                "<details>\n<summary>More</summary>\n<p>Body <strong>text</strong></p>\n</details>",
            ],
            'details that start open' => ["<details open>\n\nx\n\n</details>", "<details open>\n<p>x</p>\n</details>"],
            'details with a handler' => ["<details open ontoggle=\"alert(1)\">\n\nx\n\n</details>", "<details open>\n<p>x</p>\n</details>"],
            'a summary on several lines' => ["<details>\n<summary>\nTitle\n</summary>\n\nx\n\n</details>", "<details>\n<summary>\n<p>Title</p>\n</summary>\n<p>x</p>\n</details>"],
            'a centered paragraph' => ['<p align="center">text</p>', '<p align="center">text</p>'],
            'an unknown alignment' => ['<p align="evil">text</p>', '<p>text</p>'],
            'a div that holds blocks' => ["<div align=\"right\">\n\n# Title\n\n- a\n\n</div>", "<div align=\"right\">\n<h1>Title</h1>\n<ul>\n<li>a</li>\n</ul>\n</div>"],
            'a paragraph tag that holds blocks is written as a div' => ["<p align=\"center\">\n\nx\n\n</p>", "<div align=\"center\">\n<p>x</p>\n</div>"],
            'nested details' => ["<details>\n\n<details>\n\nx\n\n</details>\n\n</details>", "<details>\n<details>\n<p>x</p>\n</details>\n</details>"],
            'an unclosed tag is left out' => ["<details>\n\nx", '<p>x</p>'],
            'a closing tag with no opening is left out' => ["x\n\n</details>\n\ny", "<p>x</p>\n<p>y</p>"],
            'a rule' => ["a\n\n<hr>\n\nb", "<p>a</p>\n<hr>\n<p>b</p>"],
            'a block tag can end a paragraph' => ["text\n<details>\n\nx\n\n</details>", "<p>text</p>\n<details>\n<p>x</p>\n</details>"],
            'a tag with text after it is an ordinary line' => ['<div>text', '<p>text</p>'],
            'a handler on a block tag' => ['<div onclick="alert(1)" style="x">text</div>', '<div>text</div>'],
        ], self::SANITIZE);
    }

    public function testBlockTagsInsideOtherBlocks(): void
    {
        $this->assertStringContainsString(
            "<details>\n<p>x</p>\n</details>",
            $this->html("- item\n\n  <details>\n\n  x\n\n  </details>", self::SANITIZE),
        );
        $this->assertStringContainsString(
            "<details>\n<p>x</p>\n</details>",
            $this->html("> <details>\n>\n> x\n>\n> </details>", self::SANITIZE),
        );
    }

    public function testHtmlInsideCodeIsNeverTouched(): void
    {
        foreach (['escape', 'strip', 'sanitize'] as $mode) {
            $this->assertAllConvert([
                'a code span' => ['`<b>x</b>`', '<p><code>&lt;b&gt;x&lt;/b&gt;</code></p>'],
                'a fence' => ["```html\n<details>\n<b>x</b>\n</details>\n```", "<pre><code class=\"language-html\">&lt;details&gt;\n&lt;b&gt;x&lt;/b&gt;\n&lt;/details&gt;\n</code></pre>"],
            ], ['html' => $mode]);
        }
    }

    public function testDeepAndLongTagsAreHandledQuickly(): void
    {
        $inputs = [
            'deeply nested tags' => str_repeat('<b>', 5000) . 'x' . str_repeat('</b>', 5000),
            'a very long tag' => '<b ' . str_repeat('a="b" ', 20000) . '>x</b>',
            'many tags that never close' => str_repeat('<b>x', 5000),
            'many nested blocks' => str_repeat("<details>\n\n", 500) . 'x',
            'many anchors' => str_repeat('<a href="https://x.test">x</a>', 2000),
        ];

        foreach (['strip', 'sanitize'] as $mode) {
            foreach ($inputs as $name => $input) {
                $start = microtime(true);
                $html = $this->html($input, ['html' => $mode, 'imageHosts' => ['*']]);
                $elapsed = microtime(true) - $start;

                $this->assertLessThan(5.0, $elapsed, $name . ' with ' . $mode . ' took ' . round($elapsed, 2) . ' seconds.');
                $this->assertSafe($html, $name . ' with ' . $mode);
            }
        }
    }
}
