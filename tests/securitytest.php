<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\MarkdownConverter;
use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Tests that the converter is safe for text from untrusted authors, such as
 * pull request titles in generated release notes. The tests give it malicious
 * input and check that the output, once parsed, holds only the tags,
 * attributes, and addresses the converter is meant to write.
 */
final class SecurityTest extends MarkdownTestCase
{
    /**
     * Returns the malicious inputs to try, each with a name.
     *
     * @return array<string, string>
     */
    private function payloads(): array
    {
        return [
            // Raw HTML in text
            'script tag' => '<script>alert(1)</script>',
            'image with a handler' => '<img src=x onerror=alert(1)>',
            'svg with a handler' => '<svg onload=alert(1)>',
            'iframe' => '<iframe src="javascript:alert(1)"></iframe>',
            'anchor' => '<a href="javascript:alert(1)">x</a>',
            'style tag' => "<style>@import 'https://evil.test/x.css';</style>",
            'details with a handler' => '<details open ontoggle=alert(1)>',
            'form' => '<form action=javascript:alert(1)><input type=submit></form>',
            'body handler' => '<body onload=alert(1)>',
            'noscript breakout' => '<noscript><p title="</noscript><img src=x onerror=alert(1)>">',
            'cdata' => '<![CDATA[<script>alert(1)</script>]]>',
            'comment breakout' => '<!--><script>alert(1)</script>-->',
            'script between comments' => '<!-- --><script>alert(1)</script><!-- -->',
            'meta refresh' => '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">',
            'base tag' => '<base href="https://evil.test/">',
            'entities for a script tag' => '&lt;script&gt;alert(1)&lt;/script&gt;',
            'numeric entities for a script tag' => '&#60;script&#62;alert(1)&#60;/script&#62;',
            'fullwidth brackets' => "\u{FF1C}script\u{FF1E}alert(1)\u{FF1C}/script\u{FF1E}",

            // Links
            'javascript link' => '[x](javascript:alert(1))',
            'javascript link with spaces' => '[x](  javascript:alert(1)  )',
            'javascript link in angle brackets' => '[x](<javascript:alert(1)>)',
            'javascript link in capitals' => '[x](JAVASCRIPT:alert(1))',
            'javascript link with entities' => '[x](java&#x73;cript:alert(1))',
            'javascript link with a numeric entity' => '[x](&#106;avascript:alert(1))',
            'javascript link with a tab' => "[x](java\tscript:alert(1))",
            'javascript link with a zero width space' => "[x](java\u{200B}script:alert(1))",
            'javascript link with a byte order mark' => "[x](\u{FEFF}javascript:alert(1))",
            'data link' => '[x](data:text/html,<script>alert(1)</script>)',
            'data link in base64' => '[x](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)',
            'attribute breakout in the address' => '[x](https://x.test" onmouseover="alert(1))',
            'attribute breakout in the title' => '[x](https://x.test "t\" onmouseover=\"alert(1)")',
            'quote in a single quoted title' => "[x](https://x.test 'a\"b')",
            'tag breakout in the address' => '[x](https://x.test/"><script>alert(1)</script>)',
            'tag breakout as the whole address' => '[x]("><script>alert(1)</script>)',
            'apostrophe and quote in the address' => "[x](https://x.test/a'b\"c)",
            'link definition with javascript' => "[x]: javascript:alert(1)\n\n[x]",
            'autolink with javascript' => '<javascript:alert(1)>',
            'autolink with a breakout' => '<https://x.test/"onmouseover="alert(1)>',
            'bare address with a breakout' => 'https://x.test/"onmouseover="alert(1)',
            'bare address with a tag' => 'https://x.test/<script>alert(1)</script>',
            'mail address with a breakout' => '<a"onmouseover="alert(1)@x.test>',
            'link text with html' => '[<script>alert(1)</script>](https://x.test)',
            'link text with an image handler' => '[<img src=x onerror=alert(1)>](https://x.test)',

            // Images
            'image with javascript' => '![x](javascript:alert(1))',
            'image description breakout' => '![x" onerror="alert(1)](https://github.com/a.png)',
            'image address breakout' => '![x](https://github.com/a.png" onerror="alert(1))',
            'image title breakout' => '![x](https://github.com/a.png "t\" onerror=\"alert(1)")',
            'image description with a script' => '![<script>alert(1)</script>](https://github.com/a.png)',
            'image from another host' => '![x](https://evil.test/a.png)',
            'image as a data address' => '![x](data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+)',
            'image with a user name that looks like a host' => '![x](https://github.com@evil.test/a.png)',
            'image on a look alike host' => '![x](https://github.com.evil.test/a.png)',

            // Code
            'language that breaks out' => "```js\" onclick=\"alert(1)\ncode\n```",
            'language that is a tag' => "```<script>\nx\n```",
            'script in a code span' => '`<script>alert(1)</script>`',
            'script in a code block' => "```\n<script>alert(1)</script>\n```",
            'closing fence with a script' => "```\na\n```<script>alert(1)</script>",

            // Blocks
            'script in a heading' => '# <script>alert(1)</script>',
            'script in a quote' => '> <script>alert(1)</script>',
            'script in a list item' => '- <script>alert(1)</script>',
            'script in a table cell' => "| <script>alert(1)</script> |\n| - |\n| <img src=x onerror=alert(1)> |",
            'breakout in the table alignment' => "| a |\n| :\"><script>alert(1)</script> |",
            'alert kind with a breakout' => "> [!NOTE\" onclick=\"alert(1)]\n> x",
            'script in an alert' => "> [!NOTE]\n> <script>alert(1)</script>",
            'start number with a breakout' => "1\" onclick=\"alert(1). a",

            // HTML that is kept or removed when the HTML setting is on
            'anchor with an entity hidden scheme' => '<a href="jav&#x09;ascript:alert(1)">x</a>',
            'anchor with a numeric scheme' => '<a href="&#106;avascript:alert(1)">x</a>',
            'anchor with a data address' => '<a href="data:text/html,<script>alert(1)</script>">x</a>',
            'anchor with a handler' => '<a href="https://x.test" onclick="alert(1)" target="_top">x</a>',
            'image with a handler and a good address' => '<img src="https://x.test/a.png" onerror="alert(1)">',
            'image with a script width' => '<img src="https://x.test/a.png" width="1" onerror="alert(1)" height="2px">',
            'image with a srcset' => '<img src="https://x.test/a.png" srcset="javascript:alert(1)">',
            'bold with a handler' => '<b onclick="alert(1)" style="x">x</b>',
            'details with a handler' => "<details open ontoggle=alert(1)>\n\nx\n\n</details>",
            'paragraph with a handler' => '<p align="center" onclick="alert(1)">x</p>',
            'div with a style' => '<div style="background:url(javascript:alert(1))">x</div>',
            'unclosed tags' => '<b><i><u><sub><sup><mark><small><ins>x',
            'stray closing tags' => '</b></i></a></details></div></p></summary>',
            'nested anchors' => '<a href="https://x.test"><a href="https://y.test">x</a></a>',
            'a tag inside a tag' => '<<b>b onclick=alert(1)>x',
            'a script inside a code tag' => '<code><script>alert(1)</script></code>',
            'a script that never ends' => '<script>alert(1)',
            'a comment around tags' => '<!-- <b>x</b> --><b>y</b>',
            'a form' => '<form action="https://evil.test"><input name=x></form>',
            'a base tag' => '<base href="https://evil.test/">',
            'a meta refresh' => '<meta http-equiv="refresh" content="0;url=https://evil.test">',
            'a math tag' => '<math><mi xlink:href="javascript:alert(1)">x</mi></math>',
            'a very long tag' => '<b ' . str_repeat('a="b" ', 2000) . '>x</b>',
            'definitions that point at script' => "[x]: javascript:alert(1)\n[y]: data:text/html,x\n\n[x] [y][y] ![x]",
            'a numeric reference for a control character' => '&#0; &#x1; &#xD800; &#1114112; &#x202E;',
            'a www address with a script' => 'www.x.test/<script>alert(1)</script>',
            'a mail address with markup' => 'a<b>@x.test "onclick=1"@x.test',
            'indented html' => "    <script>alert(1)</script>\n\n        <b onclick=1>x</b>",

            // The GitLab style syntax and emoji
            'a diff with a script' => '{+ <script>alert(1)</script> +} [- <img src=x onerror=alert(1)> -]',
            'a diff with a link' => '{+ [x](javascript:alert(1)) +}',
            'a diff with a breakout' => '{+ " onclick="alert(1) +}',
            'a quote fence with a script' => ">>>\n<script>alert(1)</script>\n>>>",
            'an alert fence with a breakout' => ">>> [!NOTE\" onclick=\"alert(1)]\nx\n>>>",
            'an emoji name that is a payload' => ':bad: :bad::bad:',

            // Diagrams
            'a diagram with a script' => "```mermaid\ngraph TD\nA[<script>alert(1)</script>] --> B\n```",
            'a diagram that closes the element' => "```mermaid\n</pre><script>alert(1)</script><pre class=\"mermaid\">\n```",
            'a diagram with a click handler' => "```mermaid\ngraph TD\nA --> B\nclick A href \"javascript:alert(1)\"\n```",
            'a diagram with an image and quotes' => "```mermaid\nA[\"<img src=x onerror=alert(1)>\"] & \" onclick=\"alert(1)\n```",
            'a diagram in a quote and a list' => "> ```mermaid\n> <script>alert(1)</script>\n> ```\n\n- ```mermaid\n  <script>alert(1)</script>\n  ```",

            // Math
            'math with a script' => '$<script>alert(1)</script>$ $$<img src=x onerror=alert(1)>$$',
            'math with a breakout' => '$x</span><script>alert(1)</script>$',
            'math with quotes and ampersands' => '$\" onclick=\"alert(1) & &lt;b&gt;$',
            'a math block with a script' => "$$\n</div><script>alert(1)</script>\n$$",
            'a math fence with a script' => "```math\n</div><script>alert(1)</script>\n```",
            'math with a link' => '$[x](javascript:alert(1))$',
            'math in link text' => '[$a$](javascript:alert(1)) [$$b$$](https://x.test)',
            'math in an image description' => '![$a$ \"onerror=1](https://x.test/a.png)',
            'math in a table' => "| a |\n| - |\n| $<b>x</b>$ |",
            'math in a quote and a list' => "> $$\n> <script>alert(1)</script>\n> $$\n\n- $$\n  <script>alert(1)</script>\n  $$",

            // Characters
            'right to left override' => "https://x.test/\u{202E}gpj.exe",
            'null bytes' => "<scr\x00ipt>alert(1)</scr\x00ipt>",
            'control characters' => "<scr\x01ipt>alert(1)</scr\x01ipt>",
            'invalid UTF-8' => "<script\xFF>alert(1)</script>",
            'lone surrogate bytes' => "\xED\xA0\x80<script>alert(1)</script>",
        ];
    }

    public function testHostileInputNeverProducesUnsafeMarkup(): void
    {
        $settings = [
            'the defaults' => [],
            'every host allowed and styles on' => ['imageHosts' => ['*'], 'inlineStyles' => true],
            'no target and spaces for breaks' => ['linkTarget' => '', 'softBreak' => 'space'],
            'html removed' => ['html' => 'strip', 'imageHosts' => ['*']],
            'html sanitized' => ['html' => 'sanitize', 'imageHosts' => ['*']],
            'every setting on' => [
                'html' => 'sanitize', 'imageHosts' => ['*'], 'inlineStyles' => true, 'decodeEntities' => true,
                'baseUrl' => 'https://x.test/docs', 'autolinkWww' => true, 'autolinkEmails' => true, 'indentedCode' => true,
                'setextHeadings' => true, 'referenceLinks' => true, 'taskLists' => true,
                'emoji' => true, 'customEmoji' => ['bad' => '<script>alert(1)</script>"x'], 'inlineDiffs' => true, 'fencedQuotes' => true,
            ],
        ];

        foreach ($this->payloads() as $name => $payload) {
            foreach ($settings as $label => $options) {
                $html = $this->html($payload, $options);

                $this->assertSafe($html, $name . ' with ' . $label . ': ' . $html);
                $this->assertDoesNotMatchRegularExpression('/<script|<iframe|<svg|<style|<meta|<base|<form|<body/i', $html, $name . ' with ' . $label);
            }
        }
    }

    public function testHostileInputInsideEveryKindOfContainer(): void
    {
        $containers = [
            'a list item' => static fn (string $p): string => '- ' . $p,
            'a nested list item' => static fn (string $p): string => "- a\n  - " . $p,
            'a numbered item' => static fn (string $p): string => '1. ' . $p,
            'a quote' => static fn (string $p): string => '> ' . $p,
            'an alert' => static fn (string $p): string => "> [!WARNING]\n> " . $p,
            'a heading' => static fn (string $p): string => '## ' . $p,
            'a table cell' => static fn (string $p): string => "| a |\n| - |\n| " . str_replace("\n", ' ', $p) . ' |',
            'emphasis' => static fn (string $p): string => '**' . str_replace("\n", ' ', $p) . '**',
            'a link text' => static fn (string $p): string => '[' . str_replace(["\n", ']'], [' ', ''], $p) . '](https://x.test)',
        ];

        foreach ($this->payloads() as $name => $payload) {
            foreach ($containers as $label => $wrap) {
                foreach ([['imageHosts' => ['*']], ['imageHosts' => ['*'], 'html' => 'sanitize', 'referenceLinks' => true, 'decodeEntities' => true]] as $options) {
                    $html = $this->html($wrap($payload), $options);

                    $this->assertSafe($html, $name . ' in ' . $label . ': ' . $html);
                }
            }
        }
    }

    public function testLinkAddressesAreEscapedInTheAttribute(): void
    {
        $html = $this->html('[x](https://x.test/a?b="c"&d=<e>)');

        $this->assertSafe($html);
        $this->assertStringContainsString('href="https://x.test/a?b=&quot;c&quot;&amp;d=&lt;e&gt;"', $html);
    }

    public function testNoUnsafeAddressEverReachesAnAttribute(): void
    {
        $schemes = ['javascript', 'JAVASCRIPT', 'JavaScript', 'vbscript', 'data', 'file', 'about', 'blob', 'ms-its', 'livescript', 'mhtml'];

        foreach ($schemes as $scheme) {
            foreach (['[x](%s:alert(1))', '![x](%s:alert(1))', '<%s:alert(1)>', '[x]: %s:alert(1)' . "\n\n[x]", '%s:alert(1)'] as $template) {
                $html = $this->html(sprintf($template, $scheme), ['imageHosts' => ['*']]);

                $this->assertDoesNotMatchRegularExpression('/(?:href|src)="[^"]*' . preg_quote($scheme, '/') . ':/i', $html, $scheme . ' in ' . $template);
                $this->assertSafe($html, $scheme . ' in ' . $template);
            }
        }
    }

    public function testRandomInputNeverProducesUnsafeMarkupOrTakesLong(): void
    {
        $pieces = [
            '*', '**', '_', '__', '~', '~~', '`', '``', '[', ']', '(', ')', '![', '](', '<', '>', '|', '-', '--', '---', '#', '##', '1.', '- ', '> ',
            "\n", "\n\n", ' ', '  ', "\t", '\\', 'a', 'b c', 'https://x.test', 'https://github.com/a.png', '<script>', '</script>', 'javascript:', 'onerror=',
            '[!NOTE]', '[!TIP]', ':--', '| a |', '<!--', '-->', '&amp;', '"', "'", '日本', '🎉', "\u{200B}", "\u{202E}",
            '<b>', '</b>', '<a href="', '<a href="https://x.test">', '</a>', '<img src="https://x.test/a.png" width="9">', '<details>', '</details>',
            '<summary>', '</summary>', '<div align="center">', '</div>', '<br>', '<hr>', '<kbd>', '</kbd>', '<script', '[a]: https://x.test', '[a]', '[a][a]',
            '&copy;', '&#0;', 'www.x.test', 'a@b.test', '    ', '===', '- [ ] ', '- [x] ',
            '{+ a +}', '[- b -]', '{+', '+}', '>>>', '>>> [!NOTE]', ':tada:', ':bad:', ':',
            '$', '$$', '$x$', '$$x$$', '\\$', "$$\n", "\n$$\n", '```math', '```mermaid', "```mermaid\n", '$<b>$', '\\(', '\\[',
        ];

        mt_srand(20261006);
        $withImages = new MarkdownConverter(imageHosts: ['*']);
        $everything = new MarkdownConverter(
            imageHosts: ['*'],
            html: 'sanitize',
            decodeEntities: true,
            baseUrl: 'https://x.test/docs',
            autolinkWww: true,
            autolinkEmails: true,
            indentedCode: true,
            setextHeadings: true,
            referenceLinks: true,
            taskLists: true,
            emoji: true,
            customEmoji: ['bad' => '<script>alert(1)</script>"x'],
            inlineDiffs: true,
            fencedQuotes: true,
        );
        $slowest = 0.0;

        for ($run = 0; $run < 2500; $run++) {
            $input = '';
            $count = mt_rand(1, 60);

            for ($i = 0; $i < $count; $i++) {
                $input .= $pieces[mt_rand(0, count($pieces) - 1)];
            }

            foreach ([$withImages, $everything] as $converter) {
                $start = microtime(true);
                $html = $converter->toHtml($input);
                $slowest = max($slowest, microtime(true) - $start);

                $this->assertSame([], \ScottOffen\MarkdownConverter\Tests\Support\HtmlChecker::violations($html), 'Run ' . $run . ': ' . json_encode($input) . ' gave ' . $html);
            }
        }

        $this->assertLessThan(0.5, $slowest, 'No small input should take half a second.');
    }
}
