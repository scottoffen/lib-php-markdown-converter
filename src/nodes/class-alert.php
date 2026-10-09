<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A block quote whose first line is an alert marker, such as `[!NOTE]`.
 *
 * @internal
 */
final class Alert extends Node
{
    /**
     * Creates an alert of one kind, with the blocks that follow its marker.
     *
     * @param AlertKind $kind The kind of alert.
     * @param list<Node> $children The blocks inside the alert, after the marker
     *     line.
     */
    public function __construct(
        public readonly AlertKind $kind,
        public readonly array $children,
    ) {
    }
}
