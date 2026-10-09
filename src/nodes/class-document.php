<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * The result of reading one text: its blocks and its reference link
 * definitions.
 *
 * The two are kept together because a definition can appear after the link that
 * uses it. The parser must read every block before the text inside the blocks
 * can be read.
 *
 * @internal
 */
final class Document
{
    /**
     * Creates a document from the blocks and definitions that the parser read.
     *
     * @param list<Node> $nodes The blocks of the text, in order.
     * @param array<string, array{url: string, title: string|null}> $definitions
     *     The reference link definitions, keyed by
     *     `ReferenceLabel::normalize()`.
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $definitions,
    ) {
    }
}
