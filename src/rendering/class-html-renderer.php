<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Rendering;

use LogicException;
use ScottOffen\MarkdownConverter\Html;
use ScottOffen\MarkdownConverter\Math;
use ScottOffen\MarkdownConverter\Nodes\Alert;
use ScottOffen\MarkdownConverter\Nodes\CodeBlock;
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
use ScottOffen\MarkdownConverter\Parsing\InlineParser;

/**
 * Writes the tree of blocks from `BlockParser` as HTML.
 *
 * The text inside each block goes through `InlineParser`, and the inline styles
 * come from `Styles`.
 *
 * The converter makes one renderer for each text, because the reference link
 * definitions it needs belong to that text. A renderer never changes after it
 * is made.
 *
 * @internal
 */
final class HtmlRenderer
{
    /**
     * Creates a renderer for one text, with that text's reference link
     * definitions.
     *
     * @param Options $options The settings.
     * @param InlineParser $inline The parser for the text inside blocks.
     * @param Styles $styles The source of inline styles.
     * @param array<string, array{url: string, title: string|null}> $definitions
     *     The reference link definitions of the text being written, keyed by
     *     `ReferenceLabel::normalize()`.
     */
    public function __construct(
        private readonly Options $options,
        private readonly InlineParser $inline,
        private readonly Styles $styles,
        private readonly array $definitions = [],
    ) {
    }

    /**
     * Writes blocks as HTML.
     *
     * @param list<Node> $nodes The blocks to write.
     *
     * @return string The HTML, with a newline between blocks.
     */
    public function render(array $nodes): string
    {
        return $this->nodes($nodes, false);
    }

    /**
     * Writes a list of blocks, one after another.
     *
     * @param list<Node> $nodes The blocks to write.
     * @param bool $tight True to write paragraphs without `p` tags, as in a
     *     tight list; false to include them.
     *
     * @return string The HTML for the blocks, separated by newlines. A block
     *     that writes nothing is left out.
     */
    private function nodes(array $nodes, bool $tight): string
    {
        $parts = [];

        foreach ($nodes as $node) {
            $html = $this->node($node, $tight);

            if ($html !== '') {
                $parts[] = $html;
            }
        }

        return implode("\n", $parts);
    }

    private function node(Node $node, bool $tight): string
    {
        return match (true) {
            $node instanceof Paragraph => $this->paragraph($node, $tight),
            $node instanceof Heading => '<h' . $node->level . '>' . $this->inline->parse($node->raw, $this->definitions) . '</h' . $node->level . '>',
            $node instanceof HtmlLine => '<' . $node->tag . $node->attributes . '>' . $this->inline->parse($node->raw, $this->definitions) . '</' . $node->tag . '>',
            $node instanceof HtmlBlock => $this->htmlBlock($node),
            $node instanceof Rule => '<hr>',
            $node instanceof CodeBlock => $this->code($node),
            $node instanceof MathBlock => Math::html($node->tex, true, true, $this->options->mathFormat),
            $node instanceof MermaidBlock => '<pre class="mermaid"' . $this->styles->attribute('pre') . '>' . Html::text($node->source) . '</pre>',
            $node instanceof Quote => '<blockquote' . $this->styles->attribute('blockquote') . ">\n" . $this->wrap($this->nodes($node->children, false)) . '</blockquote>',
            $node instanceof Alert => $this->alert($node),
            $node instanceof ListBlock => $this->list($node),
            $node instanceof Table => $this->table($node),
            default => throw new LogicException('There is no way to write a ' . $node::class . ' as HTML.'),
        };
    }

    private function paragraph(Paragraph $node, bool $tight): string
    {
        $inline = $node->plain ? Html::text($node->raw) : $this->inline->parse($node->raw, $this->definitions);

        if ($inline === '') {
            return '';
        }

        return $tight ? $inline : '<p>' . $inline . '</p>';
    }

    private function code(CodeBlock $node): string
    {
        $class = $node->language !== '' ? ' class="language-' . Html::attr($node->language) . '"' : '';
        $text = $node->text !== '' ? Html::text($node->text) . "\n" : '';

        return '<pre' . $this->styles->attribute('pre') . '><code' . $class . '>' . $text . '</code></pre>';
    }

    private function alert(Alert $node): string
    {
        $kind = $node->kind->value;

        return '<div class="markdown-alert markdown-alert-' . $kind . '"' . $this->styles->attribute('alert', $kind) . ">\n"
            . '<p class="markdown-alert-title"' . $this->styles->attribute('alert-title', $kind) . '>' . ucfirst($kind) . "</p>\n"
            . $this->wrap($this->nodes($node->children, false)) . '</div>';
    }

    private function list(ListBlock $node): string
    {
        $tag = $node->ordered ? 'ol' : 'ul';
        $start = $node->ordered && $node->start !== 1 ? ' start="' . $node->start . '"' : '';
        $html = '<' . $tag . $start . ">\n";

        foreach ($node->items as $children) {
            $html .= $this->item($children, $node->loose) . "\n";
        }

        return $html . '</' . $tag . '>';
    }

    /**
     * Writes one list item.
     *
     * @param list<Node> $children The blocks inside the item.
     * @param bool $loose True if the list is loose, so paragraphs keep their
     *     `p` tags; false otherwise.
     *
     * @return string The `li` element.
     */
    private function item(array $children, bool $loose): string
    {
        if ($children === []) {
            return '<li></li>';
        }

        if ($loose) {
            return "<li>\n" . $this->nodes($this->withTaskBox($children), false) . "\n</li>";
        }

        $html = $this->nodes($this->withTaskBox($children), true);
        $leading = $children[0] instanceof Paragraph ? '' : "\n";
        $trailing = $children[count($children) - 1] instanceof Paragraph ? '' : "\n";

        return '<li>' . $leading . $html . $trailing . '</li>';
    }

    private function table(Table $node): string
    {
        $cell = function (string $tag, string $html, ?string $align): string {
            $attributes = $align !== null ? ' align="' . $align . '"' : '';

            return '<' . $tag . $attributes . $this->styles->attribute('cell') . '>' . $html . '</' . $tag . '>';
        };

        $html = '<table' . $this->styles->attribute('table') . ">\n<thead>\n<tr>\n";

        foreach ($node->head as $index => $content) {
            $html .= $cell('th', $this->inline->parse($content, $this->definitions), $node->alignments[$index]) . "\n";
        }

        $html .= "</tr>\n</thead>\n";

        if ($node->rows !== []) {
            $html .= "<tbody>\n";

            foreach ($node->rows as $row) {
                $html .= "<tr>\n";

                foreach ($row as $index => $content) {
                    $html .= $cell('td', $this->inline->parse($content, $this->definitions), $node->alignments[$index]) . "\n";
                }

                $html .= "</tr>\n";
            }

            $html .= "</tbody>\n";
        }

        return $html . '</table>';
    }

    private function htmlBlock(HtmlBlock $node): string
    {
        // A `p` element can't contain paragraphs, so the renderer writes a `p`
        // block that holds other blocks as a `div`.
        $tag = $node->tag === 'p' ? 'div' : $node->tag;

        return '<' . $tag . $node->attributes . ">\n" . $this->wrap($this->nodes($node->children, false)) . '</' . $tag . '>';
    }

    /**
     * Turns a leading `[ ]` or `[x]` in a list item into a box character when
     * the `taskLists` setting is on.
     *
     * @param list<Node> $children The blocks inside the item.
     *
     * @return list<Node> The same blocks, with the marker in the first
     *     paragraph replaced by a box character if there is one.
     */
    private function withTaskBox(array $children): array
    {
        $first = $children[0];

        if (
            !$this->options->taskLists
            || !$first instanceof Paragraph
            || $first->plain
            || preg_match('/^\[([ xX])\][ \t]+/', $first->raw, $m) !== 1
        ) {
            return $children;
        }

        $children[0] = new Paragraph(($m[1] === ' ' ? "\u{2610}" : "\u{2611}") . ' ' . substr($first->raw, strlen($m[0])));

        return $children;
    }

    /**
     * Puts the contents of a container on their own lines.
     *
     * @param string $html The contents.
     *
     * @return string The contents followed by a newline, or an empty string if
     *     there are none.
     */
    private function wrap(string $html): string
    {
        return $html === '' ? '' : $html . "\n";
    }
}
