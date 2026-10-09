<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Parsing;

use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\Parsing\ReferenceLabel;

/**
 * Tests how the labels of reference links are normalized. A reference link
 * finds its definition by label, so two labels that a reader would call the
 * same must come out the same, and two that differ in anything that matters
 * must not.
 */
final class ReferenceLabelTest extends TestCase
{
    public function testCaseDoesNotCount(): void
    {
        $this->assertSame('the docs', ReferenceLabel::normalize('The Docs'));
        $this->assertSame('the docs', ReferenceLabel::normalize('THE DOCS'));
    }

    public function testCaseDoesNotCountOutsideAscii(): void
    {
        $this->assertSame("\u{E9}cole", ReferenceLabel::normalize("\u{C9}COLE"));
    }

    public function testRunsOfWhiteSpaceBecomeOneSpace(): void
    {
        $this->assertSame('a b', ReferenceLabel::normalize('a   b'));
        $this->assertSame('a b', ReferenceLabel::normalize("a\t\nb"));
        $this->assertSame('a b c', ReferenceLabel::normalize("a \n b \t c"));
    }

    public function testWhiteSpaceThatIsNotAsciiCountsToo(): void
    {
        $this->assertSame('a b', ReferenceLabel::normalize("a\u{A0}b"), 'A no-break space.');
        $this->assertSame('a b', ReferenceLabel::normalize("a\u{3000}b"), 'An ideographic space.');
    }

    public function testWhiteSpaceAtTheEndsIsRemoved(): void
    {
        $this->assertSame('a', ReferenceLabel::normalize('  a  '));
        $this->assertSame('a', ReferenceLabel::normalize("\n\ta\n"));
    }

    public function testALabelOfNothingButSpacesIsEmpty(): void
    {
        $this->assertSame('', ReferenceLabel::normalize(''));
        $this->assertSame('', ReferenceLabel::normalize("  \t\n  "));
    }

    public function testWordsThatDifferStayDifferent(): void
    {
        $this->assertNotSame(ReferenceLabel::normalize('the docs'), ReferenceLabel::normalize('thedocs'), 'The space between words is kept.');
        $this->assertNotSame(ReferenceLabel::normalize('docs'), ReferenceLabel::normalize('doc'));
        $this->assertSame('a-b', ReferenceLabel::normalize('A-B'), 'Punctuation is kept.');
    }

    public function testItIsTheSameWhenDoneTwice(): void
    {
        $once = ReferenceLabel::normalize("  The   DOCS\t");

        $this->assertSame($once, ReferenceLabel::normalize($once));
    }
}
