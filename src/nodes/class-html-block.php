<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * An HTML element whose opening and closing tags are on separate lines.
 *
 * The converter keeps it only when the `html` setting is `sanitize`, and reads
 * the content between the tags as Markdown.
 *
 * @internal
 */
final class HtmlBlock extends Node
{
    /**
     * Creates an HTML block that holds other blocks.
     *
     * @param string $tag The element name: `details`, `summary`, `div`, or `p`,
     *     in lowercase.
     * @param string $attributes The attributes to keep, with a leading space,
     *     or an empty string.
     * @param list<Node> $children The blocks between the opening and closing
     *     tags.
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $attributes,
        public readonly array $children,
    ) {
    }
}
