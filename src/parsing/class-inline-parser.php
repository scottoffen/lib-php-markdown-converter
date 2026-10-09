<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

use ScottOffen\MarkdownConverter\Emoji;
use ScottOffen\MarkdownConverter\Html;
use ScottOffen\MarkdownConverter\Math;
use ScottOffen\MarkdownConverter\Options;
use ScottOffen\MarkdownConverter\Rendering\Styles;

/**
 * Turns the text inside one block into HTML: emphasis, code, links, images, and
 * line breaks.
 *
 * The parser escapes text with `Html::text()` or `Html::attr()` and doesn't
 * copy tags from the input.
 *
 * The parser reads the text once, from left to right, so whatever starts first
 * decides what a character belongs to. A code span, a comment, or an address in
 * angle brackets keeps a backtick or a bracket inside it from being read as
 * anything else. The parser remembers each `[` or `![` until its `]` arrives.
 * Only then, from what follows the `]`, is it known to be a link or an image.
 * Emphasis is matched afterward, within the text of each link and within the
 * block.
 *
 * The parser never changes after it is made. What it keeps while it reads one
 * block is in an `InlineContext` made for that block: how many brackets and
 * tags it has tried, how deeply tags are nested, and the reference link
 * definitions. So one parser can read any number of texts in any order, and
 * nothing in one affects another.
 *
 * @internal
 */
final class InlineParser
{
    private const PUNCTUATION = '!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~';

    /**
     * The most delimiter runs that one block can turn into tokens. The parser
     * leaves further runs as plain text, so emphasis matching stays fast.
     */
    private const MAX_DELIMITERS = 500;

    /**
     * The most brackets, tags, and diffs that one block can try. Beyond this,
     * the parser leaves them as plain text.
     */
    private const MAX_BRACKET_ATTEMPTS = 200;

    /**
     * The most places in one block that the parser tries to read as math,
     * counting every `$` or `$$` that could start it. Beyond this, the parser
     * leaves them as plain text.
     */
    private const MAX_MATH_ATTEMPTS = 200;

    /**
     * The longest link text or reference label, in bytes, that can make a link.
     */
    private const MAX_LABEL_LENGTH = 1000;

    /**
     * The deepest nesting of HTML tags. The parser removes tags nested deeper.
     */
    private const MAX_TAG_DEPTH = 20;

    /**
     * The characters that the scanner has to stop at. All other characters are
     * plain text.
     */
    private readonly string $stops;

    /**
     * The emoji names that are on, mapped to their characters. Empty if the
     * `emoji` setting is off.
     *
     * @var array<string, string>
     */
    private readonly array $emoji;

    /**
     * Creates an inline parser with the given settings, address policy, and
     * styles.
     *
     * @param Options $options The settings.
     * @param UrlPolicy $urls The policy for the addresses of links and images.
     * @param Styles $styles The source of inline styles.
     */
    public function __construct(
        private readonly Options $options,
        private readonly UrlPolicy $urls,
        private readonly Styles $styles,
    ) {
        $this->stops = "\\`<![]*_~\nhH"
            . ($options->decodeEntities ? '&' : '')
            . ($options->autolinkWww ? 'wW' : '')
            . (in_array('ftp', $options->linkSchemes, true) ? 'fF' : '')
            . ($options->autolinkEmails ? '@' : '')
            . ($options->emoji ? ':' : '')
            . ($options->inlineDiffs ? '{' : '')
            . ($options->math ? '$' : '');

        $this->emoji = $options->emoji ? $options->customEmoji + Emoji::NAMES : [];
    }

    /**
     * Turns the text of one block into HTML.
     *
     * @param string $text The text of the block, as written.
     * @param array<string, array{url: string, title: string|null}> $definitions
     *     The reference link definitions of the whole text, keyed by
     *     `ReferenceLabel::normalize()`.
     *
     * @return string The HTML.
     */
    public function parse(string $text, array $definitions = []): string
    {
        return $this->inline($text, true, new InlineContext($definitions));
    }

    /**
     * Turns a piece of text into HTML. `parse()` calls it for a whole block,
     * and the methods that read tags and diffs call it for the text nested
     * inside.
     *
     * @param string $text The text.
     * @param bool $allowLinks True if links and autolinks can be made; false
     *     inside the text of a link.
     * @param InlineContext $context The state of the current block.
     *
     * @return string The HTML.
     */
    private function inline(string $text, bool $allowLinks, InlineContext $context): string
    {
        return $this->render($this->emphasis($this->tokenize($text, $allowLinks, $context)));
    }

    /**
     * Splits text into plain text, finished HTML, and delimiter runs that can
     * become emphasis.
     *
     * Each kind of syntax is read by its own method below. The method moves the
     * scan along and leaves what it doesn't recognize as text.
     *
     * @param string $text The text to split.
     * @param bool $allowLinks True if links and autolinks can be made; false
     *     inside the text of a link.
     * @param InlineContext $context The state of the current block.
     *
     * @return list<array<string, mixed>> The tokens.
     */
    private function tokenize(string $text, bool $allowLinks, InlineContext $context): array
    {
        $scan = new InlineScan($text, $allowLinks, $context);

        while ($scan->i < $scan->length) {
            $run = strcspn($text, $this->stops, $scan->i);

            if ($run > 0) {
                $scan->buffer .= substr($text, $scan->i, $run);
                $scan->i += $run;

                if ($scan->i >= $scan->length) {
                    break;
                }
            }

            match ($text[$scan->i]) {
                '\\' => $this->backslash($scan),
                '`' => $this->codeSpan($scan),
                '<' => $this->angleBracket($scan),
                '!' => $this->bang($scan),
                '{', '[' => $this->openBracket($scan),
                ']' => $this->closeBracket($scan),
                '*', '_', '~' => $this->delimiterRun($scan),
                "\n" => $this->lineBreak($scan),
                '&' => $this->characterReference($scan),
                ':' => $this->emojiName($scan),
                '$' => $this->math($scan),
                '@' => $this->bareEmail($scan),
                'w', 'W' => $this->bareWww($scan),
                default => $this->bareUrl($scan),
            };
        }

        $scan->flush();

        return $scan->tokens;
    }

    /**
     * Reads a backslash, which makes the next punctuation character plain text
     * or, before a newline, makes a hard break.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function backslash(InlineScan $scan): void
    {
        $next = $scan->text[$scan->i + 1] ?? '';

        if ($next === "\n") {
            $scan->html($this->hardBreak());
            $scan->i += 2;
            $scan->i += strspn($scan->text, ' ', $scan->i);
        } elseif ($next !== '' && str_contains(self::PUNCTUATION, $next)) {
            $scan->keep($next, 2);
        } else {
            $scan->keep('\\', 1);
        }
    }

    /**
     * Reads a run of backticks. If a run of the same length follows later, the
     * text between the two is a code span. Otherwise the backticks are plain
     * text.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function codeSpan(InlineScan $scan): void
    {
        $count = strspn($scan->text, '`', $scan->i);
        $end = null;

        if (!isset($scan->noCloser[$count])) {
            if (preg_match('/(?<!`)`{' . $count . '}(?!`)/', $scan->text, $m, PREG_OFFSET_CAPTURE, $scan->i + $count) === 1) {
                $end = $m[0][1];
            } else {
                // No later run of this length has a closer either, so the
                // search isn't repeated.
                $scan->noCloser[$count] = true;
            }
        }

        if ($end === null) {
            $scan->keep(str_repeat('`', $count), $count);

            return;
        }

        $code = str_replace("\n", ' ', substr($scan->text, $scan->i + $count, $end - $scan->i - $count));

        if (strlen($code) >= 2 && $code[0] === ' ' && $code[strlen($code) - 1] === ' ' && trim($code, ' ') !== '') {
            $code = substr($code, 1, -1);
        }

        $scan->html('<code>' . Html::text($code) . '</code>');
        $scan->i = $end + $count;
    }

    /**
     * Reads a `<`, which can start a comment, an address in angle brackets, or
     * an HTML tag. Otherwise it is plain text.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function angleBracket(InlineScan $scan): void
    {
        $text = $scan->text;
        $i = $scan->i;
        $following = $text[$i + 1] ?? '';

        // A comment ends at the first `-->`. When no `-->` is left in the text,
        // no later `<!--` can have one either, so the search isn't repeated.
        // Without this, each `<!--` would read to the end of the text.
        if ($following === '!' && !$scan->noCommentEnd && substr($text, $i, 4) === '<!--') {
            $end = strpos($text, '-->', $i + 4);

            if ($end !== false) {
                $scan->i = $end + 3;

                return;
            }

            $scan->noCommentEnd = true;
        }

        // Only some characters can follow the `<` of an address or a tag, so
        // the patterns are tried only for those.
        if ($scan->allowLinks && ctype_alpha($following) && preg_match('~\G<([A-Za-z][A-Za-z0-9+.-]{1,31}:[^\s<>]*)>~', $text, $m, 0, $i) === 1) {
            $this->angleAddress($scan, $m[0], $m[1], $m[1]);

            return;
        }

        if ($scan->allowLinks && $following !== '' && $following !== ' ' && preg_match('~\G<([A-Za-z0-9.!#$%&\'*+/=?^_`{|}\~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)>~', $text, $m, 0, $i) === 1) {
            $this->angleAddress($scan, $m[0], $m[1], 'mailto:' . $m[1]);

            return;
        }

        if ($this->options->html !== Options::HTML_ESCAPE && ($following === '/' || ctype_alpha($following))) {
            $tag = $this->tagAt($text, $i, $scan->allowLinks, $scan->context);

            if ($tag !== null) {
                $scan->flush();

                if ($tag[1] !== '') {
                    $scan->html($tag[1]);
                }

                $scan->i = $tag[0];

                return;
            }
        }

        $scan->keep('<', 1);
    }

    /**
     * Writes `<https://example.com>` or `<me@example.com>` as a link if the
     * address is allowed, and as text if it isn't.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     * @param string $whole The address with its angle brackets, as written.
     * @param string $shown The address, shown as the text of the link.
     * @param string $address The address to check and link to, with `mailto:`
     *     added for a mail address.
     */
    private function angleAddress(InlineScan $scan, string $whole, string $shown, string $address): void
    {
        $href = $this->urls->link($address);

        if ($href !== null) {
            // Inside the text of a link, the address can't be a link, so the
            // parser shows it as written, angle brackets included.
            $scan->html($this->anchor($href, Html::text($shown), null), Html::text($whole));
        } else {
            $scan->buffer .= $whole;
        }

        $scan->i += strlen($whole);
    }

    /**
     * Reads a `!`. If a `[` follows, the two open the description of an image.
     * Otherwise the `!` is plain text.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function bang(InlineScan $scan): void
    {
        if (($scan->text[$scan->i + 1] ?? '') !== '[') {
            $scan->keep('!', 1);

            return;
        }

        $this->pushBracket($scan, $scan->i + 1, true, true);
    }

    /**
     * Reads a `{` or `[`. Either can start an inline diff, and a `[` can open
     * the text of a link. Otherwise it is plain text.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function openBracket(InlineScan $scan): void
    {
        $text = $scan->text;
        $i = $scan->i;
        $c = $text[$i];

        if ($this->options->inlineDiffs && $scan->context->attempts < self::MAX_BRACKET_ATTEMPTS && preg_match('/\G[\[{]([+-]) (.{1,500}?) \1[\]}]/', $text, $m, 0, $i) === 1) {
            $scan->context->attempts++;
            $tag = $m[1] === '+' ? 'ins' : 'del';

            $scan->html('<' . $tag . $this->styles->attribute($tag) . '>' . $this->inline($m[2], $scan->allowLinks, $scan->context) . '</' . $tag . '>');
            $scan->i += strlen($m[0]);

            return;
        }

        if ($c === '{') {
            $scan->keep('{', 1);

            return;
        }

        $this->pushBracket($scan, $i, false, $scan->allowLinks);
    }

    /**
     * Remembers a `[` or `![` until the `]` that closes it arrives. The method
     * decides then, from what follows the `]`, whether it becomes a link or an
     * image. A code span or an address in angle brackets between the two has
     * been read by then, so a bracket inside it can't cause a wrong match.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     * @param int $pos The position of the `[`.
     * @param bool $image True if the `[` follows a `!`; false otherwise.
     * @param bool $live True if the bracket can become a link or an image;
     *     false if it stays plain text.
     */
    private function pushBracket(InlineScan $scan, int $pos, bool $image, bool $live): void
    {
        $shown = $image ? '![' : '[';

        // Past the limit, brackets stay plain text. The parser still pushes
        // them, as false entries, so the `]` that closes one isn't matched to a
        // different bracket.
        if (!$live || $scan->context->attempts >= self::MAX_BRACKET_ATTEMPTS) {
            $scan->brackets[] = false;
            $scan->keep($shown, $image ? 2 : 1);

            return;
        }

        $scan->context->attempts++;
        $scan->flush();
        $scan->tokens[] = ['t' => 'text', 'v' => $shown];
        $scan->brackets[] = ['pos' => $pos, 'image' => $image, 'token' => count($scan->tokens) - 1];
        $scan->i = $pos + 1;
    }

    /**
     * Reads a `]`, which closes the innermost open bracket. If the `]` is
     * followed by an address in parentheses, or by a reference that is defined,
     * the bracket and everything after it become a link or an image. Otherwise
     * both stay plain text.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function closeBracket(InlineScan $scan): void
    {
        $close = $scan->i;
        $opener = array_pop($scan->brackets);
        $depth = count($scan->brackets);

        // Everything above this bracket is gone, so the floor can't be higher
        // than the stack.
        $floor = $scan->linkFloor;
        $scan->linkFloor = min($floor, $depth);

        if (
            $opener === false
            || $opener === null
            || (!$opener['image'] && $depth < $floor)
            || $close - $opener['pos'] >= self::MAX_LABEL_LENGTH
        ) {
            $scan->keep(']', 1);

            return;
        }

        $text = $scan->text;
        $label = substr($text, $opener['pos'] + 1, $close - $opener['pos'] - 1);
        $target = $this->target($text, $close, $label, $scan->context->definitions);

        if ($target === null) {
            $scan->keep(']', 1);

            return;
        }

        $scan->flush();
        $inner = array_slice($scan->tokens, $opener['token'] + 1);
        array_splice($scan->tokens, $opener['token']);

        if ($opener['image']) {
            // The description is the text between the brackets, with the markup
            // removed.
            $scan->html($this->imageHtml($target['url'], $this->plainText($this->render($this->emphasis($inner))), $target['title'], $scan->allowLinks));
        } else {
            $html = $this->withoutLinks($this->render($this->emphasis($this->withoutLinkTokens($inner))));
            $href = $this->urls->link($target['url']);

            $scan->html($href === null ? $html : $this->anchor($href, $html, $target['title']));

            // A link can't contain a link, so no `[` that is still open can
            // become one now.
            $scan->linkFloor = count($scan->brackets);
        }

        $scan->i = $target['end'];
    }

    /**
     * Replaces each link token inside the text of a link with its alternative
     * text.
     *
     * @param list<array<string, mixed>> $tokens The tokens inside the text of a
     *     link.
     *
     * @return list<array<string, mixed>> The tokens, with link tokens replaced
     *     by their alternative text.
     */
    private function withoutLinkTokens(array $tokens): array
    {
        foreach ($tokens as $index => $token) {
            if (isset($token['alt'])) {
                $tokens[$index] = ['t' => 'html', 'v' => $token['alt']];
            }
        }

        return $tokens;
    }

    /**
     * Removes the `a` tags from HTML that the parser wrote, and leaves the text
     * inside. Everything else in the HTML is escaped, so a `<a` can only be one
     * of the parser's own tags.
     *
     * @param string $html The HTML.
     *
     * @return string The HTML without `a` tags.
     */
    private function withoutLinks(string $html): string
    {
        return (string) preg_replace('~</?a\b[^>]*>~', '', $html);
    }

    /**
     * Reads a run of `*`, `_`, or `~` and makes it a delimiter token, which
     * `emphasis()` later pairs up or turns back into text.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function delimiterRun(InlineScan $scan): void
    {
        $text = $scan->text;
        $i = $scan->i;
        $c = $text[$i];
        $count = strspn($text, $c, $i);

        if ($scan->delimiters >= self::MAX_DELIMITERS || ($c === '~' && $count > 2)) {
            $scan->keep(str_repeat($c, $count), $count);

            return;
        }

        $before = self::charBefore($text, $i);
        $after = self::charAfter($text, $i + $count);

        $beforeSpace = self::isSpace($before);
        $afterSpace = self::isSpace($after);
        $beforePunct = self::isPunctuation($before);
        $afterPunct = self::isPunctuation($after);

        $leftFlanking = !$afterSpace && (!$afterPunct || $beforeSpace || $beforePunct);
        $rightFlanking = !$beforeSpace && (!$beforePunct || $afterSpace || $afterPunct);

        if ($c === '_') {
            $canOpen = $leftFlanking && (!$rightFlanking || $beforePunct);
            $canClose = $rightFlanking && (!$leftFlanking || $afterPunct);
        } else {
            $canOpen = $leftFlanking;
            $canClose = $rightFlanking;
        }

        if (!$canOpen && !$canClose) {
            $scan->keep(str_repeat($c, $count), $count);

            return;
        }

        $scan->flush();
        $scan->tokens[] = ['t' => 'delim', 'c' => $c, 'n' => $count, 'len' => $count, 'open' => $canOpen, 'close' => $canClose];
        $scan->delimiters++;
        $scan->i += $count;
    }

    /**
     * Reads a newline. Two spaces before it make a hard break. Any other
     * newline follows the `softBreak` setting.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function lineBreak(InlineScan $scan): void
    {
        $hard = str_ends_with($scan->buffer, '  ');
        $scan->buffer = rtrim($scan->buffer, ' ');
        $scan->flush();

        if ($hard) {
            $scan->html($this->hardBreak());
        } elseif ($this->options->softBreak === Options::SOFT_BREAK) {
            $scan->html('<br>');
        } else {
            $scan->tokens[] = ['t' => 'text', 'v' => $this->options->softBreak === Options::SOFT_SPACE ? ' ' : "\n"];
        }

        $scan->i++;
        $scan->i += strspn($scan->text, ' ', $scan->i);
    }

    /**
     * Reads a character reference such as `&copy;` or `&#169;` and writes the
     * character it names. The scanner stops at an `&` only when the
     * `decodeEntities` setting is on.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function characterReference(InlineScan $scan): void
    {
        if (preg_match('/\G&(?:#([0-9]{1,7})|#[xX]([0-9a-fA-F]{1,6})|[A-Za-z][A-Za-z0-9]{1,31});/', $scan->text, $m, 0, $scan->i) === 1) {
            if (($m[1] ?? '') !== '' || ($m[2] ?? '') !== '') {
                // A number that isn't a character, or is a control character,
                // becomes the replacement character, as CommonMark says.
                $code = ($m[1] ?? '') !== '' ? (int) $m[1] : (int) hexdec($m[2]);
                $invalid = $code === 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF) || ($code >= 0x80 && $code <= 0x9F);
                $decoded = $invalid ? "\u{FFFD}" : (string) mb_chr($code, 'UTF-8');
            } else {
                $decoded = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if ($decoded !== $m[0]) {
                $scan->buffer .= (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', "\u{FFFD}", $decoded);
                $scan->i += strlen($m[0]);

                return;
            }
        }

        $scan->keep('&', 1);
    }

    /**
     * Reads an emoji name such as `:tada:`. The name can't directly follow a
     * letter or number, so times like `12:30:45` are left alone.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function emojiName(InlineScan $scan): void
    {
        $i = $scan->i;

        if (
            preg_match('/\G:([a-z0-9_+-]{1,32}):/', $scan->text, $m, 0, $i) === 1
            && isset($this->emoji[$m[1]])
            && ($i === 0 || !ctype_alnum($scan->text[$i - 1]))
        ) {
            $scan->keep($this->emoji[$m[1]], strlen($m[0]));

            return;
        }

        $scan->keep(':', 1);
    }

    /**
     * Reads a `$` or `$$`, which can start math. `$...$` is math inside a
     * line, and `$$...$$` is display math that can span lines. Otherwise the
     * `$` is plain text.
     *
     * The parser reads the math as one token, as it does a code span, so an
     * `_` or a `*` inside it can't be read as emphasis. A single `$` follows
     * the rule that pandoc uses, so prices stay plain text: the character after
     * the opening `$` can't be whitespace, and the closing `$` can't follow
     * whitespace or come before a digit. A `$` that a backslash escapes can't
     * open or close math.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function math(InlineScan $scan): void
    {
        $text = $scan->text;
        $i = $scan->i;
        $run = strspn($text, '$', $i);

        if ($run > 2 || $scan->context->mathAttempts >= self::MAX_MATH_ATTEMPTS) {
            $scan->keep(str_repeat('$', $run), $run);

            return;
        }

        $scan->context->mathAttempts++;
        $start = $i + $run;
        $end = $run === 2 ? $this->displayMathEnd($text, $start) : $this->inlineMathEnd($text, $start);

        if ($end === null) {
            $scan->keep(str_repeat('$', $run), $run);

            return;
        }

        $scan->html(Math::html(substr($text, $start, $end - $start), $run === 2, false, $this->options->mathFormat));
        $scan->i = $end + $run;
    }

    /**
     * Finds the `$` that closes math opened by a single `$`.
     *
     * @param string $text The text.
     * @param int $start The position after the opening `$`.
     *
     * @return int|null The position of the closing `$`, or null if the `$`
     *     doesn't open math.
     */
    private function inlineMathEnd(string $text, int $start): ?int
    {
        $next = $text[$start] ?? '';

        if ($next === '' || $next === ' ' || $next === "\n" || $next === "\t") {
            return null;
        }

        $limit = $start + $this->options->mathMaxLength;
        $at = $start;

        while (($j = strpos($text, '$', $at)) !== false && $j <= $limit) {
            $after = $text[$j + 1] ?? '';

            if ($after === '$') {
                // A run of `$` can't close math that one `$` opened.
                $at = $j + strspn($text, '$', $j);

                continue;
            }

            $before = $text[$j - 1];

            if (
                $j > $start
                && $before !== ' ' && $before !== "\n" && $before !== "\t"
                && !($after !== '' && ctype_digit($after))
                && $this->backslashesBefore($text, $j) % 2 === 0
            ) {
                return $j;
            }

            $at = $j + 1;
        }

        return null;
    }

    /**
     * Finds the `$$` that closes math opened by `$$`.
     *
     * @param string $text The text.
     * @param int $start The position after the opening `$$`.
     *
     * @return int|null The position of the closing `$$`, or null if the `$$`
     *     doesn't open math.
     */
    private function displayMathEnd(string $text, int $start): ?int
    {
        $limit = $start + $this->options->mathMaxLength;
        $at = $start;

        while (($j = strpos($text, '$$', $at)) !== false && $j <= $limit) {
            $run = strspn($text, '$', $j);

            if ($run === 2 && $this->backslashesBefore($text, $j) % 2 === 0) {
                return trim(substr($text, $start, $j - $start)) === '' ? null : $j;
            }

            $at = $j + $run;
        }

        return null;
    }

    /**
     * Counts the backslashes right before a position, so the parser can tell a
     * `$` that is escaped, as in `\$`, from one that follows an escaped
     * backslash, as in `\\$`.
     *
     * @param string $text The text.
     * @param int $i The position.
     *
     * @return int The number of backslashes in a row that end at `$i - 1`.
     */
    private function backslashesBefore(string $text, int $i): int
    {
        $count = 0;

        while ($i - $count - 1 >= 0 && $text[$i - $count - 1] === '\\') {
            $count++;
        }

        return $count;
    }

    /**
     * Reads a bare mail address. The part before the `@` is already waiting in
     * the buffer.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function bareEmail(InlineScan $scan): void
    {
        if (
            $scan->allowLinks
            && preg_match('/[A-Za-z0-9._+-]+$/', substr($scan->buffer, -65), $local) === 1
            && strlen($local[0]) <= 64
            && preg_match('/^@([A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)+)/', substr($scan->text, $scan->i, 260), $domain) === 1
        ) {
            $address = $local[0] . '@' . $domain[1];
            $href = $this->urls->link('mailto:' . $address);

            if ($href !== null) {
                $scan->buffer = substr($scan->buffer, 0, -strlen($local[0]));
                $scan->html($this->anchor($href, Html::text($address), null));
                $scan->i += strlen($domain[0]);

                return;
            }
        }

        $scan->keep('@', 1);
    }

    /**
     * Reads a bare address that starts with `www.`.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function bareWww(InlineScan $scan): void
    {
        $i = $scan->i;

        if ($scan->allowLinks && $this->startsWord($scan->text, $i) && preg_match('~\Gwww\.[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)+[^\s<]{0,2049}~i', $scan->text, $m, 0, $i) === 1) {
            $shown = self::trimAddress($this->endAtClosingBracket($scan, $m[0]));
            $href = $this->urls->link('http://' . $shown);

            if ($href !== null) {
                $scan->html($this->anchor($href, Html::text($shown), null));
                $scan->i += strlen($shown);

                return;
            }
        }

        $scan->keep($scan->text[$i], 1);
    }

    /**
     * Reads a bare web or ftp address, found at an `h` or an `f`.
     *
     * @param InlineScan $scan The scan. The method moves it along.
     */
    private function bareUrl(InlineScan $scan): void
    {
        $i = $scan->i;

        if ($scan->allowLinks && $this->startsWord($scan->text, $i) && preg_match('~\G(?:https?|ftp)://[^\s<]{1,2049}~i', $scan->text, $m, 0, $i) === 1) {
            $url = self::trimAddress($this->endAtClosingBracket($scan, $m[0]));
            $href = $this->urls->link($url);

            if ($href !== null) {
                $scan->html($this->anchor($href, Html::text($url), null));
                $scan->i += strlen($url);

                return;
            }
        }

        $scan->keep($scan->text[$i], 1);
    }

    /**
     * Reads the HTML tag at a position, when the `html` setting is `strip` or
     * `sanitize`.
     *
     * @param string $text The text.
     * @param int $i The position of the `<`.
     * @param bool $allowLinks True if a link tag can become a link; false
     *     inside the text of a link.
     * @param InlineContext $context The state of the current block.
     *
     * @return array{0: int, 1: string}|null The position where the text
     *     continues and the HTML to write in place of the tag, or null if there
     *     is no tag at that position.
     */
    private function tagAt(string $text, int $i, bool $allowLinks, InlineContext $context): ?array
    {
        if (preg_match(Tags::TAG, $text, $m, 0, $i) !== 1 || strlen($m[0]) > 4096) {
            return null;
        }

        $end = $i + strlen($m[0]);
        $closing = $m[1] === '/';
        $name = strtolower($m[2]);

        // The parser drops a `script` or `style` element along with what is
        // inside it.
        if (!$closing && in_array($name, Tags::REMOVED_WITH_CONTENT, true)) {
            if (preg_match('~</' . $name . '\s*>~i', $text, $c, PREG_OFFSET_CAPTURE, $end) === 1) {
                return [$c[0][1] + strlen($c[0][0]), ''];
            }

            return [$end, ''];
        }

        if ($this->options->html === Options::HTML_STRIP || $closing) {
            return [$end, ''];
        }

        if ($name === 'br') {
            return [$end, '<br>'];
        }

        if ($name === 'img') {
            $attributes = Tags::attributes($m[3]);

            return [$end, $this->imageHtml(
                $attributes['src'] ?? '',
                $attributes['alt'] ?? '',
                $attributes['title'] ?? null,
                $allowLinks,
                $attributes['width'] ?? null,
                $attributes['height'] ?? null,
            )];
        }

        if ((!isset(Tags::INLINE[$name]) && $name !== 'a') || $m[4] === '/' || $context->attempts >= self::MAX_BRACKET_ATTEMPTS || $context->tagDepth >= self::MAX_TAG_DEPTH) {
            return [$end, ''];
        }

        $context->attempts++;
        $close = $this->closingTag($text, $end, $name);

        if ($close === null) {
            return [$end, ''];
        }

        $inner = substr($text, $end, $close[0] - $end);

        if ($name === 'code' || $name === 'kbd') {
            $tag = Tags::INLINE[$name];

            return [$close[1], '<' . $tag . '>' . Html::text($inner) . '</' . $tag . '>'];
        }

        $context->tagDepth++;
        $innerHtml = $this->inline($inner, $name !== 'a' && $allowLinks, $context);
        $context->tagDepth--;

        if ($name === 'a') {
            $attributes = Tags::attributes($m[3]);
            $href = $allowLinks && isset($attributes['href']) ? $this->urls->link($attributes['href']) : null;

            return [$close[1], $href === null ? $innerHtml : $this->anchor($href, $innerHtml, $attributes['title'] ?? null)];
        }

        $tag = Tags::INLINE[$name];

        return [$close[1], '<' . $tag . '>' . $innerHtml . '</' . $tag . '>'];
    }

    /**
     * Finds the tag that closes the one just opened, counting tags of the same
     * name inside it.
     *
     * @param string $text The text.
     * @param int $from The position after the opening tag.
     * @param string $name The lowercase element name.
     *
     * @return array{0: int, 1: int}|null The positions where the closing tag
     *     starts and where it ends, or null if there is none.
     */
    private function closingTag(string $text, int $from, string $name): ?array
    {
        $pattern = '~<(/?)' . preg_quote($name, '~') . '(?=[\s/>])[^<>]*+>~i';
        $depth = 1;
        $position = $from;

        for ($tries = 0; $tries < 100; $tries++) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $position) !== 1) {
                return null;
            }

            $start = $m[0][1];
            $position = $start + strlen($m[0][0]);

            if ($m[1][0] === '/') {
                if (--$depth === 0) {
                    return [$start, $position];
                }
            } elseif (!str_ends_with($m[0][0], '/>')) {
                $depth++;
            }
        }

        return null;
    }

    /**
     * Matches delimiter runs into emphasis, strong emphasis, and strikethrough,
     * following the delimiter stack procedure in the CommonMark specification.
     *
     * The tokens are in an array that shrinks as pairs are matched. After every
     * match, the method pulls the `openers_bottom` positions back so they never
     * point past the tokens that moved.
     *
     * @param list<array<string, mixed>> $tokens The tokens, including delimiter
     *     runs.
     *
     * @return list<array<string, mixed>> The tokens, with matched delimiters
     *     turned into HTML.
     */
    private function emphasis(array $tokens): array
    {
        $bottoms = [];
        $i = 0;

        while ($i < count($tokens)) {
            $closer = $tokens[$i];

            if ($closer['t'] !== 'delim' || !$closer['close'] || $closer['n'] === 0) {
                $i++;

                continue;
            }

            $char = $closer['c'];
            $key = $char === '~' ? '~' . $closer['len'] : $char . ($closer['len'] % 3) . ($closer['open'] ? 'o' : 'c');
            $bottom = $bottoms[$key] ?? -1;
            $match = null;

            for ($j = $i - 1; $j > $bottom; $j--) {
                $opener = $tokens[$j];

                if ($opener['t'] !== 'delim' || $opener['c'] !== $char || !$opener['open'] || $opener['n'] === 0) {
                    continue;
                }

                if ($char === '~') {
                    if ($opener['len'] !== $closer['len']) {
                        continue;
                    }
                } elseif (
                    ($closer['open'] || $opener['close'])
                    && ($opener['len'] + $closer['len']) % 3 === 0
                    && !($opener['len'] % 3 === 0 && $closer['len'] % 3 === 0)
                ) {
                    continue;
                }

                $match = $j;

                break;
            }

            if ($match === null) {
                $bottoms[$key] = $i - 1;
                $i++;

                continue;
            }

            $j = $match;
            $use = $char === '~' ? $closer['len'] : ($tokens[$j]['n'] >= 2 && $closer['n'] >= 2 ? 2 : 1);
            $tag = $char === '~' ? 'del' : ($use === 2 ? 'strong' : 'em');
            $inner = $this->render(array_slice($tokens, $j + 1, $i - $j - 1));

            $tokens[$j]['n'] -= $use;
            $tokens[$i]['n'] -= $use;

            array_splice($tokens, $j + 1, $i - $j - 1, [['t' => 'html', 'v' => '<' . $tag . '>' . $inner . '</' . $tag . '>']]);

            // The splice moved every token after `$j`, so a remembered position
            // past it would now point at the wrong token.
            foreach ($bottoms as $key => $bottom) {
                $bottoms[$key] = min($bottom, $j);
            }

            $closerIndex = $j + 2;

            if ($tokens[$closerIndex]['n'] === 0) {
                array_splice($tokens, $closerIndex, 1);
            }

            if ($tokens[$j]['n'] === 0) {
                array_splice($tokens, $j, 1);
                $closerIndex--;
            }

            $i = $closerIndex;
        }

        return $tokens;
    }

    /**
     * Writes tokens as HTML. Plain text is escaped, HTML tokens are written as
     * they are, and delimiter runs that were not matched are written as the
     * characters they were.
     *
     * @param list<array<string, mixed>> $tokens The tokens.
     *
     * @return string The HTML.
     */
    private function render(array $tokens): string
    {
        $html = '';

        foreach ($tokens as $token) {
            if ($token['t'] === 'text') {
                $html .= Html::text($token['v']);
            } elseif ($token['t'] === 'html') {
                $html .= $token['v'];
            } elseif ($token['n'] > 0) {
                $html .= str_repeat($token['c'], $token['n']);
            }
        }

        return $html;
    }

    /**
     * Reads what a pair of brackets points at: an address in parentheses, such
     * as `(https://example.com "Title")`, or, when the `referenceLinks` setting
     * is on, a definition found elsewhere in the text.
     *
     * @param string $text The text.
     * @param int $close The position of the `]`.
     * @param string $label The text between the brackets, as written.
     * @param array<string, array{url: string, title: string|null}> $definitions
     *     The reference link definitions of the whole text.
     *
     * @return array{url: string, title: string|null, end: int}|null The
     *     address, the title, and the position where the text continues, or
     *     null if the brackets point at nothing.
     */
    private function target(string $text, int $close, string $label, array $definitions): ?array
    {
        $tail = $this->linkTail($text, $close + 1);

        if ($tail !== null || !$this->options->referenceLinks || $definitions === []) {
            return $tail;
        }

        $key = $label;
        $end = $close + 1;

        // `[text][label]` and `[text][]` name their definition. A `[` that
        // can't start a link label, because there is no `]` after it or it
        // holds another `[`, isn't part of the link, which is then `[text]`
        // alone.
        if (($text[$close + 1] ?? '') === '[') {
            $referenceEnd = strpos($text, ']', $close + 2);

            if ($referenceEnd !== false && $referenceEnd - $close <= self::MAX_LABEL_LENGTH) {
                $reference = substr($text, $close + 2, $referenceEnd - $close - 2);

                if ($reference === '') {
                    $end = $referenceEnd + 1;
                } elseif (!str_contains($reference, '[')) {
                    $key = $reference;
                    $end = $referenceEnd + 1;
                }
            }
        }

        $definition = $definitions[ReferenceLabel::normalize($key)] ?? null;

        return $definition === null ? null : ['url' => $definition['url'], 'title' => $definition['title'], 'end' => $end];
    }

    /**
     * Returns the text of some HTML that the parser wrote, with the tags
     * removed. A nested image contributes its own description.
     *
     * @param string $html The HTML.
     *
     * @return string The text.
     */
    private function plainText(string $html): string
    {
        $html = (string) preg_replace('/<img\b[^>]*?\balt="([^"]*)"[^>]*>/', '$1', $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Returns the HTML for a hard line break.
     *
     * @return string A `br` tag, followed by a newline if the `softBreak`
     *     setting is `newline`.
     */
    private function hardBreak(): string
    {
        return $this->options->softBreak === Options::SOFT_NEWLINE ? "<br>\n" : '<br>';
    }

    /**
     * Reads `(address "title")` starting at a position.
     *
     * @param string $text The text.
     * @param int $position The position of the `(`.
     *
     * @return array{url: string, title: string|null, end: int}|null The
     *     address, the title, and the position after the `)`, or null if there
     *     is no valid tail there.
     */
    private function linkTail(string $text, int $position): ?array
    {
        if (($text[$position] ?? '') !== '(') {
            return null;
        }

        $length = strlen($text);
        $i = $position + 1;
        $i += strspn($text, " \t\n", $i);
        $url = '';

        if (($text[$i] ?? '') === '<') {
            $j = $i + 1;

            while ($j < $length && $text[$j] !== '>') {
                $ch = $text[$j];

                if ($ch === "\n" || $ch === '<') {
                    return null;
                }

                if ($ch === '\\' && $j + 1 < $length && str_contains(self::PUNCTUATION, $text[$j + 1])) {
                    $url .= $text[$j + 1];
                    $j += 2;

                    continue;
                }

                $url .= $ch;
                $j++;
            }

            if ($j >= $length) {
                return null;
            }

            $i = $j + 1;
        } else {
            $depth = 0;
            $j = $i;

            while ($j < $length) {
                $ch = $text[$j];

                if ($ch === '\\' && $j + 1 < $length && str_contains(self::PUNCTUATION, $text[$j + 1])) {
                    $url .= $text[$j + 1];
                    $j += 2;

                    continue;
                }

                if ($ch === '(') {
                    if (++$depth > 32) {
                        return null;
                    }
                } elseif ($ch === ')') {
                    if ($depth === 0) {
                        break;
                    }

                    $depth--;
                } elseif (ord($ch) <= 0x20) {
                    break;
                }

                $url .= $ch;
                $j++;
            }

            $i = $j;
        }

        $i += strspn($text, " \t\n", $i);
        $title = null;
        $quote = $text[$i] ?? '';

        if ($quote === '"' || $quote === "'" || $quote === '(') {
            $closer = $quote === '(' ? ')' : $quote;
            $title = '';
            $j = $i + 1;

            while ($j < $length && $text[$j] !== $closer) {
                if ($text[$j] === '\\' && $j + 1 < $length && str_contains(self::PUNCTUATION, $text[$j + 1])) {
                    $title .= $text[$j + 1];
                    $j += 2;

                    continue;
                }

                $title .= $text[$j];
                $j++;
            }

            if ($j >= $length) {
                return null;
            }

            $i = $j + 1;
            $i += strspn($text, " \t\n", $i);
        }

        if (($text[$i] ?? '') !== ')') {
            return null;
        }

        return ['url' => $url, 'title' => $title, 'end' => $i + 1];
    }

    /**
     * Writes a link.
     *
     * @param string $href The address, already checked.
     * @param string $innerHtml The HTML to show as the text of the link.
     * @param string|null $title The title, or null if there is none.
     *
     * @return string The `a` element.
     */
    private function anchor(string $href, string $innerHtml, ?string $title): string
    {
        $html = '<a href="' . Html::attr($href) . '"';

        if ($title !== null && $title !== '') {
            $html .= ' title="' . Html::attr($title) . '"';
        }

        $html .= ' rel="noopener noreferrer nofollow"';

        if ($this->options->linkTarget !== '') {
            $html .= ' target="' . Html::attr($this->options->linkTarget) . '"';
        }

        return $html . '>' . $innerHtml . '</a>';
    }

    /**
     * Writes an image as an `img` tag only if its address is on an allowed
     * host. Otherwise it writes a link, or only the description if a link isn't
     * safe or links aren't allowed where the image is.
     *
     * @param string $url The address of the image.
     * @param string $alt The description of the image.
     * @param string|null $title The title, or null if there is none.
     * @param bool $allowLinks True if the image can fall back to a link; false
     *     otherwise.
     * @param string|null $width The width from an HTML `img` tag, or null.
     * @param string|null $height The height from an HTML `img` tag, or null.
     *
     * @return string The HTML.
     */
    private function imageHtml(string $url, string $alt, ?string $title, bool $allowLinks, ?string $width = null, ?string $height = null): string
    {
        $src = $this->urls->image($url);

        if ($src === null) {
            // Where links aren't allowed, such as inside a link, the
            // description is all that is left.
            $href = $allowLinks ? $this->urls->link($url) : null;

            return $href === null
                ? Html::text($alt)
                : $this->anchor($href, Html::text($alt !== '' ? $alt : 'image'), $title);
        }

        $html = '<img src="' . Html::attr($src) . '" alt="' . Html::attr($alt) . '"';

        if ($title !== null && $title !== '') {
            $html .= ' title="' . Html::attr($title) . '"';
        }

        foreach (['width' => $width, 'height' => $height] as $name => $value) {
            if ($value !== null && preg_match('/^[0-9]{1,4}$/', $value) === 1) {
                $html .= ' ' . $name . '="' . $value . '"';
            }
        }

        $html .= ' loading="lazy" referrerpolicy="no-referrer"';

        return $html . $this->styles->attribute('image') . '>';
    }

    /**
     * Ends a bare address before a `]` that nothing in the address opened, if a
     * `[` is open. That `]` is most likely the end of the text of a link.
     * Brackets that balance inside the address, as in `a[1]`, stay.
     *
     * @param InlineScan $scan The scan.
     * @param string $address The address as matched.
     *
     * @return string The address, cut before the unbalanced `]` if there is
     *     one.
     */
    private function endAtClosingBracket(InlineScan $scan, string $address): string
    {
        if ($scan->brackets === []) {
            return $address;
        }

        $depth = 0;

        for ($k = 0, $length = strlen($address); $k < $length; $k++) {
            if ($address[$k] === '[') {
                $depth++;
            } elseif ($address[$k] === ']') {
                if ($depth === 0) {
                    return substr($address, 0, $k);
                }

                $depth--;
            }
        }

        return $address;
    }

    /**
     * Checks whether a bare address can start at a position: at the start of
     * the text, after whitespace, or after `*`, `_`, `~`, or `(`.
     *
     * @param string $text The text.
     * @param int $i The position.
     *
     * @return bool True if an address can start there; false otherwise.
     */
    private function startsWord(string $text, int $i): bool
    {
        if ($i === 0) {
            return true;
        }

        $before = self::charBefore($text, $i);

        return self::isSpace($before) || str_contains('*_~(', $before);
    }

    /**
     * Removes punctuation that belongs to the sentence around a bare web
     * address, and unbalanced closing parentheses, so that only the address is
     * left.
     *
     * @param string $url The address as matched.
     *
     * @return string The address without trailing sentence punctuation.
     */
    private static function trimAddress(string $url): string
    {
        do {
            $before = $url;
            $url = rtrim($url, '?!.,:*_~\'"');

            while (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) {
                $url = substr($url, 0, -1);
            }
        } while ($url !== $before);

        return $url;
    }

    /**
     * Returns the character before a position, which can be more than one byte
     * long.
     *
     * @param string $text The text.
     * @param int $i The position.
     *
     * @return string The character, or an empty string at the start of the
     *     text.
     */
    private static function charBefore(string $text, int $i): string
    {
        if ($i <= 0) {
            return '';
        }

        $start = $i - 1;

        while ($start > 0 && $i - $start < 4 && (ord($text[$start]) & 0xC0) === 0x80) {
            $start--;
        }

        return substr($text, $start, $i - $start);
    }

    /**
     * Returns the character at a position, which can be more than one byte
     * long.
     *
     * @param string $text The text.
     * @param int $i The position.
     *
     * @return string The character, or an empty string at the end of the text.
     */
    private static function charAfter(string $text, int $i): string
    {
        if ($i >= strlen($text)) {
            return '';
        }

        $byte = ord($text[$i]);
        $size = $byte < 0x80 ? 1 : ($byte >= 0xF0 ? 4 : ($byte >= 0xE0 ? 3 : ($byte >= 0xC0 ? 2 : 1)));

        return substr($text, $i, $size);
    }

    /**
     * Checks whether a character is whitespace. A missing character counts as
     * whitespace, because the start and the end of the text do.
     *
     * @param string $char One character, or an empty string.
     *
     * @return bool True if it is whitespace or missing; false otherwise.
     */
    private static function isSpace(string $char): bool
    {
        return $char === '' || preg_match('/^[\s\p{Z}]$/u', $char) === 1;
    }

    /**
     * Checks whether a character is punctuation or a symbol. The CommonMark
     * specification treats both the same way when it decides whether a
     * delimiter run can open or close emphasis.
     *
     * @param string $char One character, or an empty string.
     *
     * @return bool True if it is; false otherwise.
     */
    private static function isPunctuation(string $char): bool
    {
        return $char !== '' && preg_match('/^[\p{P}\p{S}]$/u', $char) === 1;
    }
}
