<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter;

/**
 * Writes a piece of math as HTML in the format that the `mathFormat` setting
 * names.
 *
 * The converter doesn't render math. It finds the TeX, escapes it, and writes
 * it where a page's own library, such as KaTeX or MathJax, can find it. The TeX
 * reaches the output only through `Html::text()`, so it can't add markup of its
 * own.
 *
 * @internal
 */
final class Math
{
    /**
     * Writes math as HTML.
     *
     * @param string $tex The TeX, as written between the delimiters.
     * @param bool $display True for display math, which sits on a line of its
     *     own; false for math inside a line of text.
     * @param bool $block True if the math is a block of its own, from a `$$`
     *     block or a `math` fence; false if it is inside a paragraph.
     * @param string $format The `mathFormat` setting.
     *
     * @return string The HTML.
     */
    public static function html(string $tex, bool $display, bool $block, string $format): string
    {
        $text = Html::text($tex);

        if ($format === Options::MATH_DELIMITERS) {
            $delimiter = $display ? '$$' : '$';

            if ($block) {
                return '<p>' . $delimiter . "\n" . $text . "\n" . $delimiter . '</p>';
            }

            return $delimiter . $text . $delimiter;
        }

        $tag = $block ? 'div' : 'span';

        if ($format === Options::MATH_CLASS) {
            return '<' . $tag . ' class="' . ($display ? 'math-display' : 'math-inline') . '">' . $text . '</' . $tag . '>';
        }

        $open = $display ? '\\[' : '\\(';
        $close = $display ? '\\]' : '\\)';

        return '<' . $tag . ' class="math ' . ($display ? 'display' : 'inline') . '">' . $open . $text . $close . '</' . $tag . '>';
    }
}
