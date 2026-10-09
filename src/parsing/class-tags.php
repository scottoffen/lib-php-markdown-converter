<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

/**
 * Defines which HTML tags the converter keeps or removes when the `html`
 * setting isn't `escape`, and reads the attributes of a tag.
 *
 * The converter doesn't copy a tag from the input to the output. It reads the
 * tag and writes a new one from the lists in `Tags`.
 *
 * @internal
 */
final class Tags
{
    /**
     * Tags that can wrap text inside a paragraph, each mapped to the tag the
     * converter writes for it. For example, `b` is written as `strong`.
     *
     * @var array<string, string>
     */
    public const INLINE = [
        'b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em', 'u' => 'u',
        's' => 'del', 'strike' => 'del', 'del' => 'del', 'ins' => 'ins', 'code' => 'code',
        'kbd' => 'kbd', 'sub' => 'sub', 'sup' => 'sup', 'mark' => 'mark', 'small' => 'small',
    ];

    /**
     * Tags that can stand on their own lines and hold other blocks.
     *
     * @var list<string>
     */
    public const BLOCK = ['details', 'summary', 'div', 'p'];

    /**
     * Tags for which the converter drops the whole element, including its
     * content, when the `html` setting is `strip` or `sanitize`.
     *
     * @var list<string>
     */
    public const REMOVED_WITH_CONTENT = ['script', 'style'];

    /**
     * A regular expression for one opening, closing, or self-closing tag,
     * without delimiters. Every quantifier is possessive, so the match can't
     * backtrack and can't become slow on a long run of attributes with no
     * closing `>`.
     */
    public const TAG_BODY = '<(/?)([A-Za-z][A-Za-z0-9-]*+)((?:\s++[A-Za-z_:][A-Za-z0-9_.:-]*+(?:\s*+=\s*+(?:[^\s"\'=<>`]++|\'[^\']*+\'|"[^"]*+"))?+)*+)\s*+(/?)>';

    /** The same expression with a `\G` anchor, to match at a given offset. */
    public const TAG = '~\G' . self::TAG_BODY . '~';

    /**
     * Reads the attributes of a tag.
     *
     * Names are lowercase and the first of a repeated name wins. Character
     * references in values are decoded, as a browser does.
     *
     * @param string $raw The attribute text of the tag, such as
     *     ` href="x" title="y"`.
     *
     * @return array<string, string> The attribute values, keyed by lowercase
     *     name.
     */
    public static function attributes(string $raw): array
    {
        $found = [];

        if (preg_match_all('~([A-Za-z_:][A-Za-z0-9_.:-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~', $raw, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $name = strtolower($match[1]);

            if (isset($found[$name])) {
                continue;
            }

            $value = $match[2] ?? '';
            $value = $value !== '' ? $value : ($match[3] ?? '');
            $value = $value !== '' ? $value : ($match[4] ?? '');

            $found[$name] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $found;
    }

    /**
     * Returns the attributes that a block tag can keep, written out with a
     * leading space.
     *
     * Only `align` on `p` and `div`, when its value is `left`, `center`, or
     * `right`, and `open` on `details` are kept.
     *
     * @param string $tag The element name.
     * @param array<string, string> $attributes The attributes read from the
     *     tag, from `attributes()`.
     *
     * @return string The attributes to write, or an empty string.
     */
    public static function blockAttributes(string $tag, array $attributes): string
    {
        if (($tag === 'p' || $tag === 'div') && isset($attributes['align'])) {
            $align = strtolower(trim($attributes['align']));

            return in_array($align, ['left', 'center', 'right'], true) ? ' align="' . $align . '"' : '';
        }

        if ($tag === 'details' && isset($attributes['open'])) {
            return ' open';
        }

        return '';
    }
}
