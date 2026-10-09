---
sidebar_position: 3
---

# HTML and Character References

These settings control what happens to HTML tags and character references in the text.

## `html`

Default: `'escape'`

What happens to HTML in the text.

| Value | Result |
| --- | --- |
| `'escape'` | HTML shows as text. |
| `'strip'` | The converter removes the tags and keeps the text between them. It removes `script` and `style` elements along with what is inside them. |
| `'sanitize'` | The converter keeps a short list of tags, written from scratch, and removes the rest. |

See [HTML](../guides/html.md) for the list of tags that `'sanitize'` keeps.

## `decodeEntities`

Default: `false`

Shows character references such as `&copy;`, `&#169;`, and `&#x1F389;` as the characters they name.

A reference to `<` shows as the text `<`, not as a tag. A number that isn't a character, such as `&#0;`, or that names a control character, shows as the replacement character, `U+FFFD`.

```php
$converter = new MarkdownConverter(decodeEntities: true);

$converter->toHtml('&copy; &lt;b&gt;');
// <p>© &lt;b&gt;</p>
```
