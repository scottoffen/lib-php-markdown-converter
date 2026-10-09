<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

/**
 * Recognizes GitHub-style tables and splits their rows into cells.
 *
 * @internal
 */
final class TableParser
{
    /**
     * Returns the column alignments when two lines start a table, or null when
     * they don't.
     *
     * @param string $header The line that might be the header row.
     * @param string $delimiter The line that might be the delimiter row, such
     *     as `| --- | :-: |`.
     *
     * @return list<string|null>|null The alignment of each column, which is
     *     `"left"`, `"center"`, `"right"`, or null for none. Null if the lines
     *     don't start a table.
     */
    public function alignments(string $header, string $delimiter): ?array
    {
        if (!str_contains($header, '|') || !str_contains($delimiter, '|') || !str_contains($delimiter, '-')) {
            return null;
        }

        $cells = $this->cells($delimiter);

        if ($cells === [] || count($cells) !== count($this->cells($header))) {
            return null;
        }

        $alignments = [];

        foreach ($cells as $cell) {
            if (preg_match('/^(:)?-+(:)?$/', $cell, $m) !== 1) {
                return null;
            }

            $left = ($m[1] ?? '') === ':';
            $right = ($m[2] ?? '') === ':';

            $alignments[] = $left && $right ? 'center' : ($left ? 'left' : ($right ? 'right' : null));
        }

        return $alignments;
    }

    /**
     * Splits a row into its cells. A pipe written as `\|` stays inside its
     * cell.
     *
     * @param string $row One line of the table.
     *
     * @return list<string> The cells, with surrounding whitespace removed and
     *     each `\|` replaced by `|`.
     */
    public function cells(string $row): array
    {
        $row = trim($row);

        if (str_starts_with($row, '|')) {
            $row = substr($row, 1);
        }

        if (str_ends_with($row, '|') && !str_ends_with($row, '\\|')) {
            $row = substr($row, 0, -1);
        }

        $parts = preg_split('/(?<!\\\\)\|/', $row);

        if ($parts === false) {
            return [];
        }

        return array_map(
            static fn (string $cell): string => str_replace('\\|', '|', trim($cell)),
            $parts,
        );
    }

    /**
     * Makes a row as wide as the table by adding empty cells to a short row and
     * dropping extra cells.
     *
     * @param list<string> $cells The cells of the row.
     * @param int $width The number of columns in the table.
     *
     * @return list<string> The cells, exactly `$width` of them.
     */
    public function fit(array $cells, int $width): array
    {
        $cells = array_slice($cells, 0, $width);

        while (count($cells) < $width) {
            $cells[] = '';
        }

        return $cells;
    }
}
