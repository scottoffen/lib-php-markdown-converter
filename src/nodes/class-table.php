<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * A GitHub-style table. Each row has as many cells as the header row.
 *
 * @internal
 */
final class Table extends Node
{
    /**
     * Creates a table from its alignments, header cells, and rows.
     *
     * @param list<string|null> $alignments The alignment of each column:
     *     `"left"`, `"center"`, `"right"`, or null for none.
     * @param list<string> $head The text of each header cell, as written.
     * @param list<list<string>> $rows The text of each cell in each body row,
     *     as written.
     */
    public function __construct(
        public readonly array $alignments,
        public readonly array $head,
        public readonly array $rows,
    ) {
    }
}
