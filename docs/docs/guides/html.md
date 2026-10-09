---
sidebar_position: 3
---

# HTML

By default, HTML in the text shows as text. This page explains the other two modes of the `html` setting, and which tags the converter keeps in each.

The converter never copies a tag from the input to the output. In `'strip'` and `'sanitize'` modes it reads each tag, checks it, and writes it again from a fixed list. It drops event handlers, styles, and any attribute that isn't on the list for that tag.

## Compare the Modes

The [`html` setting](../settings/html-and-character-references.md#html) describes what each mode does. For example, with the input `Hello <b>bold</b> <script>x()</script> end`:

- `'escape'` gives `<p>Hello &lt;b&gt;bold&lt;/b&gt; ...</p>`, which shows the tags as text.
- `'strip'` gives `<p>Hello bold  end</p>`.
- `'sanitize'` gives `<p>Hello <strong>bold</strong>  end</p>`.

## Tags Kept in Sanitize Mode

The converter keeps the following tags when `html` is `'sanitize'`.

| Kind | Tags | Notes |
| --- | --- | --- |
| Inside a paragraph | `b`, `strong`, `i`, `em`, `u`, `s`, `strike`, `del`, `ins`, `kbd`, `sub`, `sup`, `mark`, `small`, `code` | The converter writes `b` and `i` as `strong` and `em`, and `s` and `strike` as `del`. It reads Markdown inside them, except inside `code` and `kbd`. |
| Inside a paragraph | `br`, `a`, `img` | `a` keeps `href` and `title`, and the address goes through the same checks as a Markdown link. `img` keeps `src`, `alt`, `title`, `width`, and `height`, and follows the `imageHosts` setting. A size must be a whole number. |
| On lines of their own | `details`, `summary`, `div`, `p`, `hr` | `details` keeps `open`. `div` and `p` keep `align` when it is `left`, `center`, or `right`. The converter reads the text between an opening and a closing tag as Markdown. A `p` that holds other blocks becomes a `div`. |

## What the Converter Removes

The converter removes every other tag and keeps the text inside it. It also removes a tag that is never closed, and a closing tag with nothing to close.

HTML comments are removed in every mode. The converter doesn't touch tags inside code spans and code blocks.

## Example

The following Markdown uses a `kbd` tag, a `span` with a style, and a `details` block:

```markdown
Press <kbd>Ctrl</kbd> and <b>go</b> <span style="x">text</span>

<details>
<summary>More</summary>

Hidden **text**.

</details>
```

With `html: 'sanitize'`, the converter writes:

```html
<p>Press <kbd>Ctrl</kbd> and <strong>go</strong> text</p>
<details>
<summary>More</summary>
<p>Hidden <strong>text</strong>.</p>
</details>
```

The `span` tag is not on the list, so the converter removes it and keeps its text.
