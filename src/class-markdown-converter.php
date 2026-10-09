<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter;

use ScottOffen\MarkdownConverter\Parsing\BlockParser;
use ScottOffen\MarkdownConverter\Parsing\InlineParser;
use ScottOffen\MarkdownConverter\Parsing\TableParser;
use ScottOffen\MarkdownConverter\Parsing\UrlPolicy;
use ScottOffen\MarkdownConverter\Rendering\HtmlRenderer;
use ScottOffen\MarkdownConverter\Rendering\Styles;

/**
 * Converts untrusted text to safe HTML.
 *
 *     $html = (new MarkdownConverter())->toHtml($markdown);
 *
 * The converter treats the text as untrusted. It escapes the text and doesn't
 * copy tags from it. With no settings, the converter shows HTML in the text as
 * text, limits links to `http`, `https`, and `mailto` addresses, doesn't link
 * relative links, and doesn't show images. Each of those is a setting you can
 * change. The security tests check the output with the defaults and with every
 * setting on.
 *
 * The converter reads a subset of CommonMark and GitHub Flavored Markdown, and
 * shows anything outside that syntax as plain text.
 *
 * A converter never changes after it is made, and it keeps nothing from one
 * text for the next. You can use one converter for any number of texts, in any
 * order, and each result is the same as from a converter made for that text
 * alone.
 */
final class MarkdownConverter
{
    private readonly Options $options;

    private readonly BlockParser $blocks;

    private readonly InlineParser $inline;

    private readonly Styles $styles;

    /**
     * Creates a converter with the given settings. Every setting is optional.
     *
     * @param int $headingOffset The number of levels to push headings down,
     *     from 0 to 5, so that `#` can become an `h4` on a page that has its
     *     own headings. Levels past 6 stop at 6. Default: 0.
     * @param string $softBreak What a single line break inside a paragraph
     *     becomes: `"break"` for a line break, as on GitHub, `"space"` for a
     *     space, or `"newline"` for a newline character, which HTML shows as a
     *     space. Default: `"break"`.
     * @param list<string> $imageHosts The hosts that images can load from. If
     *     empty, the converter shows no image and turns each one into a link.
     *     `"*"` allows every host, and `"*.example.com"` allows subdomains of
     *     `example.com`. Images must use https. Default: an empty list.
     * @param bool $inlineStyles If true, adds small inline styles to tables,
     *     quotes, alerts, code blocks, images, inline diffs, and Mermaid
     *     diagrams, for pages that have no stylesheet for them. If false, adds
     *     none. Default: false.
     * @param string $linkTarget The `target` of every link: `"_blank"`,
     *     `"_self"`, or an empty string to leave it out. Default: `"_blank"`.
     * @param int $maxLength The most input, in bytes, that the converter reads.
     *     The converter leaves out the rest, adds a note, and never cuts a
     *     character in half. Default: 125000.
     * @param int $maxDepth How deeply quotes, lists, and HTML blocks can nest,
     *     from 1 to 20. The converter shows anything deeper as plain text.
     *     Default: 8.
     * @param string $html What happens to HTML in the text: `"escape"` shows it
     *     as text, `"strip"` removes the tags and keeps the text between them,
     *     and `"sanitize"` keeps a short list of tags, rebuilt from scratch,
     *     and removes the rest. Default: `"escape"`.
     * @param bool $decodeEntities If true, shows character references such as
     *     `&copy;` and `&#169;` as the characters they name. If false, shows
     *     them as written. Default: false.
     * @param string $baseUrl The address that relative links and images are
     *     joined to, such as `"https://example.com/docs"`. Both `page` and
     *     `/page` end up inside it, and `..` can't go above it. If empty,
     *     relative links aren't linked. Default: an empty string.
     * @param list<string> $linkSchemes The schemes that a link can use. Add
     *     `"ftp"` or `"tel"` if you need them. Schemes that can run script or
     *     read local files, such as `javascript`, can never be added. Default:
     *     `["http", "https", "mailto"]`.
     * @param bool $autolinkWww If true, links bare addresses that start with
     *     `www.`. If false, leaves them as text. Default: false.
     * @param bool $autolinkEmails If true, links bare mail addresses. If false,
     *     leaves them as text. Default: false.
     * @param bool $indentedCode If true, treats lines indented four spaces as a
     *     code block. If false, treats them as ordinary text. Default: false.
     * @param bool $setextHeadings If true, treats a line of `=` or `-` under a
     *     line of text as a heading. If false, treats it as text. Default:
     *     false.
     * @param bool $referenceLinks If true, supports links and images written as
     *     `[text][label]`, with `[label]: address` defined elsewhere in the
     *     text. If false, shows them as text. Default: false.
     * @param bool $taskLists If true, shows `[ ]` and `[x]` at the start of a
     *     list item as box characters. If false, shows them as written.
     *     Default: false.
     * @param bool $emoji If true, shows names such as `:tada:` as the emoji
     *     they stand for. If false, shows them as written. Default: false.
     * @param array<string, string> $customEmoji Extra emoji names, or
     *     replacements for built-in names, as `name => text`. The converter
     *     uses them only if `$emoji` is true. Default: an empty array.
     * @param bool $inlineDiffs If true, shows `{+ added +}` and
     *     `{- removed -}`, and the same inside square brackets, as inserted and
     *     deleted text, as GitLab does. If false, shows them as written.
     *     Default: false.
     * @param bool $fencedQuotes If true, treats text between two `>>>` lines as
     *     a quote, as GitLab does. The first line can also be `>>> [!NOTE]` to
     *     make an alert. If false, treats `>>>` as three nested quote markers.
     *     Default: false.
     * @param bool $math If true, treats `$...$`, `$$...$$`, a block between two
     *     lines that hold only `$$`, and a fenced `math` block as math, and
     *     writes the TeX, escaped, for a page that renders it with a library
     *     such as KaTeX or MathJax. The converter doesn't render math itself. If
     *     false, shows them as written. Default: false.
     * @param string $mathFormat How the converter writes math: `"pandoc"` for
     *     `\(...\)` and `\[...\]` inside elements with the classes `math
     *     inline` and `math display`, `"class"` for bare TeX inside elements
     *     with the classes `math-inline` and `math-display`, or `"delimiters"`
     *     for the TeX with its own `$` and `$$` delimiters and no element
     *     around it. The converter uses it only if `$math` is true. Default:
     *     `"pandoc"`.
     * @param int $mathMaxLength The longest piece of math, in bytes, that the
     *     converter reads, from 1 to 10000. The converter shows longer math as
     *     plain text. The converter uses it only if `$math` is true. Default:
     *     1000.
     * @param bool $mermaid If true, writes a fenced `mermaid` block as a `pre`
     *     element with the class `mermaid`, holding the escaped diagram text,
     *     for a page that renders it with the Mermaid library. The converter
     *     doesn't render diagrams itself. If false, shows the block as code.
     *     Default: false.
     *
     * @throws InvalidArgumentException If a setting isn't valid.
     */
    public function __construct(
        int $headingOffset = 0,
        string $softBreak = Options::SOFT_BREAK,
        array $imageHosts = [],
        bool $inlineStyles = false,
        string $linkTarget = Options::DEFAULT_LINK_TARGET,
        int $maxLength = Options::DEFAULT_MAX_LENGTH,
        int $maxDepth = Options::DEFAULT_MAX_DEPTH,
        string $html = Options::HTML_ESCAPE,
        bool $decodeEntities = false,
        string $baseUrl = '',
        array $linkSchemes = Options::DEFAULT_LINK_SCHEMES,
        bool $autolinkWww = false,
        bool $autolinkEmails = false,
        bool $indentedCode = false,
        bool $setextHeadings = false,
        bool $referenceLinks = false,
        bool $taskLists = false,
        bool $emoji = false,
        array $customEmoji = [],
        bool $inlineDiffs = false,
        bool $fencedQuotes = false,
        bool $math = false,
        string $mathFormat = Options::MATH_PANDOC,
        int $mathMaxLength = Options::DEFAULT_MATH_MAX_LENGTH,
        bool $mermaid = false,
    ) {
        // Pass every argument to `Options` by its own name, because `Options`
        // checks the settings. This call must be the first statement, so the
        // only variables defined are the arguments.
        $this->options = new Options(...get_defined_vars());

        $this->styles = new Styles($this->options->inlineStyles);

        $this->blocks = new BlockParser($this->options, new TableParser());
        $this->inline = new InlineParser($this->options, new UrlPolicy($this->options->imageHosts, $this->options->baseUrl, $this->options->linkSchemes), $this->styles);
    }

    /**
     * Converts Markdown to HTML.
     *
     * If the text is longer than `maxLength`, the converter reads the first
     * `maxLength` bytes, cut at a character boundary, and adds a note at the
     * end that the rest was left out.
     *
     * @param string $markdown The text to convert.
     *
     * @return string The HTML, with no wrapping element.
     */
    public function toHtml(string $markdown): string
    {
        $truncated = strlen($markdown) > $this->options->maxLength;

        if ($truncated) {
            // Cut on a character boundary, so the limit never splits a
            // character.
            $markdown = mb_strcut($markdown, 0, $this->options->maxLength, 'UTF-8');
        }

        // The renderer reads the text inside the blocks when it writes them, so
        // a reference link can be defined after it is used. The definitions
        // belong to this text, so the converter makes a renderer for it and
        // discards the renderer afterward.
        $document = $this->blocks->parse(self::clean($markdown));
        $html = (new HtmlRenderer($this->options, $this->inline, $this->styles, $document->definitions))->render($document->nodes);

        if ($truncated) {
            $html .= ($html !== '' ? "\n" : '') . '<p><em>The rest of this text was left out because it is too long.</em></p>';
        }

        return $html;
    }

    /**
     * Makes the text safe to read line by line: valid UTF-8, one kind of line
     * ending, no control characters, and no tabs in the indentation.
     *
     * @param string $text The text as given.
     *
     * @return string The cleaned text.
     */
    private static function clean(string $text): string
    {
        $text = self::validUtf8($text);
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        $lines = explode("\n", $text);

        foreach ($lines as $index => $line) {
            if (isset($line[0]) && ($line[0] === "\t" || ($line[0] === ' ' && str_contains(substr($line, 0, strspn($line, " \t")), "\t")))) {
                $lines[$index] = self::expandIndentation($line);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Replaces every byte that isn't part of a valid UTF-8 character with
     * U+FFFD, the replacement character. The result marks invalid bytes with a
     * symbol that browsers recognize, not with a question mark that looks like
     * the author typed it. The method returns text that is already valid
     * unchanged.
     *
     * @param string $text The text.
     *
     * @return string The text as valid UTF-8.
     */
    private static function validUtf8(string $text): string
    {
        if (preg_match('//u', $text) === 1) {
            return $text;
        }

        $valid = '[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
            . '|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';

        return (string) preg_replace('~(?:' . $valid . ')(*SKIP)(*FAIL)|[\x80-\xFF]~', "\u{FFFD}", $text);
    }

    /**
     * Replaces the tabs in the indentation of a line with spaces, up to the
     * next multiple of four columns.
     *
     * @param string $line The line.
     *
     * @return string The line with spaces in its indentation.
     */
    private static function expandIndentation(string $line): string
    {
        $length = strspn($line, " \t");
        $column = 0;
        $indent = '';

        for ($i = 0; $i < $length; $i++) {
            if ($line[$i] === "\t") {
                $spaces = 4 - ($column % 4);
                $indent .= str_repeat(' ', $spaces);
                $column += $spaces;
            } else {
                $indent .= ' ';
                $column++;
            }
        }

        return $indent . substr($line, $length);
    }
}
