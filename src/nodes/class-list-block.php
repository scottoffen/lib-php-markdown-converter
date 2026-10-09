<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A bulleted or numbered list.
 *
 * The class is named `ListBlock` because `list` is a reserved word in PHP.
 *
 * @internal
 */
final class ListBlock extends Node
{
    /**
     * Creates a list from its items.
     *
     * @param bool $ordered True if the items are numbered; false if they have
     *     bullets.
     * @param int $start The number of the first item, as written. Only a
     *     numbered list shows it.
     * @param bool $loose True if the items are written in paragraphs, because
     *     blank lines separate the items or the blocks inside them; false
     *     otherwise.
     * @param list<list<Node>> $items The blocks inside each item.
     */
    public function __construct(
        public readonly bool $ordered,
        public readonly int $start,
        public readonly bool $loose,
        public readonly array $items,
    ) {
    }
}
