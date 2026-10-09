<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

/**
 * Normalizes the labels of reference links, so that a link can be matched to
 * its definition.
 *
 * A label is the `docs` in `[the docs][docs]` and in
 * `[docs]: https://example.com`.
 *
 * @internal
 */
final class ReferenceLabel
{
    /**
     * Makes a label comparable with another by ignoring case and collapsing
     * runs of whitespace.
     *
     * @param string $label The label as written.
     *
     * @return string The normalized label.
     */
    public static function normalize(string $label): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $label)));
    }
}
