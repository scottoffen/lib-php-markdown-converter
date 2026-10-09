---
sidebar_position: 5
---

# Emoji and GitLab-Style Syntax

These settings turn on emoji names, and two kinds of syntax that GitLab adds to Markdown: inline diffs and `>>>` quotes. None of them needs to know which repository the text came from.

## `emoji`

Default: `false`

Shows names such as `:tada:` and `:bug:` as the emoji they stand for. The converter has 116 common names built in, using the names that [GitHub](https://api.github.com/emojis), [GitLab](https://docs.gitlab.com/user/markdown/#emoji), and [Gitea](https://github.com/go-gitea/gitea/blob/main/public/assets/emoji.json) share.

A name can't directly follow a letter or a number, so a time such as `12:30:45` stays as written. Names inside code stay as written.

## `customEmoji`

Default: `[]`

Extra names, or replacements for built-in ones, as `'name' => 'text'`. The converter uses them only when `emoji` is on.

A name is lowercase letters, numbers, `_`, `+`, or `-`. The text shows as text, never as markup.

```php
$converter = new MarkdownConverter(
    emoji: true,
    customEmoji: ['ship' => '🚢'],
);

$converter->toHtml(':ship: :tada:');
// <p>🚢 🎉</p>
```

## `inlineDiffs`

Default: `false`

`{+ added +}` and `{- removed -}`, and the same in square brackets, become `<ins>` and `<del>`, as in GitLab. With `inlineStyles` on, each one gets a background color.

Each mark needs a space inside it, so `[-1]` stays as written. A diff can't span lines.

## `fencedQuotes`

Default: `false`

Text between two lines of `>>>` is a quote, as in GitLab. The first line can be `>>> [!NOTE]` to make an alert.

Without a closing line, the quote runs to the end of the text. A `>>>` followed by other text on the same line is still three nested quotes.

```markdown
>>>
First paragraph of the quote.

Second paragraph of the quote.
>>>
```
