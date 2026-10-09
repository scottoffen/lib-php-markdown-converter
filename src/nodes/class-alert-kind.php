<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Nodes;

/**
 * The five kinds of GitHub alert: note, tip, important, warning, and caution.
 *
 * The value of each case is the lowercase name. The renderer appends it to
 * `markdown-alert-` to make the CSS class.
 *
 * @internal
 */
enum AlertKind: string
{
    case Note = 'note';
    case Tip = 'tip';
    case Important = 'important';
    case Warning = 'warning';
    case Caution = 'caution';
}
