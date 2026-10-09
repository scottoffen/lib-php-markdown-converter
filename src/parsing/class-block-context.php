<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

/**
 * Holds the state of one `BlockParser` run: the number of lines searched for
 * closing tags and the reference link definitions found so far.
 *
 * `BlockParser` makes a new instance for each text, so nothing one text leaves
 * behind can affect another, and the parser itself never changes.
 *
 * @internal
 */
final class BlockContext
{
    /**
     * The number of lines read so far while searching for closing tags.
     * `BlockParser` stops searching at `MAX_TAG_SEARCH_LINES`, so many unclosed
     * tags can't slow it down.
     */
    public int $tagSearchLines = 0;

    /**
     * @var array<string, array{url: string, title: string|null}> The
     *     definitions found so far, keyed by `ReferenceLabel::normalize()`.
     */
    public array $definitions = [];

    /**
     * Records a reference link definition. If the label is already defined, the
     * first definition stays.
     *
     * @param string $label The normalized label, from
     *     `ReferenceLabel::normalize()`.
     * @param string $url The destination of the link.
     * @param string|null $title The title of the link, or null if it has none.
     */
    public function define(string $label, string $url, ?string $title): void
    {
        $this->definitions[$label] ??= ['url' => $url, 'title' => $title];
    }
}
