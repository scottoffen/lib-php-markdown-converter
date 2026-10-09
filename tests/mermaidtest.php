<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use DOMDocument;
use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Tests the `mermaid` setting: a fenced `mermaid` block becomes a `pre` element
 * that holds the escaped diagram source, and every other fence stays code.
 */
final class MermaidTest extends MarkdownTestCase
{
    private const ON = ['mermaid' => true];

    /** Wraps escaped diagram source in the element the converter writes. */
    private function diagram(string $escaped): string
    {
        return '<pre class="mermaid">' . $escaped . '</pre>';
    }

    // ---- off by default ----

    public function testADiagramIsCodeByDefault(): void
    {
        $this->assertConverts("<pre><code class=\"language-mermaid\">graph TD\nA--&gt;B\n</code></pre>", "```mermaid\ngraph TD\nA-->B\n```");
    }

    // ---- fences ----

    public function testAMermaidFenceMakesADiagram(): void
    {
        $this->assertAllConvert([
            'a diagram' => ["```mermaid\ngraph TD\nA-->B\n```", $this->diagram("graph TD\nA--&gt;B")],
            'a tilde fence' => ["~~~mermaid\ngraph TD\n~~~", $this->diagram('graph TD')],
            'text after the word' => ["```mermaid extra\ngraph TD\n```", $this->diagram('graph TD')],
            'a longer fence' => ["````mermaid\ngraph TD\n````", $this->diagram('graph TD')],
            'no closing fence runs to the end' => ["```mermaid\ngraph TD\nA-->B", $this->diagram("graph TD\nA--&gt;B")],
            'text around it' => ["Before\n\n```mermaid\ngraph TD\n```\n\nAfter", "<p>Before</p>\n" . $this->diagram('graph TD') . "\n<p>After</p>"],
            'two diagrams' => ["```mermaid\na\n```\n\n```mermaid\nb\n```", $this->diagram('a') . "\n" . $this->diagram('b')],
        ], self::ON);
    }

    public function testIndentationInsideADiagramIsKept(): void
    {
        $this->assertConverts($this->diagram("mindmap\n  root\n    child\n      leaf"), "```mermaid\nmindmap\n  root\n    child\n      leaf\n```", self::ON);
        $this->assertConverts($this->diagram("mindmap\n  root"), "   ```mermaid\n   mindmap\n     root\n   ```", self::ON, 'The indentation of the fence is removed, and the rest is kept.');
    }

    public function testBlankLinesAtEitherEndAreDropped(): void
    {
        $this->assertConverts($this->diagram("graph LR\n\nA--&gt;B"), "```mermaid\n\n\ngraph LR\n\nA-->B\n\n\n```", self::ON);
    }

    public function testOtherFencesStayCode(): void
    {
        $this->assertAllConvert([
            'an empty diagram' => ["```mermaid\n```", '<pre><code class="language-mermaid"></code></pre>'],
            'a blank diagram' => ["```mermaid\n\n  \n```", "<pre><code class=\"language-mermaid\">\n  \n</code></pre>"],
            'a different case' => ["```Mermaid\nx\n```", "<pre><code class=\"language-Mermaid\">x\n</code></pre>"],
            'another language' => ["```plantuml\nx\n```", "<pre><code class=\"language-plantuml\">x\n</code></pre>"],
            'a word that starts with mermaid' => ["```mermaids\nx\n```", "<pre><code class=\"language-mermaids\">x\n</code></pre>"],
            'a fence with no language' => ["```\nx\n```", "<pre><code>x\n</code></pre>"],
        ], self::ON);
    }

    public function testADiagramInsideOtherBlocks(): void
    {
        $this->assertAllConvert([
            'a quote' => ["> ```mermaid\n> graph TD\n> ```", "<blockquote>\n" . $this->diagram('graph TD') . "\n</blockquote>"],
            'a list item' => ["- a\n\n  ```mermaid\n  graph TD\n  ```", "<ul>\n<li>\n<p>a</p>\n" . $this->diagram('graph TD') . "\n</li>\n</ul>"],
            'an alert' => ["> [!NOTE]\n> ```mermaid\n> graph TD\n> ```", "<div class=\"markdown-alert markdown-alert-note\">\n<p class=\"markdown-alert-title\">Note</p>\n" . $this->diagram('graph TD') . "\n</div>"],
            'a code fence that holds a fence' => ["````\n```mermaid\ngraph TD\n```\n````", "<pre><code>```mermaid\ngraph TD\n```\n</code></pre>"],
        ], self::ON);
    }

    public function testADiagramIsNotLimitedInLength(): void
    {
        $source = implode("\n", array_fill(0, 2000, 'A --> B'));

        $this->assertConverts($this->diagram(str_replace('>', '&gt;', $source)), "```mermaid\n$source\n```", self::ON);
    }

    // ---- escaping ----

    public function testTheSourceIsEscaped(): void
    {
        $this->assertAllConvert([
            'arrows and comparisons' => ["```mermaid\nA --> B\nC -- x < y --> D\n```", $this->diagram("A --&gt; B\nC -- x &lt; y --&gt; D")],
            'ampersands and quotes' => ["```mermaid\nA[\"a & b\"]\n```", $this->diagram('A[&quot;a &amp; b&quot;]')],
            'html in a label' => ["```mermaid\nA[<b>x</b>]\n```", $this->diagram('A[&lt;b&gt;x&lt;/b&gt;]')],
            'a closing tag' => ["```mermaid\n</pre>\n```", $this->diagram('&lt;/pre&gt;')],
            'markdown is not read' => ["```mermaid\nA[*x* _y_ `z`]\n```", $this->diagram('A[*x* _y_ `z`]')],
        ], self::ON);
    }

    /**
     * Checks that a browser reads back the diagram the author wrote. Mermaid
     * reads the text of the element, so escaping must not change it.
     */
    public function testTheTextOfTheElementIsTheSourceAsWritten(): void
    {
        $sources = [
            "graph TD\n  A[\"x < y & z\"] --> B{\"a > b\"}\n  B -->|yes| C\n",
            "sequenceDiagram\n  Alice->>Bob: <b>Hello</b> &amp; \"hi\"\n  Bob-->>Alice: ok\n",
            "mindmap\n  root\n    <script>alert(1)</script>\n      leaf\n",
            "flowchart LR\n  A --> B\n  click A href \"https://x.test\" _blank\n",
            "pie title Pets\n  \"Dogs\" : 386\n  \"Cats\" : 85\n",
        ];

        foreach ($sources as $source) {
            $html = $this->html("```mermaid\n$source```", self::ON);
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $pres = $document->getElementsByTagName('pre');

            $this->assertSame(1, $pres->length, $source);
            $this->assertSame('mermaid', $pres->item(0)?->attributes?->getNamedItem('class')?->nodeValue, $source);
            $this->assertSame(rtrim($source, "\n"), $pres->item(0)?->textContent, $source);
            $this->assertSafe($html, $source);
        }
    }

    // ---- other settings ----

    public function testInlineStylesGiveTheElementTheCodeBlockStyle(): void
    {
        $html = $this->html("```mermaid\ngraph TD\n```", self::ON + ['inlineStyles' => true]);

        $this->assertStringStartsWith('<pre class="mermaid" style="', $html);
        $this->assertSafe($html);
    }

    public function testADiagramWorksWithMath(): void
    {
        $html = $this->html("```mermaid\nA[\$x\$] --> B\n```\n\n\$y\$\n\n```math\nz\n```", self::ON + ['math' => true]);

        $this->assertStringContainsString('<pre class="mermaid">A[$x$] --&gt; B</pre>', $html);
        $this->assertStringContainsString('<span class="math inline">\\(y\\)</span>', $html);
        $this->assertStringContainsString('<div class="math display">\\[z\\]</div>', $html);
    }
}
