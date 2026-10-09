<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use InvalidArgumentException;
use ScottOffen\MarkdownConverter\MarkdownConverter;
use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Tests the settings that turn on syntax shown as plain text by default, and
 * the settings that decide which addresses can be linked.
 */
final class SettingsTest extends MarkdownTestCase
{
    // ---- relative links ----

    public function testRelativeLinksAreNotLinkedWithoutABaseAddress(): void
    {
        $this->assertAllConvert([
            'a path' => ['[a](docs/page)', '<p>a</p>'],
            'a path from the root' => ['[a](/docs/page)', '<p>a</p>'],
            'an anchor' => ['[a](#top)', '<p>a</p>'],
            'a protocol relative address' => ['[a](//evil.test/x)', '<p>a</p>'],
        ]);
    }

    public function testRelativeLinksAreJoinedToTheBaseAddress(): void
    {
        $base = 'https://x.test/help';

        $this->assertAllConvert([
            'a path' => ['[a](docs/page)', '<p>' . $this->a("$base/docs/page", 'a') . '</p>'],
            'a path from the root stays inside the base' => ['[a](/docs/page)', '<p>' . $this->a("$base/docs/page", 'a') . '</p>'],
            'a trailing slash is kept' => ['[a](docs/)', '<p>' . $this->a("$base/docs/", 'a') . '</p>'],
            'a query and a fragment' => ['[a](docs?x=1&y=2#part)', '<p>' . $this->a("$base/docs?x=1&y=2#part", 'a') . '</p>'],
            'dot segments' => ['[a](./a/./b/../c)', '<p>' . $this->a("$base/a/c", 'a') . '</p>'],
            'going up cannot leave the base' => ['[a](../../../x)', '<p>' . $this->a("$base/x", 'a') . '</p>'],
            'going up written with percent signs cannot leave the base' => ['[a](%2e%2e/%2E%2e/x)', '<p>' . $this->a("$base/x", 'a') . '</p>'],
            'the base itself' => ['[a](.)', '<p>' . $this->a($base, 'a') . '</p>'],
            'a full address is not changed' => ['[a](https://y.test/z)', '<p>' . $this->a('https://y.test/z', 'a') . '</p>'],
            'an anchor is not linked' => ['[a](#top)', '<p>a</p>'],
            'a protocol relative address is not linked' => ['[a](//evil.test/x)', '<p>a</p>'],
            'a backslash is refused' => ['[a](docs\\..\\x)', '<p>a</p>'],
        ], ['baseUrl' => $base]);

        $this->assertConverts('<p>' . $this->a("$base/docs", 'a') . '</p>', '[a](docs)', ['baseUrl' => "$base/"], 'A slash at the end of the base makes no difference.');
    }

    public function testRelativeImagesAreJoinedToTheBaseAddressAndCheckedAgainstTheHosts(): void
    {
        $this->assertConverts(
            '<p>' . $this->img('https://x.test/help/a.png', 'a') . '</p>',
            '![a](a.png)',
            ['baseUrl' => 'https://x.test/help', 'imageHosts' => ['x.test']],
        );
        $this->assertConverts(
            '<p>' . $this->a('https://x.test/help/a.png', 'a') . '</p>',
            '![a](a.png)',
            ['baseUrl' => 'https://x.test/help', 'imageHosts' => ['cdn.test']],
            'A relative image still has to come from an allowed host.',
        );
        $this->assertConverts('<p>a</p>', '![a](a.png)', ['imageHosts' => ['*']], 'With no base address a relative image cannot be loaded.');
    }

    public function testTheBaseAddressIsChecked(): void
    {
        foreach (['javascript:alert(1)', 'ftp://x.test', 'x.test', '//x.test', 'https://', 'https://u:p@x.test', 'https://x.test/?q=1', 'https://x.test/#f', "https://x.test/a b", 'https://x.test\\a'] as $base) {
            try {
                new MarkdownConverter(baseUrl: $base);
                $this->fail('The base address ' . json_encode($base) . ' should be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- link schemes ----

    public function testOnlyWebAndMailAddressesAreLinkedByDefault(): void
    {
        $this->assertAllConvert([
            'ftp' => ['[a](ftp://x.test/f)', '<p>a</p>'],
            'tel' => ['[a](tel:+15551234567)', '<p>a</p>'],
            'a bare ftp address' => ['ftp://x.test/f', '<p>ftp://x.test/f</p>'],
        ]);
    }

    public function testMoreSchemesCanBeAllowed(): void
    {
        $schemes = ['http', 'https', 'mailto', 'ftp', 'tel'];

        $html = $this->html("[a](ftp://x.test/f) [b](tel:+15551234567) [c](https://x.test) ftp://x.test/g\n\n[d](ftp://) [e](ftp:) [f](tel:)", ['linkSchemes' => $schemes]);

        $this->assertStringContainsString('<a href="ftp://x.test/f"', $html);
        $this->assertStringContainsString('<a href="tel:+15551234567"', $html);
        $this->assertStringContainsString('<a href="https://x.test"', $html);
        $this->assertStringContainsString('>ftp://x.test/g</a>', $html, 'A bare ftp address is linked once ftp is allowed.');
        $this->assertStringNotContainsString('href="ftp://"', $html, 'An address with no host is refused.');
        $this->assertStringNotContainsString('href="ftp:"', $html);
        $this->assertStringNotContainsString('href="tel:"', $html);
        $this->assertSafe($html, '', $schemes);
    }

    public function testSchemesCanBeRestricted(): void
    {
        $this->assertAllConvert([
            'http is refused' => ['[a](http://x.test)', '<p>a</p>'],
            'mail is refused' => ['[a](mailto:me@x.test)', '<p>a</p>'],
            'https works' => ['[a](https://x.test)', '<p>' . $this->a('https://x.test', 'a') . '</p>'],
        ], ['linkSchemes' => ['https']]);

        $this->assertConverts('<p>a</p>', '[a](https://x.test)', ['linkSchemes' => []], 'With no schemes allowed nothing is linked.');
    }

    public function testSchemesThatRunScriptOrReadFilesCanNeverBeAllowed(): void
    {
        foreach (['javascript', 'vbscript', 'livescript', 'data', 'file', 'blob', 'about', 'mhtml', 'ms-its', 'jar'] as $scheme) {
            try {
                new MarkdownConverter(linkSchemes: ['https', $scheme]);
                $this->fail('The scheme ' . $scheme . ' should be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach (['HTTPS', 'ht tp', '1http', '', 'a:b', 'ftp://'] as $scheme) {
            try {
                new MarkdownConverter(linkSchemes: [$scheme]);
                $this->fail('The scheme ' . json_encode($scheme) . ' should be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- character references ----

    public function testCharacterReferencesAreShownAsTextByDefault(): void
    {
        $this->assertConverts('<p>&amp;copy; &amp;#169;</p>', '&copy; &#169;');
    }

    public function testCharacterReferencesCanBeDecoded(): void
    {
        $this->assertAllConvert([
            'a named reference' => ['&copy; 2026', "<p>\u{A9} 2026</p>"],
            'a decimal reference' => ['&#169;', "<p>\u{A9}</p>"],
            'a hexadecimal reference' => ['&#x1F389; &#X41;', "<p>\u{1F389} A</p>"],
            'an ampersand' => ['a &amp; b', '<p>a &amp; b</p>'],
            'a decoded tag is still only text' => ['&lt;script&gt;alert(1)&lt;/script&gt;', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
            'a decoded quote is escaped' => ['&quot;x&quot; &#39;y&#39;', '<p>&quot;x&quot; \'y\'</p>'],
            'a name that does not exist' => ['&nosuchname;', '<p>&amp;nosuchname;</p>'],
            'a lone ampersand' => ['a & b &', '<p>a &amp; b &amp;</p>'],
            'a reference with no end' => ['&copy', '<p>&amp;copy</p>'],
            'null becomes the replacement character' => ['&#0;', "<p>\u{FFFD}</p>"],
            'a lone surrogate becomes the replacement character' => ['&#xD800;', "<p>\u{FFFD}</p>"],
            'past the end of Unicode becomes the replacement character' => ['&#1114112;', "<p>\u{FFFD}</p>"],
            'a form feed becomes the replacement character' => ['&#12; &#xC;', "<p>\u{FFFD} \u{FFFD}</p>"],
            'a control character becomes the replacement character' => ['&#x1; &#8;', "<p>\u{FFFD} \u{FFFD}</p>"],
            'a code span is left alone' => ['`&copy;`', '<p><code>&amp;copy;</code></p>'],
            'a fence is left alone' => ["```\n&copy;\n```", "<pre><code>&amp;copy;\n</code></pre>"],
            'a backslash stops it' => ['\\&copy;', '<p>&amp;copy;</p>'],
        ], ['decodeEntities' => true]);
    }

    // ---- bare addresses ----

    public function testWwwAddressesAreLinkedWhenAsked(): void
    {
        $this->assertConverts('<p>See www.x.test/a.</p>', 'See www.x.test/a.');

        $this->assertAllConvert([
            'a plain address' => ['See www.x.test/a.', '<p>See ' . $this->a('http://www.x.test/a', 'www.x.test/a') . '.</p>'],
            'at the start' => ['www.x.test', '<p>' . $this->a('http://www.x.test', 'www.x.test') . '</p>'],
            'in brackets' => ['(www.x.test/a)', '<p>(' . $this->a('http://www.x.test/a', 'www.x.test/a') . ')</p>'],
            'in the middle of a word' => ['awww.x.test', '<p>awww.x.test</p>'],
            'with no dot after the name' => ['www.test', '<p>www.test</p>'],
            'inside link text' => ['[www.x.test](https://y.test)', '<p>' . $this->a('https://y.test', 'www.x.test') . '</p>'],
            'in a code span' => ['`www.x.test`', '<p><code>www.x.test</code></p>'],
            'a script after it is escaped' => ['www.x.test/<script>alert(1)</script>', '<p>' . $this->a('http://www.x.test/', 'www.x.test/') . '&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
        ], ['autolinkWww' => true]);
    }

    public function testMailAddressesAreLinkedWhenAsked(): void
    {
        $this->assertConverts('<p>me@x.test</p>', 'me@x.test');

        $this->assertAllConvert([
            'a plain address' => ['Write me@x.test.', '<p>Write ' . $this->a('mailto:me@x.test', 'me@x.test') . '.</p>'],
            'with dots, plus, and dashes' => ['a.b+c@x-y.test', '<p>' . $this->a('mailto:a.b+c@x-y.test', 'a.b+c@x-y.test') . '</p>'],
            'a sub domain' => ['me@mail.x.test', '<p>' . $this->a('mailto:me@mail.x.test', 'me@mail.x.test') . '</p>'],
            'no dot in the domain' => ['me@localhost', '<p>me@localhost</p>'],
            'no name' => ['@x.test', '<p>@x.test</p>'],
            'a handle' => ['thanks @octocat', '<p>thanks @octocat</p>'],
            'inside link text' => ['[me@x.test](https://y.test)', '<p>' . $this->a('https://y.test', 'me@x.test') . '</p>'],
            'in a code span' => ['`me@x.test`', '<p><code>me@x.test</code></p>'],
        ], ['autolinkEmails' => true]);
    }

    // ---- block syntax ----

    public function testIndentedCode(): void
    {
        $this->assertConverts('<p>a<br>b</p>', "    a\n    b", [], 'Off by default, indented lines are a paragraph.');

        $this->assertAllConvert([
            'a block' => ["    a\n    b", "<pre><code>a\nb\n</code></pre>"],
            'extra indentation is kept' => ["    a\n      b", "<pre><code>a\n  b\n</code></pre>"],
            'blank lines inside are kept' => ["    a\n\n    b", "<pre><code>a\n\nb\n</code></pre>"],
            'blank lines at the end are not' => ["    a\n\n\nb", "<pre><code>a\n</code></pre>\n<p>b</p>"],
            'markup is escaped' => ['    <b>&</b>', "<pre><code>&lt;b&gt;&amp;&lt;/b&gt;\n</code></pre>"],
            'it cannot interrupt a paragraph' => ["a\n    b", '<p>a<br>b</p>'],
            'three spaces is not code' => ['   a', '<p>a</p>'],
            'a tab' => ["\ta", "<pre><code>a\n</code></pre>"],
            'inside a list item' => ["- a\n\n      b", "<ul>\n<li>\n<p>a</p>\n<pre><code>b\n</code></pre>\n</li>\n</ul>"],
            'inside a quote' => ['>     a', "<blockquote>\n<pre><code>a\n</code></pre>\n</blockquote>"],
        ], ['indentedCode' => true]);
    }

    public function testSetextHeadings(): void
    {
        $this->assertConverts("<p>Title<br>=====</p>", "Title\n=====", [], 'Off by default, the underline is text.');
        $this->assertConverts("<p>Title</p>\n<hr>", "Title\n---", [], 'Off by default, dashes are a rule.');

        $this->assertAllConvert([
            'level one' => ["Title\n=====", '<h1>Title</h1>'],
            'level two' => ["Title\n-----", '<h2>Title</h2>'],
            'a short underline' => ["Title\n=", '<h1>Title</h1>'],
            'an indented underline' => ["Title\n   ---", '<h2>Title</h2>'],
            'spaces after the underline' => ["Title\n===  ", '<h1>Title</h1>'],
            'several lines' => ["a\nb\n===", '<h1>a<br>b</h1>'],
            'markup in the text' => ["*a* `b`\n---", '<h2><em>a</em> <code>b</code></h2>'],
            'a rule on its own is still a rule' => ["a\n\n---\n\nb", "<p>a</p>\n<hr>\n<p>b</p>"],
            'a table is still a table' => ["a | b\n--- | ---\n1 | 2", "<table>\n<thead>\n<tr>\n<th>a</th>\n<th>b</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>1</td>\n<td>2</td>\n</tr>\n</tbody>\n</table>"],
            'a list is not a heading' => ["- a\n---", "<ul>\n<li>a</li>\n</ul>\n<hr>"],
            'words under it are a new paragraph' => ["Title\n===\ntext", "<h1>Title</h1>\n<p>text</p>"],
            'in a quote' => ["> Title\n> ---", "<blockquote>\n<h2>Title</h2>\n</blockquote>"],
            'a line of text then equals signs inside text' => ["a = b\n===", '<h1>a = b</h1>'],
        ], ['setextHeadings' => true]);

        $this->assertConverts('<h3>Title</h3>', "Title\n===", ['setextHeadings' => true, 'headingOffset' => 2], 'The heading offset applies.');
        $this->assertConverts('<h6>Title</h6>', "Title\n---", ['setextHeadings' => true, 'headingOffset' => 5], 'Levels stop at 6.');
    }

    public function testTaskLists(): void
    {
        $this->assertConverts("<ul>\n<li>[ ] a</li>\n<li>[x] b</li>\n</ul>", "- [ ] a\n- [x] b", [], 'Off by default, the brackets are text.');

        $this->assertAllConvert([
            'unchecked and checked' => ["- [ ] a\n- [x] b\n- [X] c", "<ul>\n<li>\u{2610} a</li>\n<li>\u{2611} b</li>\n<li>\u{2611} c</li>\n</ul>"],
            'a numbered list' => ['1. [x] a', "<ol>\n<li>\u{2611} a</li>\n</ol>"],
            'markup after the box' => ['- [ ] **a**', "<ul>\n<li>\u{2610} <strong>a</strong></li>\n</ul>"],
            'a loose list' => ["- [ ] a\n\n- [x] b", "<ul>\n<li>\n<p>\u{2610} a</p>\n</li>\n<li>\n<p>\u{2611} b</p>\n</li>\n</ul>"],
            'no space after the box' => ['- [ ]a', "<ul>\n<li>[ ]a</li>\n</ul>"],
            'no text after the box' => ['- [ ]', "<ul>\n<li>[ ]</li>\n</ul>"],
            'a box that is not first' => ['- a [x]', "<ul>\n<li>a [x]</li>\n</ul>"],
            'another letter' => ['- [y] a', "<ul>\n<li>[y] a</li>\n</ul>"],
            'outside a list' => ['[x] a', '<p>[x] a</p>'],
        ], ['taskLists' => true]);
    }

    // ---- reference links ----

    public function testReferenceLinksAreTextByDefault(): void
    {
        $html = $this->html("[a][b]\n\n[b]: https://x.test");

        $this->assertStringContainsString('[a][b]', $html);
        $this->assertStringContainsString('[b]:', $html);
    }

    public function testReferenceLinks(): void
    {
        $this->assertAllConvert([
            'a full reference' => ["[a][b]\n\n[b]: https://x.test", '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'a collapsed reference' => ["[a][]\n\n[a]: https://x.test", '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'a shortcut reference' => ["[a]\n\n[a]: https://x.test", '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'defined before it is used' => ["[b]: https://x.test\n\n[a][b]", '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'case and spacing do not matter' => ["[a][The   Docs]\n\n[the docs]: https://x.test", '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'a title in quotes' => ["[a][b]\n\n[b]: https://x.test \"The Title\"", '<p>' . $this->a('https://x.test', 'a', 'The Title') . '</p>'],
            'a title in parentheses' => ["[a][b]\n\n[b]: https://x.test (The Title)", '<p>' . $this->a('https://x.test', 'a', 'The Title') . '</p>'],
            'an address in angle brackets' => ["[a][b]\n\n[b]: <https://x.test/a b>", '<p>a</p>'],
            'the first definition wins' => ["[a]\n\n[a]: https://x.test/1\n[a]: https://x.test/2", '<p>' . $this->a('https://x.test/1', 'a') . '</p>'],
            'an undefined label is text' => ['[a][nope] and [nope]', '<p>[a][nope] and [nope]</p>'],
            'the definition is not shown' => ["a\n\n[b]: https://x.test\n\nc", "<p>a</p>\n<p>c</p>"],
            'a definition cannot interrupt a paragraph' => ["a\n[b]: https://x.test", '<p>a<br>[b]: ' . $this->a('https://x.test', 'https://x.test') . '</p>'],
            'an unsafe address keeps only the text' => ["[a][b]\n\n[b]: javascript:alert(1)", '<p>a</p>'],
            'a relative address is not linked without a base' => ["[a][b]\n\n[b]: docs/page", '<p>a</p>'],
            'inside a list item' => ["- [a][b]\n\n[b]: https://x.test", "<ul>\n<li>" . $this->a('https://x.test', 'a') . "</li>\n</ul>"],
            'a definition inside a quote leaves an empty quote' => ["> [b]: https://x.test\n\n[a][b]", "<blockquote>\n</blockquote>\n<p>" . $this->a('https://x.test', 'a') . '</p>'],
            'markup in the text' => ["[*a*][b]\n\n[b]: https://x.test", '<p>' . $this->a('https://x.test', '<em>a</em>') . '</p>'],
            'a link cannot hold a link, so the inner one wins and the label after it is a reference of its own' => ["[a [c](https://y.test)][b]\n\n[b]: https://x.test", '<p>[a ' . $this->a('https://y.test', 'c') . ']' . $this->a('https://x.test', 'b') . '</p>'],
            'an inline link still works' => ["[a](https://x.test)\n\n[a]: https://y.test", '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'a brace in the label' => ["[a\\]b]\n\n[a\\]b]: https://x.test", '<p>' . $this->a('https://x.test', 'a]b') . '</p>'],
        ], ['referenceLinks' => true]);
    }

    public function testAReferenceFollowedByABracketThatCannotStartALabelIsStillALink(): void
    {
        $this->assertAllConvert([
            'a bracket that is never closed' => ["[b][*\n\n[b]: https://x.test", '<p>' . $this->a('https://x.test', 'b') . '[*</p>'],
            'a bracket that holds another bracket' => ["[b][[c]\n\n[b]: https://x.test", '<p>' . $this->a('https://x.test', 'b') . '[[c]</p>'],
            'a collapsed reference' => ["[b][]\n\n[b]: https://x.test", '<p>' . $this->a('https://x.test', 'b') . '</p>'],
            'a label that is not defined is not a link, even when the text is' => ["[b][c]\n\n[b]: https://x.test", '<p>[b][c]</p>'],
            'a label of spaces is a label, and is not defined' => ["[b][ ]\n\n[b]: https://x.test", '<p>[b][ ]</p>'],
            'a label with a character in it is a label, and is not defined' => ["[b][*]\n\n[b]: https://x.test", '<p>[b][*]</p>'],
        ], ['referenceLinks' => true]);
    }

    public function testReferenceImages(): void
    {
        $options = ['referenceLinks' => true, 'imageHosts' => ['x.test']];

        $this->assertAllConvert([
            'a full reference' => ["![a][b]\n\n[b]: https://x.test/a.png", '<p>' . $this->img('https://x.test/a.png', 'a') . '</p>'],
            'a shortcut reference with a title' => ["![a]\n\n[a]: https://x.test/a.png \"T\"", '<p>' . $this->img('https://x.test/a.png', 'a', 'T') . '</p>'],
            'another host becomes a link' => ["![a][b]\n\n[b]: https://y.test/a.png", '<p>' . $this->a('https://y.test/a.png', 'a') . '</p>'],
        ], $options);
    }

    // ---- emoji ----

    public function testEmojiNamesAreTextByDefault(): void
    {
        $this->assertConverts('<p>:tada: :bug:</p>', ':tada: :bug:');
    }

    public function testEmojiNames(): void
    {
        $this->assertAllConvert([
            'a name' => [':tada:', "<p>\u{1F389}</p>"],
            'in a sentence' => ['Fixed :bug: and shipped :rocket:!', "<p>Fixed \u{1F41B} and shipped \u{1F680}!</p>"],
            'a name with a sign' => [':+1: :-1:', "<p>\u{1F44D} \u{1F44E}</p>"],
            'a name with digits' => [':100:', "<p>\u{1F4AF}</p>"],
            'a sequence with a variation selector' => [':warning:', "<p>\u{26A0}\u{FE0F}</p>"],
            'a name that does not exist' => [':nosuchname:', '<p>:nosuchname:</p>'],
            'upper case is not a name' => [':TADA:', '<p>:TADA:</p>'],
            'a time of day' => ['at 12:30:45 or 9:100:2', '<p>at 12:30:45 or 9:100:2</p>'],
            'directly after a letter' => ['a:tada:', '<p>a:tada:</p>'],
            'two in a row' => [':tada::bug:', "<p>\u{1F389}\u{1F41B}</p>"],
            'a lone colon' => ['a : b :', '<p>a : b :</p>'],
            'a code span is left alone' => ['`:tada:`', '<p><code>:tada:</code></p>'],
            'a fence is left alone' => ["```\n:tada:\n```", "<pre><code>:tada:\n</code></pre>"],
            'in a link text' => ['[:tada:](https://x.test)', '<p>' . $this->a('https://x.test', "\u{1F389}") . '</p>'],
            'in a heading' => ['# :rocket: Launch', "<h1>\u{1F680} Launch</h1>"],
            'in a table' => ["| a |\n| - |\n| :bug: |", "<table>\n<thead>\n<tr>\n<th>a</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>\u{1F41B}</td>\n</tr>\n</tbody>\n</table>"],
        ], ['emoji' => true]);
    }

    public function testCustomEmoji(): void
    {
        $options = ['emoji' => true, 'customEmoji' => ['shipit' => 'S!', 'tada' => '<b>']];

        $this->assertAllConvert([
            'a new name' => [':shipit:', '<p>S!</p>'],
            'a replacement for a built in name' => [':tada:', '<p>&lt;b&gt;</p>'],
            'a built in name still works' => [':bug:', "<p>\u{1F41B}</p>"],
        ], $options);

        $this->assertConverts('<p>:shipit:</p>', ':shipit:', ['customEmoji' => ['shipit' => 'S!']], 'Custom names are used only when emoji is on.');
    }

    public function testCustomEmojiNamedWithOnlyDigitsAreAccepted(): void
    {
        // PHP converts keys such as `100` and `-1` to integers, which once made
        // these names look invalid.
        $options = ['emoji' => true, 'customEmoji' => ['100' => 'full marks', '404' => 'lost', '-1' => 'down']];

        $this->assertAllConvert([
            'a new name of digits' => [':404:', '<p>lost</p>'],
            'a replacement for a built in name of digits' => [':100:', '<p>full marks</p>'],
            'a replacement for a built in name that starts with a minus' => [':-1:', '<p>down</p>'],
            'a built in name of digits still works without a replacement' => [':+1:', "<p>\u{1F44D}</p>"],
        ], $options);

        $this->assertConverts("<p>\u{1F4AF} \u{1F44E}</p>", ':100: :-1:', ['emoji' => true], 'The built in digit names work with no custom names.');
    }

    public function testCustomEmojiAreChecked(): void
    {
        foreach ([['Bad Name' => 'x'], ['UPPER' => 'x'], ['a' => ''], ['a' => "x\ny"], ['a' => str_repeat('x', 65)], ['' => 'x'], [str_repeat('a', 33) => 'x']] as $names) {
            try {
                new MarkdownConverter(emoji: true, customEmoji: $names);
                $this->fail('These custom emoji should be refused: ' . json_encode($names));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- inline diffs ----

    public function testInlineDiffsAreTextByDefault(): void
    {
        $this->assertConverts('<p>{+ a +} [- b -]</p>', '{+ a +} [- b -]');
    }

    public function testInlineDiffs(): void
    {
        $this->assertAllConvert([
            'an addition' => ['{+ added +}', '<p><ins>added</ins></p>'],
            'a deletion' => ['{- removed -}', '<p><del>removed</del></p>'],
            'in square brackets' => ['[+ added +] [- removed -]', '<p><ins>added</ins> <del>removed</del></p>'],
            'mixed brackets' => ['{+ a +] [+ b +}', '<p><ins>a</ins> <ins>b</ins></p>'],
            'in a sentence' => ['Use {- the old -}{+ the new +} way.', '<p>Use <del>the old</del><ins>the new</ins> way.</p>'],
            'markup inside' => ['{+ **bold** and `code` +}', '<p><ins><strong>bold</strong> and <code>code</code></ins></p>'],
            'a link inside' => ['{+ [a](https://x.test) +}', '<p><ins>' . $this->a('https://x.test', 'a') . '</ins></p>'],
            'markup is escaped' => ['{+ <b>x</b> & y +}', '<p><ins>&lt;b&gt;x&lt;/b&gt; &amp; y</ins></p>'],
            'no spaces is not a diff' => ['{+a+} [-b-]', '<p>{+a+} [-b-]</p>'],
            'a number in brackets is not a diff' => ['[-1] and [+1]', '<p>[-1] and [+1]</p>'],
            'a sign that does not match' => ['{+ a -}', '<p>{+ a -}</p>'],
            'no end' => ['{+ a', '<p>{+ a</p>'],
            'it cannot span lines' => ["{+ a\nb +}", '<p>{+ a<br>b +}</p>'],
            'a plain brace' => ['{ a } and {}', '<p>{ a } and {}</p>'],
            'a link still works' => ['[a](https://x.test)', '<p>' . $this->a('https://x.test', 'a') . '</p>'],
            'in a code span' => ['`{+ a +}`', '<p><code>{+ a +}</code></p>'],
        ], ['inlineDiffs' => true]);

        $this->assertConverts(
            '<p><ins style="background:#ccffd8">a</ins> <del style="background:#ffd7d5">b</del></p>',
            '{+ a +} {- b -}',
            ['inlineDiffs' => true, 'inlineStyles' => true],
        );
    }

    // ---- fenced quotes ----

    public function testFencedQuotesAreOrdinaryQuotesByDefault(): void
    {
        $this->assertConverts("<blockquote>\n<blockquote>\n<blockquote>\n</blockquote>\n</blockquote>\n</blockquote>\n<p>a</p>", ">>>\n\na", [], 'Off by default, ">>>" is three nested quotes.');
    }

    public function testFencedQuotes(): void
    {
        $this->assertAllConvert([
            'a quote' => [">>>\na\n>>>", "<blockquote>\n<p>a</p>\n</blockquote>"],
            'several paragraphs and markup' => [">>>\nOne **two**\n\nThree\n>>>", "<blockquote>\n<p>One <strong>two</strong></p>\n<p>Three</p>\n</blockquote>"],
            'text after it' => [">>>\na\n>>>\n\nb", "<blockquote>\n<p>a</p>\n</blockquote>\n<p>b</p>"],
            'no closing line runs to the end' => [">>>\na\n\nb", "<blockquote>\n<p>a</p>\n<p>b</p>\n</blockquote>"],
            'an alert' => [">>> [!NOTE]\nCareful\n>>>", "<div class=\"markdown-alert markdown-alert-note\">\n<p class=\"markdown-alert-title\">Note</p>\n<p>Careful</p>\n</div>"],
            'an alert in lower case' => [">>> [!warning]\nx\n>>>", "<div class=\"markdown-alert markdown-alert-warning\">\n<p class=\"markdown-alert-title\">Warning</p>\n<p>x</p>\n</div>"],
            'an unknown alert kind is not a fence, so it is nested quotes' => [">>> [!NOPE]\nx\n>>>", "<blockquote>\n<blockquote>\n<blockquote>\n<p>[!NOPE]<br>x</p>\n</blockquote>\n</blockquote>\n</blockquote>"],
            'text after the marks is a nested quote' => ['>>> a', "<blockquote>\n<blockquote>\n<blockquote>\n<p>a</p>\n</blockquote>\n</blockquote>\n</blockquote>"],
            'a code fence can hold the marks' => [">>>\n```\n>>>\n```\nb\n>>>", "<blockquote>\n<pre><code>&gt;&gt;&gt;\n</code></pre>\n<p>b</p>\n</blockquote>"],
            'a list inside' => [">>>\n- a\n- b\n>>>", "<blockquote>\n<ul>\n<li>a</li>\n<li>b</li>\n</ul>\n</blockquote>"],
            'a quote inside a list item' => ["- a\n\n  >>>\n  b\n  >>>", "<ul>\n<li>\n<p>a</p>\n<blockquote>\n<p>b</p>\n</blockquote>\n</li>\n</ul>"],
            'an ordinary quote still works' => ['> a', "<blockquote>\n<p>a</p>\n</blockquote>"],
        ], ['fencedQuotes' => true]);
    }

    // ---- the settings together ----

    public function testEverySettingCanBeUsedAtOnce(): void
    {
        $converter = new MarkdownConverter(
            html: 'sanitize',
            imageHosts: ['x.test'],
            decodeEntities: true,
            baseUrl: 'https://x.test/docs',
            linkSchemes: ['http', 'https', 'mailto', 'ftp'],
            autolinkWww: true,
            autolinkEmails: true,
            indentedCode: true,
            setextHeadings: true,
            referenceLinks: true,
            taskLists: true,
            emoji: true,
            inlineDiffs: true,
            fencedQuotes: true,
            math: true,
            mermaid: true,
        );

        $html = $converter->toHtml("Title\n=====\n\n    code\n\n- [x] <b>done</b> &copy; [guide][g] www.x.test me@x.test\n\n[g]: guide/start\n");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString("\u{2611} <strong>done</strong> \u{A9}", $html);
        $this->assertStringContainsString('href="https://x.test/docs/guide/start"', $html);
        $this->assertStringContainsString('href="http://www.x.test"', $html);
        $this->assertStringContainsString('href="mailto:me@x.test"', $html);
        $this->assertStringContainsString("<pre><code>code\n</code></pre>", $html);
        $this->assertSafe($html, '', ['http', 'https', 'mailto', 'ftp']);
    }
}
