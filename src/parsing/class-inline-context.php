<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

/**
 * Holds the state of reading the text of one block with `InlineParser`: the
 * counts of brackets and tags tried, the tag depth, and the reference link
 * definitions.
 *
 * `InlineParser` makes a new instance for each block, so the limits apply to
 * one block, not to the whole text, and the parser itself never changes.
 *
 * @internal
 */
final class InlineContext
{
    /**
     * The number of brackets, tags, and diffs tried so far. `InlineParser`
     * stops trying at `MAX_BRACKET_ATTEMPTS`, so the work can't grow with the
     * square of the text's length.
     */
    public int $attempts = 0;

    /**
     * The number of places that could start math and were tried so far,
     * whether or not they turned out to be math. `InlineParser` stops trying at
     * `MAX_MATH_ATTEMPTS`, so the work stays bounded.
     */
    public int $mathAttempts = 0;

    /** The depth of the HTML tags around the current position. */
    public int $tagDepth = 0;

    /**
     * Creates the state for one block, with the reference link definitions of
     * the whole text.
     *
     * @param array<string, array{url: string, title: string|null}> $definitions
     *     The reference link definitions of the whole text, keyed by
     *     `ReferenceLabel::normalize()`.
     */
    public function __construct(public readonly array $definitions)
    {
    }
}
