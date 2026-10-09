<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter;

/**
 * Escapes text for HTML output. Text from the Markdown reaches the output only
 * through `text()` or `attr()`.
 */
final class Html
{
    /**
     * Escapes text for use between tags.
     *
     * @param string $text The text to escape.
     *
     * @return string The escaped text.
     */
    public static function text(string $text): string
    {
        return htmlspecialchars($text, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escapes text for use inside a quoted attribute.
     *
     * @param string $text The text to escape.
     *
     * @return string The escaped text.
     */
    public static function attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
