<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * An HTML element that opens and closes on one line, such as
 * `<summary>Title</summary>`.
 *
 * The converter keeps it only when the `html` setting is `sanitize`.
 *
 * @internal
 */
final class HtmlLine extends Node
{
    /**
     * Creates an HTML element that opens and closes on one line.
     *
     * @param string $tag The element name: `details`, `summary`, `div`, or `p`,
     *     in lowercase.
     * @param string $attributes The attributes to keep, with a leading space,
     *     or an empty string.
     * @param string $raw The text between the tags, as written.
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $attributes,
        public readonly string $raw,
    ) {
    }
}
