<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter;

use InvalidArgumentException;

/**
 * Holds the settings of a converter, and is the one place that checks them.
 *
 * `MarkdownConverter` builds one from its own constructor arguments. No public
 * method accepts an `Options` object, so code outside the library doesn't need
 * this class.
 *
 * `MarkdownConverter::__construct()` lists the same settings, in the same order
 * and with the same defaults, so editors can show them. A test compares the two
 * lists. If you add a setting here, add it there too, or the test fails.
 * `MarkdownConverter::__construct()` also documents what each setting does.
 *
 * @internal
 */
final class Options
{
    /**
     * A single line break in a paragraph becomes a line break, as in GitHub
     * comments and release notes.
     */
    public const SOFT_BREAK = 'break';

    /**
     * A single line break in a paragraph becomes a space, as in a README file.
     */
    public const SOFT_SPACE = 'space';

    /**
     * A single line break in a paragraph stays a newline character, which HTML
     * shows as a space.
     */
    public const SOFT_NEWLINE = 'newline';

    /**
     * The default for `maxLength`, in bytes. GitHub limits release notes to
     * 125,000 characters.
     */
    public const DEFAULT_MAX_LENGTH = 125000;

    /**
     * The default for `maxDepth`: how deeply quotes, lists, and HTML blocks can
     * nest.
     */
    public const DEFAULT_MAX_DEPTH = 8;

    /**
     * The default for `mathMaxLength`, in bytes: the longest piece of math that
     * the converter reads.
     */
    public const DEFAULT_MATH_MAX_LENGTH = 1000;

    /**
     * The largest value that `mathMaxLength` can have, so that the setting
     * can't undo the limits that keep conversion fast.
     */
    public const LIMIT_MATH_MAX_LENGTH = 10000;

    /** The default for `linkTarget`. An empty string leaves the target out. */
    public const DEFAULT_LINK_TARGET = '_blank';

    /** The default for `linkSchemes`. */
    public const DEFAULT_LINK_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Schemes that can run script or load local content. No setting can allow
     * them. `UrlPolicy` checks them again, so the rule holds even for code that
     * builds a policy without `Options`.
     */
    public const FORBIDDEN_SCHEMES = ['javascript', 'vbscript', 'livescript', 'data', 'file', 'blob', 'about', 'mhtml', 'ms-its', 'jar'];

    /**
     * Writes math as `\(...\)` and `\[...\]` inside elements with the classes
     * `math inline` and `math display`, as pandoc does. MathJax and KaTeX
     * read this with their default settings.
     */
    public const MATH_PANDOC = 'pandoc';

    /**
     * Writes math as bare TeX inside elements with the classes `math-inline`
     * and `math-display`, for a page that renders each element itself.
     */
    public const MATH_CLASS = 'class';

    /**
     * Writes math as escaped TeX with its own `$` and `$$` delimiters and no
     * element around it, for a page that renders the delimiters in the text.
     */
    public const MATH_DELIMITERS = 'delimiters';

    /** Shows HTML in the text as text. */
    public const HTML_ESCAPE = 'escape';

    /** Removes HTML tags from the text and keeps the text between them. */
    public const HTML_STRIP = 'strip';

    /**
     * Rebuilds a short list of safe tags from the text and removes every other
     * tag.
     */
    public const HTML_SANITIZE = 'sanitize';

    /**
     * Checks the settings and stores them. `MarkdownConverter::__construct()`
     * documents what each setting does.
     *
     * @param list<string> $imageHosts
     * @param list<string> $linkSchemes
     * @param array<string, string> $customEmoji
     *
     * @throws InvalidArgumentException If a setting isn't valid.
     */
    public function __construct(
        public readonly int $headingOffset = 0,
        public readonly string $softBreak = self::SOFT_BREAK,
        public readonly array $imageHosts = [],
        public readonly bool $inlineStyles = false,
        public readonly string $linkTarget = self::DEFAULT_LINK_TARGET,
        public readonly int $maxLength = self::DEFAULT_MAX_LENGTH,
        public readonly int $maxDepth = self::DEFAULT_MAX_DEPTH,
        public readonly string $html = self::HTML_ESCAPE,
        public readonly bool $decodeEntities = false,
        public readonly string $baseUrl = '',
        public readonly array $linkSchemes = self::DEFAULT_LINK_SCHEMES,
        public readonly bool $autolinkWww = false,
        public readonly bool $autolinkEmails = false,
        public readonly bool $indentedCode = false,
        public readonly bool $setextHeadings = false,
        public readonly bool $referenceLinks = false,
        public readonly bool $taskLists = false,
        public readonly bool $emoji = false,
        public readonly array $customEmoji = [],
        public readonly bool $inlineDiffs = false,
        public readonly bool $fencedQuotes = false,
        public readonly bool $math = false,
        public readonly string $mathFormat = self::MATH_PANDOC,
        public readonly int $mathMaxLength = self::DEFAULT_MATH_MAX_LENGTH,
        public readonly bool $mermaid = false,
    ) {
        if ($headingOffset < 0 || $headingOffset > 5) {
            throw new InvalidArgumentException('The heading offset must be between 0 and 5.');
        }

        if (!in_array($softBreak, [self::SOFT_BREAK, self::SOFT_SPACE, self::SOFT_NEWLINE], true)) {
            throw new InvalidArgumentException('The soft break setting must be "break", "space", or "newline".');
        }

        foreach ($imageHosts as $host) {
            if (!is_string($host) || trim($host) === '') {
                throw new InvalidArgumentException('Every image host must be a non-empty string.');
            }
        }

        if (!in_array($linkTarget, ['_blank', '_self', ''], true)) {
            throw new InvalidArgumentException('The link target must be "_blank", "_self", or an empty string.');
        }

        if ($maxLength < 1) {
            throw new InvalidArgumentException('The maximum length must be at least 1.');
        }

        if ($maxDepth < 1 || $maxDepth > 20) {
            throw new InvalidArgumentException('The maximum depth must be between 1 and 20.');
        }

        if (!in_array($html, [self::HTML_ESCAPE, self::HTML_STRIP, self::HTML_SANITIZE], true)) {
            throw new InvalidArgumentException('The html setting must be "escape", "strip", or "sanitize".');
        }

        if (!in_array($mathFormat, [self::MATH_PANDOC, self::MATH_CLASS, self::MATH_DELIMITERS], true)) {
            throw new InvalidArgumentException('The math format must be "pandoc", "class", or "delimiters".');
        }

        if ($mathMaxLength < 1 || $mathMaxLength > self::LIMIT_MATH_MAX_LENGTH) {
            throw new InvalidArgumentException('The maximum math length must be between 1 and ' . self::LIMIT_MATH_MAX_LENGTH . '.');
        }

        if ($baseUrl !== '') {
            $parts = parse_url($baseUrl);

            if (
                preg_match('~^https?://~i', $baseUrl) !== 1
                || preg_match('/[\x00-\x20\x7F\\\\]/', $baseUrl) === 1
                || $parts === false
                || !isset($parts['host'])
                || $parts['host'] === ''
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['query'])
                || isset($parts['fragment'])
            ) {
                throw new InvalidArgumentException('The base address must be an http or https address with a host, and no user name, query, or fragment.');
            }
        }

        // PHP converts a key such as `100` or `-1` to an integer, so the loop
        // casts the name to a string before checking it.
        foreach ($customEmoji as $name => $text) {
            if (
                preg_match('/^[a-z0-9_+-]{1,32}$/', (string) $name) !== 1
                || !is_string($text)
                || $text === ''
                || strlen($text) > 64
                || preg_match('/[\x00-\x1F\x7F]/', $text) === 1
            ) {
                throw new InvalidArgumentException('A custom emoji needs a name of lower case letters, numbers, "_", "+", or "-", and text up to 64 bytes long with no control characters.');
            }
        }

        foreach ($linkSchemes as $scheme) {
            if (!is_string($scheme) || preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) !== 1) {
                throw new InvalidArgumentException('Every link scheme must be written in lower case, such as "https" or "ftp".');
            }

            if (in_array($scheme, self::FORBIDDEN_SCHEMES, true)) {
                throw new InvalidArgumentException('The scheme "' . $scheme . '" can run script or read local files, so it cannot be allowed.');
            }
        }
    }
}
