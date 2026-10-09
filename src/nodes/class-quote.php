<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A block quote.
 *
 * @internal
 */
final class Quote extends Node
{
    /**
     * Creates a block quote that holds the given blocks.
     *
     * @param list<Node> $children The blocks inside the quote.
     */
    public function __construct(public readonly array $children)
    {
    }
}
