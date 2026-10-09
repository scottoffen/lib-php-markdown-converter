<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Parses converter output and reports anything that isn't on the allowlist: a
 * tag, an attribute, or an address that the converter must not produce.
 *
 * The checker looks at the parsed tree, which is what a browser acts on, not at
 * the text, so it catches problems that a search for `<script` would miss.
 */
final class HtmlChecker
{
    private const ELEMENTS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'em', 'strong', 'del', 'code', 'pre', 'a', 'img',
        'ul', 'ol', 'li', 'blockquote', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'div',
        // The converter writes this only when the math setting is on.
        'span',
        // The converter writes this only when it sanitizes HTML.
        'details', 'summary', 'u', 'ins', 'kbd', 'sub', 'sup', 'mark', 'small',
    ];

    private const ATTRIBUTES = [
        'a' => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt', 'title', 'loading', 'referrerpolicy', 'style', 'width', 'height'],
        'details' => ['open'],
        'ins' => ['style'],
        'del' => ['style'],
        'code' => ['class'],
        'ol' => ['start'],
        'th' => ['align', 'style'],
        'td' => ['align', 'style'],
        'table' => ['style'],
        'pre' => ['class', 'style'],
        'blockquote' => ['style'],
        'div' => ['class', 'style', 'align'],
        'p' => ['class', 'style', 'align'],
        'span' => ['class'],
    ];

    /**
     * Returns the problems in some converter output.
     *
     * @param string $html The output to check.
     * @param list<string> $schemes The schemes that a link can use.
     *
     * @return list<string> A description of each problem, or an empty list if
     *     the output is clean.
     */
    public static function violations(string $html, array $schemes = ['http', 'https', 'mailto']): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');
        $problems = [];

        if ($root === null) {
            return ['The output could not be parsed.'];
        }

        self::inspect($root, $problems, $schemes);

        return $problems;
    }

    /**
     * Walks the parsed tree and adds each problem it finds to the list.
     *
     * @param list<string> $problems
     * @param list<string> $schemes
     */
    private static function inspect(DOMNode $node, array &$problems, array $schemes): void
    {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (!in_array($tag, self::ELEMENTS, true)) {
                $problems[] = 'Disallowed element <' . $tag . '>';

                continue;
            }

            foreach ($child->attributes ?? [] as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;

                if (!in_array($name, self::ATTRIBUTES[$tag] ?? [], true)) {
                    $problems[] = 'Disallowed attribute ' . $name . ' on <' . $tag . '>';

                    continue;
                }

                $problem = self::checkValue($tag, $name, $value, $schemes);

                if ($problem !== null) {
                    $problems[] = $problem;
                }
            }

            self::inspect($child, $problems, $schemes);
        }
    }

    private static function checkValue(string $tag, string $name, string $value, array $schemes): ?string
    {
        switch ($name) {
            case 'href':
                return preg_match('~^(?:' . implode('|', array_map('preg_quote', $schemes)) . '):\S+$~i', $value) === 1
                    && preg_match('/^(?:javascript|vbscript|data|file|blob|about):/i', $value) !== 1
                    ? null
                    : 'Unsafe href: ' . $value;

            case 'open':
                return $value === '' ? null : 'Unexpected open: ' . $value;

            case 'width':
            case 'height':
                return preg_match('/^[0-9]{1,4}$/', $value) === 1 ? null : 'Unexpected ' . $name . ': ' . $value;

            case 'src':
                return preg_match('~^https://[a-z0-9.-]+(?::\d+)?(?:[/?#]\S*)?$~i', $value) === 1 ? null : 'Unsafe src: ' . $value;

            case 'class':
                return preg_match('/^(?:markdown-alert(?: markdown-alert-(?:note|tip|important|warning|caution))?|markdown-alert-title|math (?:inline|display)|math-(?:inline|display)|mermaid|language-[A-Za-z0-9_+.#-]+)$/', $value) === 1
                    ? null
                    : 'Unexpected class: ' . $value;

            case 'style':
                return preg_match('/^[a-z-]+:[#a-z0-9 .%-]+(?:;[a-z-]+:[#a-z0-9 .%-]+)*$/i', $value) === 1 ? null : 'Unexpected style: ' . $value;

            case 'rel':
                return $value === 'noopener noreferrer nofollow' ? null : 'Unexpected rel: ' . $value;

            case 'target':
                return in_array($value, ['_blank', '_self'], true) ? null : 'Unexpected target: ' . $value;

            case 'align':
                return in_array($value, ['left', 'center', 'right'], true) ? null : 'Unexpected align: ' . $value;

            case 'start':
                return ctype_digit($value) ? null : 'Unexpected start: ' . $value;

            case 'loading':
                return $value === 'lazy' ? null : 'Unexpected loading: ' . $value;

            case 'referrerpolicy':
                return $value === 'no-referrer' ? null : 'Unexpected referrerpolicy: ' . $value;
        }

        return null;
    }
}
