---
sidebar_position: 6
---

# Math

These settings turn on math syntax. The converter doesn't render math. It finds the TeX, escapes it, and writes it where a library on your page, such as KaTeX or MathJax, can find it. See [Render Math](../guides/math.md) for how to set that up.

## `math`

Default: `false`

Reads the following syntax as math. With the setting off, the converter shows all of it as plain text, and a fenced `math` block as an ordinary code block.

| Syntax | Kind of math |
| --- | --- |
| `$x^2$` | Math inside a line of text. |
| `$$x^2$$` | Display math inside a paragraph. It can span lines. |
| A line with only `$$`, the TeX, and a second line with only `$$` | A block of display math. The TeX can hold blank lines and lines that look like other Markdown, such as `- a` or `# b`. |
| A fenced block that starts with `` ```math `` | A block of display math. |

The converter doesn't read the TeX as Markdown, so an `_` or a `*` inside it doesn't make emphasis, and a `\` stays a backslash.

### What Counts as Math

A single `$` follows the rule that pandoc uses, so prices stay plain text:

- The character after the opening `$` can't be whitespace.
- The character before the closing `$` can't be whitespace.
- The character after the closing `$` can't be a digit.

With these rules, `It costs $5 and $10` shows as written, and `Let $x$ be 1` is math. A `$` that a backslash escapes, as in `\$`, is a dollar sign and never opens or closes math. Inside math, `\$` is TeX for a dollar sign.

A `$$` block needs a closing line. If there is none, or the block is empty, the converter shows the `$$` line as text, so a stray `$$` can't swallow the rest of the page. Three or more `$` in a row are plain text.

Pipes in a table cell need an escape, because the converter splits a row at each `|` before it reads the cell. Write `$a \| b$` for the math `a | b`.

```php
$converter = new MarkdownConverter(math: true);

$converter->toHtml('Let $x$ be 1.');
// <p>Let <span class="math inline">\(x\)</span> be 1.</p>
```

## `mathFormat`

Default: `'pandoc'`

How the converter writes math. The converter uses this setting only when `math` is on. The following table shows the same input in each format:

| Input | `'pandoc'` | `'class'` | `'delimiters'` |
| --- | --- | --- | --- |
| `$a_b$` | `<span class="math inline">\(a_b\)</span>` | `<span class="math-inline">a_b</span>` | `$a_b$` |
| `$$a < b$$` | `<span class="math display">\[a &lt; b\]</span>` | `<span class="math-display">a &lt; b</span>` | `$$a &lt; b$$` |
| A `$$` block or `math` fence | `<div class="math display">\[a &lt; b\]</div>` | `<div class="math-display">a &lt; b</div>` | A `p` element that holds `$$`, the TeX, and `$$` on three lines |

- `'pandoc'` writes the TeX between `\(` and `\)` or between `\[` and `\]`, inside an element with the classes `math inline` or `math display`. MathJax and KaTeX's auto-render extension read this with their default settings.
- `'class'` writes the bare TeX, with no delimiters, inside an element with the class `math-inline` or `math-display`. Use it when your page renders each element itself, for example by calling `katex.render()`.
- `'delimiters'` writes the TeX with its own `$` or `$$` delimiters and no element around it. Use it when the page's math library is set up to find `$` delimiters in text. A block of display math goes in a `p` element.

In every format, the converter escapes the TeX like any other text.

## `mathMaxLength`

Default: `1000`

The longest piece of math, in bytes, that the converter reads, from 1 to 10000. Math longer than the limit shows as plain text, and a fenced `math` block longer than the limit shows as a code block. The converter uses this setting only when `math` is on.

The limit applies to the TeX between the delimiters. It keeps the work the converter does on one paragraph small, which is why the setting has an upper bound. A long `align` environment can pass 1,000 bytes, so raise the setting if your equations are that long.

In one paragraph, the converter tries at most 200 places that could start math. After that, a `$` is plain text. See [Limitations](../reference/limitations.md).
