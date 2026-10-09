<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A block of display math: the lines between two `$$` lines, or a fenced
 * `math` block.
 *
 * @internal
 */
final class MathBlock extends Node
{
    /**
     * Creates a block of math.
     *
     * @param string $tex The TeX, with no delimiters and no blank space at
     *     either end.
     */
    public function __construct(public readonly string $tex)
    {
    }
}
