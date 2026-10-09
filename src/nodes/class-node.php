<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * Base class for the blocks that `BlockParser` reads.
 *
 * `BlockParser` builds a tree of these blocks and `HtmlRenderer` writes it out.
 * The classes in this folder are all that the two share.
 *
 * @internal
 */
abstract class Node
{
    /**
     * True if a blank line came before this block in its container; false
     * otherwise.
     *
     * `BlockParser` sets it while it reads the lines. A list uses it to tell a
     * loose list from a tight one.
     */
    public bool $blank = false;
}
