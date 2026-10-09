<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A heading, written with `#` characters or, when the `setextHeadings` setting
 * is on, underlined with `=` or `-`.
 *
 * @internal
 */
final class Heading extends Node
{
    /**
     * Creates a heading with its level and text.
     *
     * @param int $level The heading level from 1 to 6, including the
     *     `headingOffset` setting.
     * @param string $raw The text of the heading, as written.
     */
    public function __construct(
        public readonly int $level,
        public readonly string $raw,
    ) {
    }
}
