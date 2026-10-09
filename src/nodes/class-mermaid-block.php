<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A fenced `mermaid` block: the source of a diagram that a page renders with
 * the Mermaid library.
 *
 * @internal
 */
final class MermaidBlock extends Node
{
    /**
     * Creates a block of diagram source.
     *
     * @param string $source The diagram source, as written. Mermaid reads
     *     indentation in some diagrams, so the parser keeps it.
     */
    public function __construct(public readonly string $source)
    {
    }
}
