---
sidebar_position: 2
---

# Output Format

This page shows the HTML that the converter writes for each kind of Markdown.

| Markdown | HTML |
| --- | --- |
| Link | `<a href="…" rel="noopener noreferrer nofollow" target="_blank">` |
| Image | `<img src="…" alt="…" loading="lazy" referrerpolicy="no-referrer">`, with `width` and `height` when an HTML `img` gave whole numbers |
| Code block | `<pre><code class="language-php">…</code></pre>` |
| Mermaid diagram | `<pre class="mermaid">…</pre>` with `mermaid` on |
| Table cell | `<th align="center">` and `<td align="right">`, with no attribute when there is no alignment |
| Numbered list | `<ol>`, or `<ol start="3">` when it starts at another number |
| Alert | `<div class="markdown-alert markdown-alert-note">` with a `<p class="markdown-alert-title">` |
| Task list box | The character `☐` or `☑` at the start of the item |
| Inline diff | `<ins>` and `<del>`, with a background color when `inlineStyles` is on |
| Math inside a line | `<span class="math inline">\(…\)</span>` with `math` on and the default `mathFormat` |
| Display math | `<span class="math display">\[…\]</span>` inside a paragraph, or `<div class="math display">\[…\]</div>` for a `$$` block or a `math` fence |

The `mathFormat` setting changes how the converter writes math. See [Math](../settings/math.md). The `target` attribute on a link depends on the `linkTarget` setting. With `inlineStyles` on, tables, quotes, alerts, code blocks, images, inline diffs, and Mermaid diagrams also carry a `style` attribute.

`toHtml()` returns the HTML as a string with no wrapping element. The converter can write the following tags: `p`, `br`, `h1` to `h6`, `em`, `strong`, `del`, `code`, `pre`, `a`, `img`, `ul`, `ol`, `li`, `blockquote`, `hr`, `table`, `thead`, `tbody`, `tr`, `th`, `td`, and `div`. With `html` set to `'sanitize'`, it can also write `details`, `summary`, `u`, `ins`, `kbd`, `sub`, `sup`, `mark`, and `small`. With `inlineDiffs` on, it can write `ins` too. With `math` on, it can write `span`, with only a `class` attribute that is `math inline`, `math display`, `math-inline`, or `math-display`. With `mermaid` on, a `pre` element can have the class `mermaid`.
