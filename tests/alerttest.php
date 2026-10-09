<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

final class AlertTest extends MarkdownTestCase
{
    private function alert(string $kind, string $content): string
    {
        return '<div class="markdown-alert markdown-alert-' . $kind . "\">\n"
            . '<p class="markdown-alert-title">' . ucfirst($kind) . "</p>\n"
            . ($content !== '' ? $content . "\n" : '')
            . '</div>';
    }

    public function testEveryKindOfAlert(): void
    {
        foreach (['note', 'tip', 'important', 'warning', 'caution'] as $kind) {
            $this->assertConverts(
                $this->alert($kind, '<p>Text.</p>'),
                '> [!' . strtoupper($kind) . "]\n> Text.",
                [],
                'The ' . $kind . ' alert',
            );
        }
    }

    public function testTheKindIsNotCaseSensitive(): void
    {
        $this->assertConverts($this->alert('note', '<p>a</p>'), "> [!note]\n> a");
        $this->assertConverts($this->alert('warning', '<p>a</p>'), "> [!Warning]\n> a");
    }

    public function testAnAlertCanHoldAnyBlocks(): void
    {
        $this->assertConverts(
            $this->alert('tip', "<p>One <strong>two</strong>.</p>\n<ul>\n<li>a</li>\n<li>b</li>\n</ul>\n<pre><code>x\n</code></pre>"),
            "> [!TIP]\n> One **two**.\n>\n> - a\n> - b\n>\n> ```\n> x\n> ```",
        );
    }

    public function testAnAlertWithTwoParagraphs(): void
    {
        $this->assertConverts(
            $this->alert('important', "<p>a</p>\n<p>b</p>"),
            "> [!IMPORTANT]\n> a\n>\n> b",
        );
    }

    public function testAnAlertWithNoText(): void
    {
        $this->assertConverts($this->alert('caution', ''), '> [!CAUTION]');
    }

    public function testALazyLineStaysInTheAlert(): void
    {
        $this->assertConverts($this->alert('note', '<p>a<br>b</p>'), "> [!NOTE]\n> a\nb");
    }

    public function testAlertsFollowedByOtherBlocks(): void
    {
        $this->assertConverts(
            $this->alert('note', '<p>a</p>') . "\n<p>b</p>",
            "> [!NOTE]\n> a\n\nb",
        );
        $this->assertConverts(
            $this->alert('note', '<p>a</p>') . "\n<blockquote>\n<p>b</p>\n</blockquote>",
            "> [!NOTE]\n> a\n\n> b",
        );
    }

    public function testAnAlertInsideAListItem(): void
    {
        $this->assertConverts(
            "<ul>\n<li>\n" . $this->alert('warning', '<p>a</p>') . "\n</li>\n</ul>",
            "- > [!WARNING]\n  > a",
        );
    }

    public function testThingsThatLookLikeAlertsButAreNot(): void
    {
        $this->assertAllConvert([
            'text on the same line as the marker' => ['> [!NOTE] a', "<blockquote>\n<p>[!NOTE] a</p>\n</blockquote>"],
            'the marker is not on the first line' => ["> a\n> [!NOTE]", "<blockquote>\n<p>a<br>[!NOTE]</p>\n</blockquote>"],
            'an unknown kind' => ["> [!INFO]\n> a", "<blockquote>\n<p>[!INFO]<br>a</p>\n</blockquote>"],
            'no quote around it' => ["[!NOTE]\na", '<p>[!NOTE]<br>a</p>'],
            'a space inside the marker' => ["> [! NOTE]\n> a", "<blockquote>\n<p>[! NOTE]<br>a</p>\n</blockquote>"],
        ]);
    }

    public function testAnAlertsKindCannotInjectAnythingIntoTheClass(): void
    {
        $html = $this->html("> [!NOTE\" onclick=\"x]\n> a");

        $this->assertStringNotContainsString('markdown-alert', $html);
        $this->assertSafe($html);
    }

    public function testInlineStylesGiveEachKindItsColor(): void
    {
        $colors = ['note' => '#0969da', 'tip' => '#1a7f37', 'important' => '#8250df', 'warning' => '#9a6700', 'caution' => '#cf222e'];

        foreach ($colors as $kind => $color) {
            $html = $this->html('> [!' . strtoupper($kind) . "]\n> a", ['inlineStyles' => true]);

            $this->assertStringContainsString('style="border-left:4px solid ' . $color . ';margin:0 0 16px;padding:0 1em"', $html, $kind);
            $this->assertStringContainsString('style="color:' . $color . ';font-weight:600"', $html, $kind);
            $this->assertSafe($html, $kind);
        }
    }
}
