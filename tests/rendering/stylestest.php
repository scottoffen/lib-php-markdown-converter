<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Rendering;

use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\Rendering\Styles;

final class StylesTest extends TestCase
{
    public function testNothingIsAddedWhenStylesAreOff(): void
    {
        $styles = new Styles(false);

        foreach (['blockquote', 'pre', 'table', 'cell', 'image', 'ins', 'del', 'alert', 'alert-title'] as $part) {
            $this->assertSame('', $styles->attribute($part, 'note'), $part);
        }
    }

    public function testEveryPartHasAStyleWhenStylesAreOn(): void
    {
        $styles = new Styles(true);

        $this->assertSame(' style="border-left:4px solid #c3c4c7;margin:0 0 16px;padding:0 1em;color:#50575e"', $styles->attribute('blockquote'));
        $this->assertSame(' style="overflow:auto;padding:8px 12px;background:#f6f7f7;border:1px solid #dcdcde"', $styles->attribute('pre'));
        $this->assertSame(' style="border-collapse:collapse;margin:0 0 16px"', $styles->attribute('table'));
        $this->assertSame(' style="border:1px solid #c3c4c7;padding:4px 8px"', $styles->attribute('cell'));
        $this->assertSame(' style="max-width:100%;height:auto"', $styles->attribute('image'));
        $this->assertSame(' style="background:#ccffd8"', $styles->attribute('ins'));
        $this->assertSame(' style="background:#ffd7d5"', $styles->attribute('del'));
    }

    public function testAlertsTakeTheirColorFromTheirKind(): void
    {
        $styles = new Styles(true);
        $colors = ['note' => '#0969da', 'tip' => '#1a7f37', 'important' => '#8250df', 'warning' => '#9a6700', 'caution' => '#cf222e'];

        foreach ($colors as $kind => $color) {
            $this->assertSame(' style="border-left:4px solid ' . $color . ';margin:0 0 16px;padding:0 1em"', $styles->attribute('alert', $kind), $kind);
            $this->assertSame(' style="color:' . $color . ';font-weight:600"', $styles->attribute('alert-title', $kind), $kind);
        }
    }

    public function testAnAlertWithNoKnownKindGetsTheNeutralColor(): void
    {
        $this->assertSame(' style="border-left:4px solid #c3c4c7;margin:0 0 16px;padding:0 1em"', (new Styles(true))->attribute('alert'));
    }

    public function testAPartWithNoStyleAddsNothing(): void
    {
        $this->assertSame('', (new Styles(true))->attribute('paragraph'));
    }
}
