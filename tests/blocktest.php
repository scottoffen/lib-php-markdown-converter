<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

final class BlockTest extends MarkdownTestCase
{
    public function testParagraphs(): void
    {
        $this->assertAllConvert([
            'two paragraphs' => ["a\n\nb", "<p>a</p>\n<p>b</p>"],
            'many blank lines' => ["a\n\n\n\nb", "<p>a</p>\n<p>b</p>"],
            'lines that only hold spaces' => ["a\n   \nb", "<p>a</p>\n<p>b</p>"],
            'leading spaces are dropped' => ["  a\n  b", '<p>a<br>b</p>'],
            'empty input' => ['', ''],
            'only blank lines' => ["\n\n  \n", ''],
        ]);
    }

    public function testHeadings(): void
    {
        $this->assertAllConvert([
            'level 1' => ['# a', '<h1>a</h1>'],
            'level 6' => ['###### a', '<h6>a</h6>'],
            'seven hashes is a paragraph' => ['####### a', '<p>####### a</p>'],
            'no space is a paragraph' => ['#hashtag', '<p>#hashtag</p>'],
            'closing hashes are dropped' => ['## a ##', '<h2>a</h2>'],
            'a hash that is part of the text stays' => ['# a #b', '<h1>a #b</h1>'],
            'empty' => ['#', '<h1></h1>'],
            'inline markup' => ['## **a** `b`', '<h2><strong>a</strong> <code>b</code></h2>'],
            'up to three spaces of indentation' => ['   # a', '<h1>a</h1>'],
            'between paragraphs' => ["a\n# b\nc", "<p>a</p>\n<h1>b</h1>\n<p>c</p>"],
        ]);
    }

    public function testHorizontalRules(): void
    {
        $this->assertAllConvert([
            'dashes' => ['---', '<hr>'],
            'stars' => ['***', '<hr>'],
            'underscores' => ['___', '<hr>'],
            'spaced out' => ['- - -', '<hr>'],
            'many' => ['----------', '<hr>'],
            'too short' => ['--', '<p>--</p>'],
            'between paragraphs' => ["a\n\n---\n\nb", "<p>a</p>\n<hr>\n<p>b</p>"],
        ]);
    }

    public function testFencedCodeBlocks(): void
    {
        $this->assertAllConvert([
            'plain' => ["```\na < b\n```", "<pre><code>a &lt; b\n</code></pre>"],
            'with a language' => ["```php\n<?php\n```", "<pre><code class=\"language-php\">&lt;?php\n</code></pre>"],
            'with tildes' => ["~~~\ncode\n~~~", "<pre><code>code\n</code></pre>"],
            'never closed' => ["```\na\nb", "<pre><code>a\nb\n</code></pre>"],
            'closed by a longer fence' => ["```\na\n`````", "<pre><code>a\n</code></pre>"],
            'markdown inside is not read' => ["```\n**a** [b](https://x.test)\n```", "<pre><code>**a** [b](https://x.test)\n</code></pre>"],
            'blank lines inside are kept' => ["```\na\n\nb\n```", "<pre><code>a\n\nb\n</code></pre>"],
            'empty' => ["```\n```", '<pre><code></code></pre>'],
            'a language with more words after it' => ["``` js title\ncode\n```", "<pre><code class=\"language-js\">code\n</code></pre>"],
            'a language that could break out of the attribute' => ["```js\" onclick=\"x\ncode\n```", "<pre><code>code\n</code></pre>"],
            'a closing fence of another kind does not close' => ["```\na\n~~~\nb\n```", "<pre><code>a\n~~~\nb\n</code></pre>"],
            'ends the paragraph above' => ["a\n```\nb\n```", "<p>a</p>\n<pre><code>b\n</code></pre>"],
        ]);
    }

    public function testBlockQuotes(): void
    {
        $this->assertAllConvert([
            'one line' => ['> a', "<blockquote>\n<p>a</p>\n</blockquote>"],
            'no space after the marker' => ['>a', "<blockquote>\n<p>a</p>\n</blockquote>"],
            'two lines' => ["> a\n> b", "<blockquote>\n<p>a<br>b</p>\n</blockquote>"],
            'a lazy line' => ["> a\nb", "<blockquote>\n<p>a<br>b</p>\n</blockquote>"],
            'nested' => ['> > a', "<blockquote>\n<blockquote>\n<p>a</p>\n</blockquote>\n</blockquote>"],
            'with a heading and a list' => ["> # a\n> - b", "<blockquote>\n<h1>a</h1>\n<ul>\n<li>b</li>\n</ul>\n</blockquote>"],
            'with a code block' => ["> ```\n> x\n> ```", "<blockquote>\n<pre><code>x\n</code></pre>\n</blockquote>"],
            'two quotes split by a blank line' => ["> a\n\n> b", "<blockquote>\n<p>a</p>\n</blockquote>\n<blockquote>\n<p>b</p>\n</blockquote>"],
            'a paragraph after a blank line' => ["> a\n\nb", "<blockquote>\n<p>a</p>\n</blockquote>\n<p>b</p>"],
            'empty' => ['>', "<blockquote>\n</blockquote>"],
            'an open code fence is not continued lazily' => ["> ```\nfoo", "<blockquote>\n<pre><code></code></pre>\n</blockquote>\n<p>foo</p>"],
        ]);
    }

    public function testOnlyParagraphTextCanContinueLazilyInAQuote(): void
    {
        $this->assertAllConvert([
            'after a heading' => ["> # a\nb", "<blockquote>\n<h1>a</h1>\n</blockquote>\n<p>b</p>"],
            'after a rule' => ["> ---\nb", "<blockquote>\n<hr>\n</blockquote>\n<p>b</p>"],
            'after a closed fence' => ["> ```\n> a\n> ```\nb", "<blockquote>\n<pre><code>a\n</code></pre>\n</blockquote>\n<p>b</p>"],
            'after a comment' => ["> <!-- a -->\nb", "<blockquote>\n</blockquote>\n<p>b</p>"],
            'after an empty list item' => ["> -\nb", "<blockquote>\n<ul>\n<li></li>\n</ul>\n</blockquote>\n<p>b</p>"],
            'after a heading inside a list' => ["> - # a\nb", "<blockquote>\n<ul>\n<li>\n<h1>a</h1>\n</li>\n</ul>\n</blockquote>\n<p>b</p>"],
            'after paragraph text' => ["> a\nb", "<blockquote>\n<p>a<br>b</p>\n</blockquote>"],
            'after paragraph text in a nested quote' => ["> > a\nb", "<blockquote>\n<blockquote>\n<p>a<br>b</p>\n</blockquote>\n</blockquote>"],
            'after paragraph text in a list in a quote' => ["> - a\nb", "<blockquote>\n<ul>\n<li>a<br>b</li>\n</ul>\n</blockquote>"],
            'a lazy line that goes on' => ["> a\nb\nc", "<blockquote>\n<p>a<br>b<br>c</p>\n</blockquote>"],
        ]);
    }

    public function testOnlyParagraphTextCanContinueLazilyInAListItem(): void
    {
        $this->assertAllConvert([
            'after a heading' => ["- # a\nb", "<ul>\n<li>\n<h1>a</h1>\n</li>\n</ul>\n<p>b</p>"],
            'after paragraph text' => ["- a\nb", "<ul>\n<li>a<br>b</li>\n</ul>"],
            'after paragraph text in a nested list' => ["- - a\nb", "<ul>\n<li>\n<ul>\n<li>a<br>b</li>\n</ul>\n</li>\n</ul>"],
            'after paragraph text in a quote' => ["- > a\nb", "<ul>\n<li>\n<blockquote>\n<p>a<br>b</p>\n</blockquote>\n</li>\n</ul>"],
        ]);
    }

    public function testASettingThatAddsABlockAlsoEndsTheParagraphBeforeALazyLine(): void
    {
        $this->assertConverts("<blockquote>\n<h1>a</h1>\n</blockquote>\n<p>b</p>", "> a\n> ===\nb", ['setextHeadings' => true], 'a setext underline');
        $this->assertConverts("<blockquote>\n</blockquote>\n<p>b</p>", "> [a]: https://x.test\nb", ['referenceLinks' => true], 'a reference definition');
        $this->assertConverts("<blockquote>\n</blockquote>\n<p>b</p>", "> <div>\nb", ['html' => 'sanitize'], 'a block tag that was removed');
    }

    public function testBulletedLists(): void
    {
        $this->assertAllConvert([
            'dashes' => ["- a\n- b", "<ul>\n<li>a</li>\n<li>b</li>\n</ul>"],
            'stars' => ["* a\n* b", "<ul>\n<li>a</li>\n<li>b</li>\n</ul>"],
            'pluses' => ["+ a\n+ b", "<ul>\n<li>a</li>\n<li>b</li>\n</ul>"],
            'a new marker starts a new list' => ["- a\n* b", "<ul>\n<li>a</li>\n</ul>\n<ul>\n<li>b</li>\n</ul>"],
            'nested' => ["- a\n  - b\n- c", "<ul>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n<li>c</li>\n</ul>"],
            'nested with four spaces' => ["- a\n    - b", "<ul>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ul>"],
            'a lazy line' => ["- a\nb", "<ul>\n<li>a<br>b</li>\n</ul>"],
            'an empty item' => ['-', "<ul>\n<li></li>\n</ul>"],
            'no space after the marker is a paragraph' => ['-a', '<p>-a</p>'],
            'a checkbox is left as text' => ['- [ ] a', "<ul>\n<li>[ ] a</li>\n</ul>"],
            'interrupts a paragraph' => ["a\n- b", "<p>a</p>\n<ul>\n<li>b</li>\n</ul>"],
            'a paragraph after the list' => ["- a\n\nb", "<ul>\n<li>a</li>\n</ul>\n<p>b</p>"],
            'a heading ends the list' => ["- a\n# b", "<ul>\n<li>a</li>\n</ul>\n<h1>b</h1>"],
            'bold text is not a list' => ['**a** b', '<p><strong>a</strong> b</p>'],
        ]);
    }

    public function testNumberedLists(): void
    {
        $this->assertAllConvert([
            'a plain list' => ["1. a\n2. b", "<ol>\n<li>a</li>\n<li>b</li>\n</ol>"],
            'with a closing parenthesis' => ["1) a\n2) b", "<ol>\n<li>a</li>\n<li>b</li>\n</ol>"],
            'starting at another number' => ["3. a\n4. b", "<ol start=\"3\">\n<li>a</li>\n<li>b</li>\n</ol>"],
            'a different delimiter starts a new list' => ["1. a\n1) b", "<ol>\n<li>a</li>\n</ol>\n<ol>\n<li>b</li>\n</ol>"],
            'nested under a bullet' => ["1. a\n   - b", "<ol>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ol>"],
            'only a list starting at 1 interrupts a paragraph' => ["a\n2. b", '<p>a<br>2. b</p>'],
            'a list starting at 1 interrupts a paragraph' => ["a\n1. b", "<p>a</p>\n<ol>\n<li>b</li>\n</ol>"],
            'ten digits is not a list' => ['1234567890. a', '<p>1234567890. a</p>'],
        ]);
    }

    public function testTightAndLooseLists(): void
    {
        $this->assertAllConvert([
            'a blank line between items makes the list loose' => ["- a\n\n- b", "<ul>\n<li>\n<p>a</p>\n</li>\n<li>\n<p>b</p>\n</li>\n</ul>"],
            'two paragraphs in one item make it loose' => ["- a\n\n  b", "<ul>\n<li>\n<p>a</p>\n<p>b</p>\n</li>\n</ul>"],
            'a blank line inside a nested list does not loosen the outer list' => ["- a\n  - b\n\n  - c\n- d", "<ul>\n<li>a\n<ul>\n<li>\n<p>b</p>\n</li>\n<li>\n<p>c</p>\n</li>\n</ul>\n</li>\n<li>d</li>\n</ul>"],
            'a code block in a tight item' => ["- a\n  ```\n  x\n  ```", "<ul>\n<li>a\n<pre><code>x\n</code></pre>\n</li>\n</ul>"],
            'an empty item followed by a blank line is finished' => ["-\n\n  foo", "<ul>\n<li></li>\n</ul>\n<p>foo</p>"],
        ]);
    }

    public function testCommentsBetweenBlocks(): void
    {
        $this->assertAllConvert([
            'a comment on its own' => ['<!-- hidden -->', ''],
            'between paragraphs' => ["a\n\n<!-- hidden -->\n\nb", "<p>a</p>\n<p>b</p>"],
            'over several lines' => ["a\n\n<!--\nhidden\n-->\n\nb", "<p>a</p>\n<p>b</p>"],
            'text after the comment on its last line is kept' => ["<!-- c --> text", '<p>text</p>'],
            'never closed' => ["a\n\n<!-- hidden\nb", '<p>a</p>'],
            'a comment ends a paragraph' => ["a\n<!-- c -->\nb", "<p>a</p>\n<p>b</p>"],
        ]);
    }

    public function testSyntaxThatIsNotSupportedIsShownAsText(): void
    {
        $this->assertAllConvert([
            'indented code is a paragraph' => ['    code', '<p>code</p>'],
            'an equals underline is text' => ["Title\n===", '<p>Title<br>===</p>'],
            'a dash underline is a rule, not a heading' => ["Title\n---", "<p>Title</p>\n<hr>"],
            'a link definition is text' => ["[a]: https://x.test\n\n[a]", "<p>[a]: " . $this->a('https://x.test', 'https://x.test') . "</p>\n<p>[a]</p>"],
            'html blocks are escaped' => ["<details>\n<summary>S</summary>\n</details>", '<p>&lt;details&gt;<br>&lt;summary&gt;S&lt;/summary&gt;<br>&lt;/details&gt;</p>'],
            'a footnote is text' => ['a[^1]', '<p>a[^1]</p>'],
        ]);
    }

    public function testNestingIsLimited(): void
    {
        $deep = str_repeat('> ', 30) . 'a';
        $html = $this->html($deep, ['maxDepth' => 3]);

        $this->assertSame(4, substr_count($html, '<blockquote>'), 'Only the first four levels are quotes.');
        $this->assertStringContainsString('&gt; &gt;', $html, 'The rest is shown as text.');
        $this->assertSafe($html);
    }
}
