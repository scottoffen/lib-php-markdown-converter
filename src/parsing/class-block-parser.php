<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

use ScottOffen\MarkdownConverter\Nodes\Alert;
use ScottOffen\MarkdownConverter\Nodes\AlertKind;
use ScottOffen\MarkdownConverter\Nodes\CodeBlock;
use ScottOffen\MarkdownConverter\Nodes\Document;
use ScottOffen\MarkdownConverter\Nodes\Heading;
use ScottOffen\MarkdownConverter\Nodes\HtmlBlock;
use ScottOffen\MarkdownConverter\Nodes\HtmlLine;
use ScottOffen\MarkdownConverter\Nodes\ListBlock;
use ScottOffen\MarkdownConverter\Nodes\MathBlock;
use ScottOffen\MarkdownConverter\Nodes\MermaidBlock;
use ScottOffen\MarkdownConverter\Nodes\Node;
use ScottOffen\MarkdownConverter\Nodes\Paragraph;
use ScottOffen\MarkdownConverter\Nodes\Quote;
use ScottOffen\MarkdownConverter\Nodes\Rule;
use ScottOffen\MarkdownConverter\Nodes\Table;
use ScottOffen\MarkdownConverter\Options;

/**
 * Reads Markdown into a tree of blocks: headings, paragraphs, lists, quotes,
 * alerts, code, and tables. `HtmlRenderer` writes the tree as HTML.
 *
 * A quote or list item contains blocks of its own. The parser removes its
 * prefix from each line and reads what is left the same way.
 *
 * The parser never changes after it is made. What it keeps while it reads a
 * text is in a `BlockContext` made for that text, so one parser can read any
 * number of texts in any order, and what it finds in one doesn't affect
 * another.
 *
 * @internal
 */
final class BlockParser
{
    /**
     * A line that holds only `$$`, which opens or closes a block of math when
     * the `math` setting is on.
     */
    private const MATH_FENCE = '/^ {0,3}\$\$[ \t]*$/';

    /**
     * The most lines that the search for closing tags can read in one text. The
     * limit keeps many unclosed tags from making the search slow.
     */
    private const MAX_TAG_SEARCH_LINES = 100000;

    /**
     * A regular expression for a reference link definition, such as
     * `[label]: https://example.com "Title"`.
     */
    private const DEFINITION = <<<'REGEX'
    /^ {0,3}\[((?:[^\[\]\\]|\\.){1,999})\]:[ \t]*(?:<([^<>\n]*)>|(\S+))(?:[ \t]+(?:"((?:[^"\\]|\\.)*)"|'((?:[^'\\]|\\.)*)'|\(((?:[^()\\]|\\.)*)\)))?[ \t]*$/
    REGEX;

    /**
     * Creates a block parser with the given settings.
     *
     * @param Options $options The settings.
     * @param TableParser $tables The parser for tables.
     */
    public function __construct(
        private readonly Options $options,
        private readonly TableParser $tables,
    ) {
    }

    /**
     * Reads the lines into a tree of blocks and collects the reference link
     * definitions on the way.
     *
     * The text inside each block stays as written. The inline parser reads it
     * when the renderer writes the tree, so a reference link can be defined
     * after it is used.
     *
     * @param string $markdown The text, already cleaned: valid UTF-8, `\n` line
     *     endings, and no tabs in indentation.
     *
     * @return Document The blocks and the definitions.
     */
    public function parse(string $markdown): Document
    {
        $context = new BlockContext();
        $nodes = $this->blocks(explode("\n", rtrim($markdown, "\n")), 0, $context);

        return new Document($nodes, $context->definitions);
    }

    /**
     * Reads a list of lines as blocks. Lines nested deeper than `maxDepth`
     * become one paragraph of plain text.
     *
     * @param list<string> $lines The lines to read.
     * @param int $depth The nesting depth of the lines, starting at 0.
     * @param BlockContext $context The state of the current text.
     *
     * @return list<Node> The blocks.
     */
    private function blocks(array $lines, int $depth, BlockContext $context): array
    {
        if ($depth > $this->options->maxDepth) {
            $text = trim(implode(' ', array_map('trim', $lines)));

            return $text === '' ? [] : [new Paragraph($text, true)];
        }

        $nodes = [];
        $blank = false;
        $i = 0;

        while ($i < count($lines)) {
            if (trim($lines[$i]) === '') {
                $blank = true;
                $i++;

                continue;
            }

            $node = $this->block($lines, $i, $depth, $context);

            if ($node === null) {
                continue;
            }

            $node->blank = $blank && $nodes !== [];
            $nodes[] = $node;
            $blank = false;
        }

        return $nodes;
    }

    /**
     * Reads one block that starts at `$lines[$i]` and moves `$i` past it.
     *
     * The order of the checks matters, because some lines match more than one.
     * For example, `* * *` is a rule and not a list item, so the rule check
     * comes before the list check, and `>>>` is checked before `>`.
     *
     * The method takes the lines by reference because it puts the text after a
     * comment on its last line back as a line of its own.
     *
     * @param list<string> $lines All the lines being read.
     * @param int $i The position of the line to read from. The method moves it
     *     past the block.
     * @param int $depth The nesting depth of the lines, starting at 0.
     * @param BlockContext $context The state of the current text.
     *
     * @return Node|null The block, or null if the lines produce no output, such
     *     as a comment.
     */
    private function block(array &$lines, int &$i, int $depth, BlockContext $context): ?Node
    {
        $line = $lines[$i];

        if (preg_match('/^ {0,3}<!--/', $line) === 1) {
            $this->skipComment($lines, $i);

            return null;
        }

        // With the `strip` setting, a line that holds only tags produces no
        // output.
        if ($this->options->html === Options::HTML_STRIP && $this->isOnlyTags($line)) {
            $i++;

            return null;
        }

        if ($this->options->html === Options::HTML_SANITIZE) {
            $html = $this->htmlBlock($lines, $i, $depth, $context);

            if ($html !== false) {
                return $html;
            }
        }

        if (preg_match('/^( {0,3})(`{3,}|~{3,})[ \t]*(.*)$/', $line, $m) === 1 && ($m[2][0] !== '`' || !str_contains($m[3], '`'))) {
            return $this->fencedCode($lines, $i, strlen($m[1]), $m[2], $m[3]);
        }

        if ($this->options->math && preg_match(self::MATH_FENCE, $line) === 1) {
            $end = $this->mathBlockEnd($lines, $i);

            if ($end !== null) {
                $tex = trim(implode("\n", array_slice($lines, $i + 1, $end - $i - 1)));
                $i = $end + 1;

                return new MathBlock($tex);
            }
        }

        if (preg_match('/^ {0,3}(#{1,6})(?:[ \t]+(.*))?$/', $line, $m) === 1) {
            $i++;
            $text = trim($m[2] ?? '');
            $text = trim((string) preg_replace('/(?:^|[ \t]+)#+$/', '', $text));
            $level = min(6, strlen($m[1]) + $this->options->headingOffset);

            return new Heading($level, $text);
        }

        if ($this->isRule($line)) {
            $i++;

            return new Rule();
        }

        if ($this->options->fencedQuotes && preg_match('/^ {0,3}>>>(?:[ \t]*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\])?[ \t]*$/i', $line, $m) === 1) {
            return $this->fencedQuote($lines, $i, $depth, $m[1] ?? '', $context);
        }

        if (preg_match('/^ {0,3}>/', $line) === 1) {
            return $this->quote($lines, $i, $depth, $context);
        }

        $marker = $this->listMarker($line);

        if ($marker !== null) {
            return $this->listBlock($lines, $i, $depth, $marker, $context);
        }

        if ($this->options->indentedCode && strspn($line, ' ') >= 4) {
            return $this->indentedCode($lines, $i);
        }

        if ($i + 1 < count($lines)) {
            $alignments = $this->tables->alignments($line, $lines[$i + 1]);

            if ($alignments !== null) {
                return $this->table($lines, $i, $alignments);
            }
        }

        if ($this->options->referenceLinks && $this->definition($line, $context)) {
            $i++;

            return null;
        }

        return $this->paragraph($lines, $i);
    }

    /**
     * Skips an HTML comment, which can start and end on different lines. If
     * text follows the comment on its last line, the method puts that text back
     * as a line of its own.
     *
     * @param list<string> $lines The lines being read. The method can change
     *     the line where the comment ends.
     * @param int $i The position of the line where the comment starts. The
     *     method moves it past the comment.
     */
    private function skipComment(array &$lines, int &$i): void
    {
        $count = count($lines);
        $text = $lines[$i];
        $pos = strpos($text, '-->', (int) strpos($text, '<!--') + 4);
        $at = $i;

        while ($pos === false) {
            $at++;

            if ($at >= $count) {
                $i = $count;

                return;
            }

            $text = $lines[$at];
            $pos = strpos($text, '-->');
        }

        $rest = substr($text, $pos + 3);

        if (trim($rest) === '') {
            $i = $at + 1;

            return;
        }

        // The parser reads the text after the comment on its last line as a
        // line of its own.
        $lines[$at] = ltrim($rest);
        $i = $at;
    }

    /**
     * Reads a fenced code block, up to its closing fence or the end of the
     * text.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the opening fence. The method moves it past
     *     the block.
     * @param int $indent The number of spaces that indent the opening fence.
     *     The method removes that many from each line of code.
     * @param string $fence The opening fence, which is three or more backticks
     *     or tildes.
     * @param string $info The text after the opening fence.
     *
     * @return CodeBlock|MathBlock|MermaidBlock The block. With the `math`
     *     setting on, a fence whose first word is `math` makes a `MathBlock`,
     *     unless the math is empty or longer than `mathMaxLength`. With the
     *     `mermaid` setting on, a fence whose first word is `mermaid` makes a
     *     `MermaidBlock`, unless the diagram is empty.
     */
    private function fencedCode(array $lines, int &$i, int $indent, string $fence, string $info): CodeBlock|MathBlock|MermaidBlock
    {
        $lang = '';
        $words = preg_split('/\s+/', trim(self::unescape($info)));

        if (is_array($words) && isset($words[0]) && preg_match('/^[A-Za-z0-9_+.#-]{1,40}$/', $words[0]) === 1) {
            $lang = $words[0];
        }

        $closing = '/^ {0,3}' . preg_quote($fence[0], '/') . '{' . strlen($fence) . ',}[ \t]*$/';
        $body = [];
        $count = count($lines);
        $i++;

        while ($i < $count) {
            if (preg_match($closing, $lines[$i]) === 1) {
                $i++;

                break;
            }

            $body[] = substr($lines[$i], min($indent, strspn($lines[$i], ' ')));
            $i++;
        }

        $code = implode("\n", $body);

        if ($this->options->math && $lang === 'math' && trim($code) !== '' && strlen($code) <= $this->options->mathMaxLength) {
            return new MathBlock(trim($code));
        }

        if ($this->options->mermaid && $lang === 'mermaid' && trim($code) !== '') {
            // The parser keeps the indentation of each line, because Mermaid
            // reads it in some diagrams, and drops only blank lines at either
            // end.
            return new MermaidBlock(rtrim(ltrim($code, "\n")));
        }

        return new CodeBlock($lang, $code);
    }

    /**
     * Finds the line that closes a block of math.
     *
     * The method reads forward from an opening `$$` line, and stops at the
     * first line that holds only `$$`. It gives up if the lines are longer than
     * `mathMaxLength` together, if the block is empty, or if there is no closing
     * line. The `$$` line is then ordinary text, and a `$$` that has no match
     * can't swallow the rest of the text.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the opening `$$` line.
     *
     * @return int|null The position of the closing line, or null if the
     *     opening line doesn't start a block of math.
     */
    private function mathBlockEnd(array $lines, int $i): ?int
    {
        $count = count($lines);
        $length = 0;
        $filled = false;

        for ($j = $i + 1; $j < $count; $j++) {
            if (preg_match(self::MATH_FENCE, $lines[$j]) === 1) {
                return $filled ? $j : null;
            }

            $length += strlen($lines[$j]) + 1;

            if ($length > $this->options->mathMaxLength) {
                return null;
            }

            $filled = $filled || trim($lines[$j]) !== '';
        }

        return null;
    }

    /**
     * Reads a block quote, or an alert if the quote starts with an alert
     * marker.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the first line of the quote. The method
     *     moves it past the quote.
     * @param int $depth The nesting depth of the lines, starting at 0.
     * @param BlockContext $context The state of the current text.
     *
     * @return Quote|Alert The block.
     */
    private function quote(array $lines, int &$i, int $depth, BlockContext $context): Quote|Alert
    {
        $inner = [];
        $count = count($lines);
        $fence = null;

        while ($i < $count) {
            $line = $lines[$i];

            $content = $this->quoteContent($line);

            if ($content !== null) {
                $inner[] = $content;
                $fence = $this->fenceAfter($fence, $content);
                $i++;

                continue;
            }

            // A line that continues the paragraph above stays in the quote,
            // even without a `>`. That doesn't apply if a code fence is open or
            // the last line isn't paragraph text.
            if ($fence === null && $inner !== [] && $this->paragraphIsOpenAfter(end($inner)) && trim($line) !== '' && !$this->interrupts($line) && !($this->options->math && $this->startsMathBlock($lines, $i)) && $this->listMarker($line) === null) {
                $inner[] = $this->lazyLine($line);
                $i++;

                continue;
            }

            break;
        }

        if ($inner !== [] && preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*$/i', $inner[0], $m) === 1) {
            return new Alert(AlertKind::from(strtolower($m[1])), $this->blocks(array_slice($inner, 1), $depth + 1, $context));
        }

        return new Quote($this->blocks($inner, $depth + 1, $context));
    }

    /**
     * Reads the lines between two `>>>` lines, or up to the end of the text if
     * there is no second one.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the opening `>>>` line. The method moves it
     *     past the quote.
     * @param int $depth The nesting depth of the lines, starting at 0.
     * @param string $kind The alert kind from the opening line, or an empty
     *     string for an ordinary quote.
     * @param BlockContext $context The state of the current text.
     *
     * @return Quote|Alert The block.
     */
    private function fencedQuote(array $lines, int &$i, int $depth, string $kind, BlockContext $context): Quote|Alert
    {
        $inner = [];
        $count = count($lines);
        $fence = null;

        for ($i++; $i < $count; $i++) {
            if ($fence === null && preg_match('/^ {0,3}>>>[ \t]*$/', $lines[$i]) === 1) {
                $i++;

                break;
            }

            $fence = $this->fenceAfter($fence, $lines[$i]);
            $inner[] = $lines[$i];
        }

        if ($kind !== '') {
            return new Alert(AlertKind::from(strtolower($kind)), $this->blocks($inner, $depth + 1, $context));
        }

        return new Quote($this->blocks($inner, $depth + 1, $context));
    }

    /**
     * Reads a run of lines indented four spaces or more as code.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the first indented line. The method moves
     *     it past the block.
     *
     * @return CodeBlock The block.
     */
    private function indentedCode(array $lines, int &$i): CodeBlock
    {
        $count = count($lines);
        $code = [];
        $blanks = [];

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $blanks[] = substr($line, min(4, strspn($line, ' ')));
                $i++;

                continue;
            }

            if (strspn($line, ' ') < 4) {
                break;
            }

            foreach ($blanks as $blank) {
                $code[] = $blank;
            }

            $blanks = [];
            $code[] = substr($line, 4);
            $i++;
        }

        // Blank lines after the last line of code belong to the block that
        // follows.
        $i -= count($blanks);

        return new CodeBlock('', implode("\n", $code));
    }

    /**
     * Records the reference link definition on a line, such as
     * `[label]: https://example.com "Title"`. If the label is already defined,
     * the first definition stays.
     *
     * @param string $line The line to read.
     * @param BlockContext $context The state of the current text.
     *
     * @return bool True if the line is a definition; false otherwise.
     */
    private function definition(string $line, BlockContext $context): bool
    {
        if (preg_match(self::DEFINITION, $line, $m) !== 1) {
            return false;
        }

        $label = ReferenceLabel::normalize($m[1]);
        $url = ($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '');

        if ($label === '' || $url === '') {
            return false;
        }

        $title = null;

        foreach ([4, 5, 6] as $group) {
            if (isset($m[$group]) && $m[$group] !== '') {
                $title = self::unescape($m[$group]);

                break;
            }
        }

        $context->define($label, self::unescape($url), $title);

        return true;
    }

    /**
     * Removes the backslash from each escaped punctuation character.
     *
     * @param string $text The text with escapes.
     *
     * @return string The text without the backslashes.
     */
    private static function unescape(string $text): string
    {
        return (string) preg_replace('/\\\\([!-\/:-@\[-`{-~])/', '$1', $text);
    }

    /**
     * Reads a block made of one of the HTML tags allowed on their own lines:
     * `details`, `summary`, `div`, `p`, and `hr`.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the line to read from. The method moves it
     *     past the block when it reads one.
     * @param int $depth The nesting depth of the lines, starting at 0.
     * @param BlockContext $context The state of the current text.
     *
     * @return Node|null|false The block. Null if the lines hold nothing to
     *     show, such as a tag that is never closed. False if the line doesn't
     *     start one of these blocks.
     */
    private function htmlBlock(array &$lines, int &$i, int $depth, BlockContext $context): Node|null|false
    {
        $line = $lines[$i];
        $count = count($lines);

        if (preg_match('~^ {0,3}<hr\s*/?>\s*$~i', $line) === 1) {
            $i++;

            return new Rule();
        }

        // An element that opens and closes on one line, such as
        // `<summary>Title</summary>`.
        if (
            preg_match('~^ {0,3}<(details|summary|div|p)((?:\s[^<>]*)?)>(.*)</\1\s*>\s*$~i', $line, $m) === 1
            && preg_match('~<(?:details|summary|div|p)[\s>]~i', $m[3]) !== 1
        ) {
            $i++;
            $tag = strtolower($m[1]);

            return new HtmlLine($tag, Tags::blockAttributes($tag, Tags::attributes($m[2])), $m[3]);
        }

        // An opening tag on its own line, with a matching closing tag on its
        // own line.
        if (preg_match('~^ {0,3}<(details|summary|div|p)((?:\s[^<>]*)?)>\s*$~i', $line, $m) === 1) {
            $tag = strtolower($m[1]);
            $open = '~^ {0,3}<' . $tag . '(?:\s[^<>]*)?>\s*$~i';
            $close = '~^ {0,3}</' . $tag . '\s*>\s*$~i';
            $level = 1;
            $end = $i + 1;
            $found = false;

            while ($end < $count && ++$context->tagSearchLines <= self::MAX_TAG_SEARCH_LINES) {
                if (preg_match($open, $lines[$end]) === 1) {
                    $level++;
                } elseif (preg_match($close, $lines[$end]) === 1 && --$level === 0) {
                    $found = true;

                    break;
                }

                $end++;
            }

            // The parser drops a tag that is never closed and reads the lines
            // after it as usual.
            if (!$found) {
                $i++;

                return null;
            }

            $children = $this->blocks(array_slice($lines, $i + 1, $end - $i - 1), $depth + 1, $context);
            $attributes = Tags::blockAttributes($tag, Tags::attributes($m[2]));
            $i = $end + 1;

            return new HtmlBlock($tag, $attributes, $children);
        }

        // The parser drops a closing tag that has no opening tag.
        if (preg_match('~^ {0,3}</(?:details|summary|div|p)\s*>\s*$~i', $line) === 1) {
            $i++;

            return null;
        }

        return false;
    }

    /**
     * Tracks whether a code fence is open after a line. A quote needs this to
     * know when a line can't continue it without a `>`.
     *
     * @param array{0: string, 1: int}|null $fence The open fence, as its
     *     character and length, or null if none is open.
     * @param string $line The line to read.
     *
     * @return array{0: string, 1: int}|null The open fence after the line, or
     *     null if none is open.
     */
    private function fenceAfter(?array $fence, string $line): ?array
    {
        if ($fence === null) {
            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m) === 1) {
                return [$m[1][0], strlen($m[1])];
            }

            return $this->options->math && preg_match(self::MATH_FENCE, $line) === 1 ? ['$', 2] : null;
        }

        if ($fence[0] === '$') {
            return preg_match(self::MATH_FENCE, $line) === 1 ? null : $fence;
        }

        return preg_match('/^ {0,3}' . preg_quote($fence[0], '/') . '{' . $fence[1] . ',}[ \t]*$/', $line) === 1 ? null : $fence;
    }

    /**
     * Reads a list, which is a run of items that use the same kind of marker.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the first item. The method moves it past
     *     the list.
     * @param int $depth The nesting depth of the lines, starting at 0.
     * @param array{ordered: bool, char: string, number: int, contentIndent: int, content: string} $first
     *     The marker of the first item, already read from `$lines[$i]`.
     * @param BlockContext $context The state of the current text.
     *
     * @return ListBlock The block.
     */
    private function listBlock(array $lines, int &$i, int $depth, array $first, BlockContext $context): ListBlock
    {
        $count = count($lines);
        $items = [];
        $loose = false;

        while ($i < $count) {
            $marker = $this->listMarker($lines[$i]);

            if ($marker === null || !$this->sameList($first, $marker) || $this->isRule($lines[$i])) {
                break;
            }

            $contentIndent = $marker['contentIndent'];
            $itemLines = [$marker['content']];
            $i++;

            while ($i < $count) {
                $line = $lines[$i];

                if (trim($line) === '') {
                    if ($itemLines === ['']) {
                        break;
                    }

                    $next = $i;

                    while ($next < $count && trim($lines[$next]) === '') {
                        $next++;
                    }

                    if ($next < $count && strspn($lines[$next], ' ') >= $contentIndent) {
                        for ($k = $i; $k < $next; $k++) {
                            $itemLines[] = '';
                        }

                        $i = $next;

                        continue;
                    }

                    break;
                }

                if (strspn($line, ' ') >= $contentIndent) {
                    $itemLines[] = substr($line, $contentIndent);
                    $i++;

                    continue;
                }

                if ($this->paragraphIsOpenAfter(end($itemLines)) && !$this->interrupts($line) && !($this->options->math && $this->startsMathBlock($lines, $i)) && $this->listMarker($line) === null) {
                    $itemLines[] = $this->lazyLine($line);
                    $i++;

                    continue;
                }

                break;
            }

            $items[] = $this->blocks($itemLines, $depth + 1, $context);

            $next = $i;

            while ($next < $count && trim($lines[$next]) === '') {
                $next++;
            }

            $following = $next < $count ? $this->listMarker($lines[$next]) : null;

            if ($following === null || !$this->sameList($first, $following) || $this->isRule($lines[$next])) {
                break;
            }

            if ($next > $i) {
                $loose = true;
            }

            $i = $next;
        }

        foreach ($items as $children) {
            foreach ($children as $index => $child) {
                if ($index > 0 && $child->blank) {
                    $loose = true;
                }
            }
        }

        return new ListBlock($first['ordered'], $first['number'], $loose, $items);
    }

    /**
     * Reads a table, which is a header row, a delimiter row, and the body rows
     * that follow.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the header row. The method moves it past
     *     the table.
     * @param list<string|null> $alignments The alignment of each column, from
     *     `TableParser::alignments()`.
     *
     * @return Table The block.
     */
    private function table(array $lines, int &$i, array $alignments): Table
    {
        $width = count($alignments);
        $head = $this->tables->fit($this->tables->cells($lines[$i]), $width);

        $i += 2;
        $rows = [];
        $count = count($lines);

        while ($i < $count && trim($lines[$i]) !== '' && !$this->interrupts($lines[$i]) && !($this->options->math && $this->startsMathBlock($lines, $i))) {
            $rows[] = $this->tables->fit($this->tables->cells($lines[$i]), $width);
            $i++;
        }

        return new Table($alignments, $head, $rows);
    }

    /**
     * Reads a paragraph, or a heading if the `setextHeadings` setting is on and
     * an underline follows the text.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the first line of the paragraph. The method
     *     moves it past the paragraph.
     *
     * @return Heading|Paragraph The block.
     */
    private function paragraph(array $lines, int &$i): Heading|Paragraph
    {
        $buffer = [ltrim($lines[$i])];
        $count = count($lines);
        $i++;

        while ($i < $count && trim($lines[$i]) !== '') {
            // A line of `=` or `-` under the text makes it a heading.
            if ($this->options->setextHeadings && preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $lines[$i], $m) === 1) {
                $i++;

                return new Heading(min(6, ($m[1][0] === '=' ? 1 : 2) + $this->options->headingOffset), trim(implode("\n", $buffer)));
            }

            if ($this->interrupts($lines[$i]) || ($this->options->math && $this->startsMathBlock($lines, $i))) {
                break;
            }

            $buffer[] = ltrim($lines[$i]);
            $i++;
        }

        return new Paragraph(rtrim(implode("\n", $buffer)));
    }

    /**
     * Checks whether a paragraph is still open after a line, so that the next
     * line can continue it without repeating the quote or list marker.
     *
     * A heading, a rule, a code fence, a comment, or a definition ends the
     * paragraph, and the line after one of them starts a new block. The method
     * looks through any quote or list markers on the line, so `> - text` ends
     * in a paragraph. The method doesn't recognize tables, so a line after a
     * table row continues the table.
     *
     * @param string $line The last line read.
     *
     * @return bool True if a paragraph is open; false otherwise.
     */
    private function paragraphIsOpenAfter(string $line): bool
    {
        // Content nested past the limit is plain text, so there are no markers
        // deeper than this.
        for ($level = 0; $level <= $this->options->maxDepth; $level++) {
            if (preg_match('/^ {0,3}> ?(.*)$/', $line, $m) === 1) {
                $line = $m[1];

                continue;
            }

            $marker = $this->listMarker($line);

            if ($marker === null || $this->isRule($line)) {
                break;
            }

            $line = $marker['content'];
        }

        if (trim($line) === '') {
            return false;
        }

        return preg_match('/^ {0,3}(?:#{1,6}(?:[ \t]|$)|`{3,}|~{3,}|<!--)/', $line) !== 1
            && !$this->isRule($line)
            && !($this->options->math && preg_match(self::MATH_FENCE, $line) === 1)
            && !($this->options->html === Options::HTML_SANITIZE && preg_match('~^ {0,3}</?(?:details|summary|div|p|hr)[\s/>]~i', $line) === 1)
            && !($this->options->html === Options::HTML_STRIP && $this->isOnlyTags($line))
            && !($this->options->setextHeadings && preg_match('/^ {0,3}(?:=+|-+)[ \t]*$/', $line) === 1)
            && !($this->options->referenceLinks && preg_match(self::DEFINITION, $line) === 1);
    }

    /**
     * Checks whether a line opens a block of math, which can interrupt a
     * paragraph. A `$$` line with no closing line doesn't, so it stays part of
     * the paragraph.
     *
     * @param list<string> $lines The lines being read.
     * @param int $i The position of the line to check.
     *
     * @return bool True if the line opens a block of math; false otherwise.
     */
    private function startsMathBlock(array $lines, int $i): bool
    {
        return $this->options->math
            && preg_match(self::MATH_FENCE, $lines[$i]) === 1
            && $this->mathBlockEnd($lines, $i) !== null;
    }

    /**
     * Checks whether a line begins a block that can interrupt a paragraph.
     *
     * @param string $line The line to check.
     *
     * @return bool True if the line interrupts a paragraph; false otherwise.
     */
    private function interrupts(string $line): bool
    {
        return preg_match('/^ {0,3}(?:`{3,}|~{3,}|>|<!--)/', $line) === 1
            || preg_match('/^ {0,3}#{1,6}(?:[ \t]|$)/', $line) === 1
            || preg_match('/^ {0,3}[-+*][ \t]+\S/', $line) === 1
            || preg_match('/^ {0,3}1[.)][ \t]+\S/', $line) === 1
            || ($this->options->html === Options::HTML_SANITIZE && preg_match('~^ {0,3}</?(?:details|summary|div|p|hr)[\s/>]~i', $line) === 1)
            || ($this->options->html === Options::HTML_STRIP && $this->isOnlyTags($line))
            || $this->isRule($line);
    }

    /**
     * Checks whether a line holds nothing but HTML tags and whitespace.
     *
     * @param string $line The line to check.
     *
     * @return bool True if it does; false otherwise.
     */
    private function isOnlyTags(string $line): bool
    {
        return preg_match('~^ {0,3}(?:' . Tags::TAG_BODY . '[ \t]*+)++$~', $line) === 1;
    }

    /**
     * Checks whether a line is a horizontal rule: three or more of the same
     * character, which is `-`, `*`, or `_`, with optional spaces between them.
     *
     * @param string $line The line to check.
     *
     * @return bool True if it is; false otherwise.
     */
    private function isRule(string $line): bool
    {
        return preg_match('/^ {0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$/', $line) === 1;
    }

    /**
     * Replaces the tabs in a run of whitespace with the spaces that reach the
     * same columns, as CommonMark does: a tab moves to the next multiple of
     * four.
     *
     * @param string $whitespace A run of spaces and tabs.
     * @param int $column The column where the run starts.
     *
     * @return string The run as spaces.
     */
    private static function expandTabs(string $whitespace, int $column): string
    {
        $spaces = '';

        for ($i = 0, $length = strlen($whitespace); $i < $length; $i++) {
            $width = $whitespace[$i] === "\t" ? 4 - ($column % 4) : 1;
            $spaces .= str_repeat(' ', $width);
            $column += $width;
        }

        return $spaces;
    }

    /**
     * Reads the text of a line of a block quote, after the `>` and the one
     * space or tab column that can follow it.
     *
     * @param string $line The line.
     *
     * @return string|null The text, with the tabs after the `>` turned into
     *     spaces, or null if the line doesn't start with a `>`.
     */
    private function quoteContent(string $line): ?string
    {
        if (preg_match('/^( {0,3}>)([ \t]*)(.*)$/', $line, $m) !== 1) {
            return null;
        }

        if ($m[2] === '') {
            return $m[3];
        }

        $spaces = str_contains($m[2], "\t") ? self::expandTabs($m[2], strlen($m[1])) : $m[2];

        return substr($spaces, 1) . $m[3];
    }

    /**
     * Prepares a line that continues a paragraph without the marker of its
     * container, so that the line can't be read as a heading underline. A line
     * of `=` or `-` after a quoted or listed paragraph is plain text, because
     * only a line with the marker can underline the text above it. A backslash
     * in front makes the line plain text, and it writes the same characters.
     *
     * @param string $line The line to add to the container.
     *
     * @return string The line to use.
     */
    private function lazyLine(string $line): string
    {
        if ($this->options->setextHeadings && preg_match('/^ {0,3}(?:=+|-+)[ \t]*$/', $line) === 1) {
            return '\\' . ltrim($line);
        }

        return $line;
    }

    /**
     * Reads the list marker at the start of a line, such as `-`, `*`, `+`,
     * `1.`, or `1)`.
     *
     * @param string $line The line to read.
     *
     * @return array{ordered: bool, char: string, number: int, contentIndent: int, content: string}|null
     *     The marker: whether it's numbered, its character (the bullet, or the
     *     `.` or `)` after a number), its number, the column where the item's
     *     content starts, and the content on the line. Null if the line doesn't
     *     start with a marker.
     */
    private function listMarker(string $line): ?array
    {
        // A tab after the marker counts as the spaces that reach the next tab
        // stop.
        if (str_contains($line, "\t") && preg_match('/^( {0,3}(?:[-+*]|\d{1,9}[.)]))([ \t]+)/', $line, $t) === 1 && str_contains($t[2], "\t")) {
            $line = $t[1] . self::expandTabs($t[2], strlen($t[1])) . substr($line, strlen($t[0]));
        }

        if (preg_match('/^( {0,3})([-+*])(?:( +)(.*))?$/', $line, $m) === 1) {
            $ordered = false;
            $char = $m[2];
            $number = 1;
            $width = 1;
        } elseif (preg_match('/^( {0,3})(\d{1,9})([.)])(?:( +)(.*))?$/', $line, $m) === 1) {
            $ordered = true;
            $char = $m[3];
            $number = (int) $m[2];
            $width = strlen($m[2]) + 1;

            // The pattern for a numbered marker has one more capture group than
            // the one for a bullet. Renumber the groups so that the code below
            // reads both the same way.
            $m = [$m[0], $m[1], $m[3], $m[4] ?? '', $m[5] ?? ''];
        } else {
            return null;
        }

        $indent = strlen($m[1]);
        $spaces = strlen($m[3] ?? '');
        $content = $m[4] ?? '';

        if (trim($content) === '') {
            return ['ordered' => $ordered, 'char' => $char, 'number' => $number, 'contentIndent' => $indent + $width + 1, 'content' => ''];
        }

        if ($spaces > 4) {
            $content = str_repeat(' ', $spaces - 1) . $content;
            $spaces = 1;
        }

        return ['ordered' => $ordered, 'char' => $char, 'number' => $number, 'contentIndent' => $indent + $width + $spaces, 'content' => $content];
    }

    /**
     * Checks whether two markers belong to the same list: both are bullets of
     * the same character, or both are numbers with the same delimiter.
     *
     * @param array{ordered: bool, char: string} $a The first marker.
     * @param array{ordered: bool, char: string} $b The second marker.
     *
     * @return bool True if the markers belong to the same list; false
     *     otherwise.
     */
    private function sameList(array $a, array $b): bool
    {
        return $a['ordered'] === $b['ordered'] && $a['char'] === $b['char'];
    }
}
