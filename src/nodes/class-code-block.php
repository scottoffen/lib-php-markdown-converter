<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A fenced or indented block of code.
 *
 * @internal
 */
final class CodeBlock extends Node
{
    /**
     * Creates a code block with its language and text.
     *
     * @param string $language The first word after the opening fence, if it is
     *     safe to use as a class name. Otherwise an empty string.
     * @param string $text The code, without a trailing newline.
     */
    public function __construct(
        public readonly string $language,
        public readonly string $text,
    ) {
    }
}
