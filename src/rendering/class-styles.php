<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Rendering;

/**
 * Provides the inline `style` attributes that the `inlineStyles` setting adds,
 * for pages that have no stylesheet for the output.
 *
 * `Styles` defines every style.
 *
 * @internal
 */
final class Styles
{
    private const ALERT_COLORS = [
        'note' => '#0969da',
        'tip' => '#1a7f37',
        'important' => '#8250df',
        'warning' => '#9a6700',
        'caution' => '#cf222e',
    ];

    private const DEFAULT_COLOR = '#c3c4c7';

    /** Styles that are the same for every kind of alert. */
    private const FIXED = [
        'blockquote' => 'border-left:4px solid #c3c4c7;margin:0 0 16px;padding:0 1em;color:#50575e',
        'pre' => 'overflow:auto;padding:8px 12px;background:#f6f7f7;border:1px solid #dcdcde',
        'table' => 'border-collapse:collapse;margin:0 0 16px',
        'cell' => 'border:1px solid #c3c4c7;padding:4px 8px',
        'image' => 'max-width:100%;height:auto',
        'ins' => 'background:#ccffd8',
        'del' => 'background:#ffd7d5',
    ];

    /**
     * Creates a source of inline styles, turned on or off.
     *
     * @param bool $enabled True to add styles; false to add none.
     */
    public function __construct(private readonly bool $enabled)
    {
    }

    /**
     * Returns the `style` attribute for one part of the output.
     *
     * @param string $part The part to style: `blockquote`, `pre`, `table`,
     *     `cell`, `image`, `ins`, `del`, `alert`, or `alert-title`.
     * @param string $kind The kind of alert, for the `alert` and `alert-title`
     *     parts: `note`, `tip`, `important`, `warning`, or `caution`. The color
     *     comes from it, and any other value gets a neutral color.
     *
     * @return string The attribute with a leading space, or an empty string if
     *     styles are off or the part has no style.
     */
    public function attribute(string $part, string $kind = ''): string
    {
        if (!$this->enabled) {
            return '';
        }

        $color = self::ALERT_COLORS[$kind] ?? self::DEFAULT_COLOR;

        $css = match ($part) {
            'alert' => 'border-left:4px solid ' . $color . ';margin:0 0 16px;padding:0 1em',
            'alert-title' => 'color:' . $color . ';font-weight:600',
            default => self::FIXED[$part] ?? null,
        };

        return $css === null ? '' : ' style="' . $css . '"';
    }
}
