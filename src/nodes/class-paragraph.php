<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A paragraph, or the text of an item in a tight list.
 *
 * @internal
 */
final class Paragraph extends Node
{
    /**
     * Creates a paragraph of text.
     *
     * @param string $raw The text as written, which the inline parser reads for
     *     emphasis, links, and other syntax. If `$plain` is true, the final
     *     text instead, which the renderer escapes and writes as it is.
     * @param bool $plain If true, `$raw` is final text that the inline parser
     *     doesn't read, as for content nested deeper than `maxDepth`. If false,
     *     the inline parser reads `$raw`.
     */
    public function __construct(
        public readonly string $raw,
        public readonly bool $plain = false,
    ) {
    }
}
