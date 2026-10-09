<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

final class InlineTest extends MarkdownTestCase
{
    public function testEmphasisAndStrongEmphasis(): void
    {
        $this->assertAllConvert([
            'asterisks' => ['*a*', '<p><em>a</em></p>'],
            'underscores' => ['_a_', '<p><em>a</em></p>'],
            'strong asterisks' => ['**a**', '<p><strong>a</strong></p>'],
            'strong underscores' => ['__a__', '<p><strong>a</strong></p>'],
            'both' => ['***a***', '<p><em><strong>a</strong></em></p>'],
            'nested' => ['**a *b* c**', '<p><strong>a <em>b</em> c</strong></p>'],
            'inside a word with asterisks' => ['a*b*c', '<p>a<em>b</em>c</p>'],
            'not inside a word with underscores' => ['snake_case_name', '<p>snake_case_name</p>'],
            'unbalanced' => ['**a*', '<p>*<em>a</em></p>'],
            'spaces inside do not count' => ['2 * 3 * 4', '<p>2 * 3 * 4</p>'],
            'escaped' => ['\*a\*', '<p>*a*</p>'],
        ]);
    }

    public function testEmphasisStillMatchesAfterAnEarlierPairIsCollapsed(): void
    {
        // Each of these failed when the remembered search positions pointed at
        // tokens that had since moved.
        $this->assertAllConvert([
            'closer after a collapsed pair' => ['a _a*_*a__a* .', '<p>a <em>a*</em><em>a__a</em> .</p>'],
            'strong then a lone closer' => ['a **..a_**_*a_', '<p>a <strong>..a_</strong><em>*a</em></p>'],
            'mixed marks' => ['a *a_*_)a_*a.)', '<p>a <em>a_</em><em>)a</em>*a.)</p>'],
        ]);
    }

    public function testStrikethroughWithOneOrTwoTildes(): void
    {
        $this->assertAllConvert([
            'two tildes' => ['~~a~~', '<p><del>a</del></p>'],
            'one tilde' => ['~a~', '<p><del>a</del></p>'],
            'three tildes in the middle of a line stay text' => ['a ~~~ b', '<p>a ~~~ b</p>'],
            'inside other emphasis' => ['**~~a~~**', '<p><strong><del>a</del></strong></p>'],
            'not matched' => ['~~a', '<p>~~a</p>'],
        ]);
    }

    public function testCodeSpans(): void
    {
        $this->assertAllConvert([
            'plain' => ['`a`', '<p><code>a</code></p>'],
            'with a backtick inside' => ['``a`b``', '<p><code>a`b</code></p>'],
            'one space is trimmed from each side' => ['` a `', '<p><code>a</code></p>'],
            'more spaces are kept' => ['`  a  `', '<p><code> a </code></p>'],
            'html is escaped' => ['`<b>&</b>`', '<p><code>&lt;b&gt;&amp;&lt;/b&gt;</code></p>'],
            'emphasis inside is not parsed' => ['`*a*`', '<p><code>*a*</code></p>'],
            'a backslash is literal' => ['`a\`', '<p><code>a\</code></p>'],
            'unmatched' => ['`a', '<p>`a</p>'],
            'unmatched run of two' => ['``a`', '<p>``a`</p>'],
            'a web address inside is not linked' => ['`https://x.test`', '<p><code>https://x.test</code></p>'],
        ]);
    }

    /**
     * Tests that a backtick inside an address or a comment belongs to it and
     * can't open a code span.
     *
     * The parser reads text from left to right, so whatever starts first
     * decides what a backtick belongs to. Looking for code spans in the whole
     * text beforehand would pair such a backtick with one further on.
     */
    public function testABacktickSwallowedByAnAddressOrCommentDoesNotPairWithLaterOnes(): void
    {
        $this->assertAllConvert([
            'inside an address in angle brackets' => ['<https://x.test/`>x`y`z', '<p>' . $this->a('https://x.test/`', 'https://x.test/`') . 'x<code>y</code>z</p>'],
            'inside a comment' => ['<!-- ` -->a`b`c', '<p>a<code>b</code>c</p>'],
            'an address, then a span of its own' => ['<https://x.test/`> `code`', '<p>' . $this->a('https://x.test/`', 'https://x.test/`') . ' <code>code</code></p>'],
            'a span and then a lone backtick' => ['`a` and `b', '<p><code>a</code> and `b</p>'],
        ]);
    }

    public function testLinksAreFoundAroundBackticksThatBelongToOtherSyntax(): void
    {
        $this->assertAllConvert([
            'after addresses in angle brackets that each hold a backtick' => [
                '<https://x.test/`>see [the docs](https://u.test) and <https://y.test/`>',
                '<p>' . $this->a('https://x.test/`', 'https://x.test/`') . 'see ' . $this->a('https://u.test', 'the docs') . ' and ' . $this->a('https://y.test/`', 'https://y.test/`') . '</p>',
            ],
            'after a bare address with a stray backtick, with real code later' => [
                'See https://x.test/a` and [the docs](https://u.test) then `code`',
                '<p>See ' . $this->a('https://x.test/a`', 'https://x.test/a`') . ' and ' . $this->a('https://u.test', 'the docs') . ' then <code>code</code></p>',
            ],
            'a bracket inside real code in the text of a link' => [
                '[a `]` b](https://u.test)',
                '<p>' . $this->a('https://u.test', 'a <code>]</code> b') . '</p>',
            ],
            'real code with a bracket between two links' => [
                '[a](https://u.test) `x]` [b](https://v.test)',
                '<p>' . $this->a('https://u.test', 'a') . ' <code>x]</code> ' . $this->a('https://v.test', 'b') . '</p>',
            ],
        ]);
    }

    /**
     * Tests that the closing bracket decides whether a bracket becomes a link,
     * from what follows it.
     */
    public function testTheClosingBracketDecidesWhatIsALink(): void
    {
        $this->assertAllConvert([
            'brackets inside the text' => ['[a [b] c](https://u.test)', '<p>' . $this->a('https://u.test', 'a [b] c') . '</p>'],
            'a bracket with no address stays text' => ['[a] and [b](https://u.test) and ]', '<p>[a] and ' . $this->a('https://u.test', 'b') . ' and ]</p>'],
            'a closing bracket with nothing open' => ['a ] b', '<p>a ] b</p>'],
            'links side by side' => ['[a](https://u.test)[b](https://v.test)', '<p>' . $this->a('https://u.test', 'a') . $this->a('https://v.test', 'b') . '</p>'],
            'an unfinished reference' => ['[a][', '<p>[a][</p>'],
            'an escaped closing bracket does not close the text' => ['[a\](https://u.test)', '<p>[a](' . $this->a('https://u.test', 'https://u.test') . ')</p>'],
        ]);
    }

    public function testAnAddressInAngleBracketsIsReadBeforeABracketAfterIt(): void
    {
        $this->assertConverts(
            '<p>[foo' . $this->a('https://x.test/?search=](uri)', 'https://x.test/?search=](uri)') . '</p>',
            '[foo<https://x.test/?search=](uri)>',
        );
    }

    public function testACommentSwallowsTheBracketsInsideIt(): void
    {
        $this->assertConverts('<p>[a  b</p>', '[a <!-- ](https://u.test) --> b');
    }

    public function testALinkNeverHoldsAnotherLink(): void
    {
        $this->assertAllConvert([
            'an image that cannot be shown, inside a link' => ['[![build](https://github.com/o/r/b.svg)](https://ci.test/run)', '<p>' . $this->a('https://ci.test/run', 'build') . '</p>'],
            'the same inside a link that is not safe' => ['[![d](https://u.test/i.png)](javascript:x)', '<p>d</p>'],
        ]);

        $this->assertConverts(
            '<p>' . $this->a('https://x.test', 'd') . '</p>',
            '<a href="https://x.test">![d](https://u.test/i.png)</a>',
            ['html' => 'sanitize'],
            'An image that cannot be shown does not become a link inside a link written in HTML.',
        );
    }

    public function testABareAddressEndsAtTheBracketThatClosesTheOpenText(): void
    {
        $this->assertAllConvert([
            'inside text that is not a link' => ['[see https://x.test/a] and more', '<p>[see ' . $this->a('https://x.test/a', 'https://x.test/a') . '] and more</p>'],
            'brackets that balance inside the address stay' => [
                '[https://x.test/a[1]](https://u.test) https://x.test/b[2]',
                '<p>' . $this->a('https://u.test', 'https://x.test/a[1]') . ' ' . $this->a('https://x.test/b[2]', 'https://x.test/b[2]') . '</p>',
            ],
        ]);
    }

    /**
     * Matches the CommonMark reference implementation. The last star has a mark
     * on each side, so it can open as well as close, and the rule of three then
     * stops it from closing the two stars before it.
     */
    public function testEmphasisAtTheEndOfLinkTextLooksAtTheRealNeighbours(): void
    {
        $this->assertConverts('<p>' . $this->a('https://u.test', '**b]*') . '</p>', '[**b\]*](https://u.test)');
    }

    public function testOnlyTheFirst200BracketsInABlockCanBecomeLinks(): void
    {
        $html = $this->html(str_repeat('[a](https://x.test) ', 250));

        $this->assertSame(200, substr_count($html, '>a</a>'));
        $this->assertSafe($html);
    }

    public function testBackslashEscapes(): void
    {
        $this->assertAllConvert([
            'html characters' => ['\<b\>', '<p>&lt;b&gt;</p>'],
            'a backslash before a letter stays' => ['\a', '<p>\a</p>'],
            'an escaped backslash' => ['a\\\\b', '<p>a\b</p>'],
            'escaped brackets' => ['\[a\](https://x.test)', '<p>[a](' . $this->a('https://x.test', 'https://x.test') . ')</p>'],
        ]);
    }

    public function testLinks(): void
    {
        $this->assertAllConvert([
            'plain' => ['[a](https://x.test)', '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'with a title' => ['[a](https://x.test "T")', '<p>' . $this->a('https://x.test', 'a', 'T') . '</p>'],
            'with a single quoted title' => ["[a](https://x.test 'T')", '<p>' . $this->a('https://x.test', 'a', 'T') . '</p>'],
            'emphasis in the text' => ['[**a**](https://x.test)', '<p>' . $this->a('https://x.test', '<strong>a</strong>') . '</p>'],
            'parentheses in the address' => ['[a](https://x.test/a_(b))', '<p>' . $this->a('https://x.test/a_(b)', 'a') . '</p>'],
            'an address in angle brackets' => ['[a](<https://x.test/a>)', '<p>' . $this->a('https://x.test/a', 'a') . '</p>'],
            'spaces around the address' => ['[a](  https://x.test  )', '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'a mail address' => ['[a](mailto:me@x.test)', '<p>' . $this->a('mailto:me@x.test', 'a') . '</p>'],
            'an ampersand in the address is escaped' => ['[a](https://x.test/?a=1&b=2)', '<p>' . $this->a('https://x.test/?a=1&b=2', 'a') . '</p>'],
            'brackets without an address stay text' => ['[a]', '<p>[a]</p>'],
            'brackets with something else after them' => ['[a] (https://x.test)', '<p>[a] (' . $this->a('https://x.test', 'https://x.test') . ')</p>'],
        ]);
    }

    public function testLinksWithAddressesThatAreNotSafeShowOnlyTheirText(): void
    {
        $this->assertAllConvert([
            'javascript' => ['[a](javascript:alert(1))', '<p>a</p>'],
            'javascript in mixed case' => ['[a](JaVaScRiPt:alert(1))', '<p>a</p>'],
            'data' => ['[a](data:text/html;base64,AAAA)', '<p>a</p>'],
            'vbscript' => ['[a](vbscript:x)', '<p>a</p>'],
            'file' => ['[a](file:///etc/passwd)', '<p>a</p>'],
            'a relative address' => ['[a](/docs/page)', '<p>a</p>'],
            'an anchor' => ['[a](#section)', '<p>a</p>'],
            'a protocol relative address' => ['[a](//evil.test/x)', '<p>a</p>'],
            'an empty address' => ['[a]()', '<p>a</p>'],
            'a space inside an angle bracketed address' => ['[a](<https://x.test/a b>)', '<p>a</p>'],
        ]);
    }

    public function testCodeSpansWinOverLinks(): void
    {
        $this->assertConverts(
            '<p>[not a <code>link](https://x.test/foo</code>)</p>',
            '[not a `link](https://x.test/foo`)',
        );
    }

    public function testALinkCannotContainALink(): void
    {
        $this->assertConverts(
            '<p>[a ' . $this->a('https://x.test/b', 'b') . ' c](' . $this->a('https://x.test/a', 'https://x.test/a') . ')</p>',
            '[a [b](https://x.test/b) c](https://x.test/a)',
        );
    }

    public function testBareAddressesInsideLinkTextAreNotLinkedAgain(): void
    {
        $this->assertConverts(
            '<p>' . $this->a('https://x.test/b', 'https://x.test/a') . '</p>',
            '[https://x.test/a](https://x.test/b)',
        );
        $this->assertConverts(
            '<p>' . $this->a('https://x.test/b', '&lt;https://x.test/a&gt;') . '</p>',
            '[<https://x.test/a>](https://x.test/b)',
        );
    }

    public function testImages(): void
    {
        $github = 'https://github.com/x/y.png';

        $this->assertAllConvert([
            'plain' => ["![a]($github)", '<p>' . $this->img($github, 'a') . '</p>'],
            'with a title' => ["![a]($github \"T\")", '<p>' . $this->img($github, 'a', 'T') . '</p>'],
            'a subdomain of githubusercontent.com' => ['![a](https://user-images.githubusercontent.com/1/a.png)', '<p>' . $this->img('https://user-images.githubusercontent.com/1/a.png', 'a') . '</p>'],
            'emphasis in the description becomes plain text' => ["![*a* b]($github)", '<p>' . $this->img($github, 'a b') . '</p>'],
            'quotes in the description are escaped' => ["![a\"b]($github)", '<p>' . $this->img($github, 'a"b') . '</p>'],
            'a badge inside a link' => ["[![a]($github)](https://x.test/c)", '<p>' . $this->a('https://x.test/c', $this->img($github, 'a')) . '</p>'],
        ], ['imageHosts' => self::GITHUB_IMAGES]);
    }

    public function testImagesAreOffByDefault(): void
    {
        $this->assertAllConvert([
            'a GitHub image' => ['![a](https://github.com/x/y.png)', '<p>' . $this->a('https://github.com/x/y.png', 'a') . '</p>'],
            'any other image' => ['![a](https://x.test/y.png)', '<p>' . $this->a('https://x.test/y.png', 'a') . '</p>'],
            'an image with no description' => ['![](https://x.test/y.png)', '<p>' . $this->a('https://x.test/y.png', 'image') . '</p>'],
            'an unsafe address' => ['![a](javascript:alert(1))', '<p>a</p>'],
        ]);
    }

    public function testImagesFromOtherHostsBecomeLinks(): void
    {
        $this->assertAllConvert([
            'another host' => ['![a](https://x.test/y.png)', '<p>' . $this->a('https://x.test/y.png', 'a') . '</p>'],
            'no description' => ['![](https://x.test/y.png)', '<p>' . $this->a('https://x.test/y.png', 'image') . '</p>'],
            'the bare githubusercontent.com domain' => ['![a](https://githubusercontent.com/y.png)', '<p>' . $this->a('https://githubusercontent.com/y.png', 'a') . '</p>'],
            'a look alike host' => ['![a](https://evilgithubusercontent.com/y.png)', '<p>' . $this->a('https://evilgithubusercontent.com/y.png', 'a') . '</p>'],
            'a host that merely starts with an allowed one' => ['![a](https://github.com.evil.test/y.png)', '<p>' . $this->a('https://github.com.evil.test/y.png', 'a') . '</p>'],
            'plain http' => ['![a](http://github.com/y.png)', '<p>' . $this->a('http://github.com/y.png', 'a') . '</p>'],
            'an unsafe address' => ['![a](javascript:alert(1))', '<p>a</p>'],
        ], ['imageHosts' => self::GITHUB_IMAGES]);
    }

    public function testImageHostsCanBeChanged(): void
    {
        $this->assertConverts('<p>' . $this->img('https://x.test/y.png', 'a') . '</p>', '![a](https://x.test/y.png)', ['imageHosts' => ['*']]);
        $this->assertConverts('<p>' . $this->img('https://cdn.x.test/y.png', 'a') . '</p>', '![a](https://cdn.x.test/y.png)', ['imageHosts' => ['*.x.test']]);
        $this->assertConverts('<p>' . $this->a('https://x.test/y.png', 'a') . '</p>', '![a](https://x.test/y.png)', ['imageHosts' => ['*.x.test']]);
        $this->assertConverts('<p>' . $this->a('https://github.com/y.png', 'a') . '</p>', '![a](https://github.com/y.png)', ['imageHosts' => []]);
    }

    public function testAutolinksInAngleBrackets(): void
    {
        $this->assertAllConvert([
            'a web address' => ['<https://x.test/a>', '<p>' . $this->a('https://x.test/a', 'https://x.test/a') . '</p>'],
            'a mail address' => ['<me@x.test>', '<p>' . $this->a('mailto:me@x.test', 'me@x.test') . '</p>'],
            'javascript is shown as text' => ['<javascript:alert(1)>', '<p>&lt;javascript:alert(1)&gt;</p>'],
            'ftp is shown as text' => ['<ftp://x.test>', '<p>&lt;ftp://x.test&gt;</p>'],
        ]);
    }

    public function testBareWebAddresses(): void
    {
        $this->assertAllConvert([
            'on their own' => ['https://x.test/a', '<p>' . $this->a('https://x.test/a', 'https://x.test/a') . '</p>'],
            'at the end of a sentence' => ['See https://x.test/a.', '<p>See ' . $this->a('https://x.test/a', 'https://x.test/a') . '.</p>'],
            'in parentheses' => ['(https://x.test/a)', '<p>(' . $this->a('https://x.test/a', 'https://x.test/a') . ')</p>'],
            'with parentheses of their own' => ['https://x.test/a_(b)', '<p>' . $this->a('https://x.test/a_(b)', 'https://x.test/a_(b)') . '</p>'],
            'inside strong' => ['**https://x.test/a**', '<p><strong>' . $this->a('https://x.test/a', 'https://x.test/a') . '</strong></p>'],
            'with a query' => ['https://x.test/a?b=1&c=2', '<p>' . $this->a('https://x.test/a?b=1&c=2', 'https://x.test/a?b=1&amp;c=2') . '</p>'],
            'in the middle of a word' => ['xhttps://x.test', '<p>xhttps://x.test</p>'],
            'ftp is not linked' => ['ftp://x.test/a', '<p>ftp://x.test/a</p>'],
            'www is not linked' => ['www.x.test', '<p>www.x.test</p>'],
            'a bare mail address is not linked' => ['me@x.test', '<p>me@x.test</p>'],
        ]);
    }

    public function testLineBreaks(): void
    {
        $this->assertAllConvert([
            'a single line break' => ["a\nb", '<p>a<br>b</p>'],
            'two spaces make a hard break' => ["a  \nb", '<p>a<br>b</p>'],
            'a backslash makes a hard break' => ["a\\\nb", '<p>a<br>b</p>'],
            'one trailing space is dropped' => ["a \nb", '<p>a<br>b</p>'],
            'spaces at the start of the next line are dropped' => ["a\n   b", '<p>a<br>b</p>'],
            'spaces at the end of a paragraph are dropped' => ['a  ', '<p>a</p>'],
        ]);
    }

    public function testSoftBreakSetting(): void
    {
        $this->assertConverts("<p>a b</p>", "a\nb", ['softBreak' => 'space']);
        $this->assertConverts("<p>a\nb</p>", "a\nb", ['softBreak' => 'newline']);
        $this->assertConverts("<p>a<br>\nb</p>", "a  \nb", ['softBreak' => 'newline']);
        $this->assertConverts('<p>a<br>b</p>', "a  \nb", ['softBreak' => 'space']);
    }

    public function testCommentsAreDropped(): void
    {
        $this->assertAllConvert([
            'inside a line' => ['a <!-- hidden --> b', '<p>a  b</p>'],
            'across lines' => ["a <!-- x\ny --> b", '<p>a  b</p>'],
            'never closed' => ['a <!-- b', '<p>a &lt;!-- b</p>'],
            'empty' => ['a<!---->b', '<p>ab</p>'],
            'ended by the first closing mark' => ['a <!-- b --> c --> d', '<p>a  c --&gt; d</p>'],
            'several in one line' => ['x <!-- a --> y <!-- b --> z', '<p>x  y  z</p>'],
            'a start inside a comment is part of it' => ['a<!--b<!--c-->d', '<p>ad</p>'],
            'one that is never closed does not hide a closed one before it' => ['<b>a</b> <!-- c --> d <!-- e', '<p>&lt;b&gt;a&lt;/b&gt;  d &lt;!-- e</p>'],
        ]);
    }

    public function testRawHtmlIsShownAsText(): void
    {
        $this->assertAllConvert([
            'a tag' => ['<b>x</b>', '<p>&lt;b&gt;x&lt;/b&gt;</p>'],
            'a script' => ['<script>alert(1)</script>', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
            'an image with a handler' => ['<img src=x onerror=alert(1)>', '<p>&lt;img src=x onerror=alert(1)&gt;</p>'],
            'an entity is not interpreted' => ['&lt;b&gt;', '<p>&amp;lt;b&amp;gt;</p>'],
        ]);
    }

    public function testSpecialCharactersAreEscaped(): void
    {
        $this->assertConverts('<p>a &amp; b &quot;c&quot; \'d\' &lt; &gt;</p>', 'a & b "c" \'d\' < >');
    }

    public function testNonAsciiText(): void
    {
        $this->assertAllConvert([
            'cjk' => ['**日本語**', '<p><strong>日本語</strong></p>'],
            'cjk next to letters' => ['日本語**テスト**', '<p>日本語<strong>テスト</strong></p>'],
            'emoji' => ['*🎉* done', '<p><em>🎉</em> done</p>'],
            'accents' => ['_café_', '<p><em>café</em></p>'],
        ]);
    }
}
