---
sidebar_position: 1
---

# Output

These settings change how the converter writes its HTML.

## `headingOffset`

Default: `0`

The number of levels to push headings down, from 0 to 5. With `4`, `#` becomes an `h5` and every deeper level becomes an `h6`. Use it when the result goes into a page that has its own headings.

```php
$converter = new MarkdownConverter(headingOffset: 4);

$converter->toHtml("# Title\n\n## Sub");
// <h5>Title</h5>
// <h6>Sub</h6>
```

## `softBreak`

Default: `'break'`

What a single line break inside a paragraph becomes.

| Value | Result |
| --- | --- |
| `'break'` | A line break (`<br>`), as GitHub does in comments and release notes. |
| `'space'` | A space, as in a README file. |
| `'newline'` | A newline character, which HTML shows as a space. |

A hard break, made with two spaces or a backslash at the end of a line, is always a line break.

```php
$converter = new MarkdownConverter(softBreak: 'space');

$converter->toHtml("line one\nline two");
// <p>line one line two</p>
```

## `inlineStyles`

Default: `false`

Adds small inline styles to tables, quotes, alerts, code blocks, images, inline diffs, and Mermaid diagrams. Turn it on when the HTML goes somewhere with no stylesheet for it. With the setting on, each kind of alert gets its own color.

## `linkTarget`

Default: `'_blank'`

The `target` attribute of every link. Use `'_self'` to open links in the same tab, or an empty string to leave the attribute out.

## `maxLength`

Default: `125000`

The most input, in bytes, that the converter reads. It leaves out the rest and adds a note that says so, and it never cuts a character in half. GitHub limits release notes to 125,000 characters, so the default matches that limit.

```php
$converter = new MarkdownConverter(maxLength: 20);

$converter->toHtml("This text is longer than twenty bytes.");
// <p>This text is longer</p>
// <p><em>The rest of this text was left out because it is too long.</em></p>
```

The note is always in English.

## `maxDepth`

Default: `8`

How deeply quotes, lists, and HTML blocks can nest, from 1 to 20. The converter shows anything deeper as plain text.
