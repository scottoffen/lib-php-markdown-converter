<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

/**
 * Tracks where `InlineParser` is in one piece of text: how far it has read, the
 * plain text waiting to be written, and the tokens found so far.
 *
 * Each `InlineParser` method that reads one kind of syntax moves the scan
 * along.
 *
 * @internal
 */
final class InlineScan
{
    /** The position of the next character to read. */
    public int $i = 0;

    /** The length of the text in bytes. */
    public readonly int $length;

    /** Plain text read since the last token, before escaping. */
    public string $buffer = '';

    /**
     * The tokens found so far: plain text, finished HTML, and delimiter runs.
     *
     * @var list<array<string, mixed>>
     */
    public array $tokens = [];

    /**
     * The number of delimiter runs that have become tokens. `InlineParser`
     * stops making them at `MAX_DELIMITERS`, so emphasis matching stays fast.
     */
    public int $delimiters = 0;

    /**
     * @var array<int, true> The lengths of backtick runs that have no matching
     *     run after them.
     */
    public array $noCloser = [];

    /**
     * True once no `-->` is left in the text, so later `<!--` markers don't
     * search for one.
     */
    public bool $noCommentEnd = false;

    /**
     * The `[` and `![` that haven't been closed yet, innermost last.
     *
     * Each entry is an array with the position of its `[`, whether it is an
     * image, and the index of the token that stands in for its `[` or `![`
     * until it is closed. An entry is false for a bracket that can't become a
     * link. The false entry keeps the stack balanced but stores nothing.
     *
     * @var list<array{pos: int, image: bool, token: int}|false>
     */
    public array $brackets = [];

    /**
     * The number of entries in `$brackets` that can no longer become links.
     *
     * After a link is made, every `[` still open below it is out, because a
     * link can't contain a link. The entries below this position are those
     * brackets. Images aren't affected.
     */
    public int $linkFloor = 0;

    /**
     * Creates a scan at the start of a piece of text.
     *
     * @param string $text The text to scan.
     * @param bool $allowLinks True if links and autolinks can be made here;
     *     false inside the text of a link.
     * @param InlineContext $context The state of the block that the text
     *     belongs to.
     */
    public function __construct(
        public readonly string $text,
        public readonly bool $allowLinks,
        public readonly InlineContext $context,
    ) {
        $this->length = strlen($text);
    }

    /**
     * Turns the waiting plain text into a token.
     */
    public function flush(): void
    {
        if ($this->buffer !== '') {
            $this->tokens[] = ['t' => 'text', 'v' => $this->buffer];
            $this->buffer = '';
        }
    }

    /**
     * Adds finished HTML after any plain text that is waiting.
     *
     * @param string $html The HTML to add.
     * @param string|null $alt What to write instead if this HTML ends up inside
     *     the text of a link, where it can't be a link of its own. If null, the
     *     tags of any link are removed and the text inside stays.
     */
    public function html(string $html, ?string $alt = null): void
    {
        $this->flush();
        $this->tokens[] = $alt === null ? ['t' => 'html', 'v' => $html] : ['t' => 'html', 'v' => $html, 'alt' => $alt];
    }

    /**
     * Keeps text as plain text and moves past part of the input.
     *
     * @param string $text The text to keep.
     * @param int $length The number of bytes of the input to move past.
     */
    public function keep(string $text, int $length): void
    {
        $this->buffer .= $text;
        $this->i += $length;
    }
}
