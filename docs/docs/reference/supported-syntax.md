---
sidebar_position: 1
---

# Supported Syntax

The converter supports a subset of CommonMark and GitHub Flavored Markdown. This page lists what it reads and what it leaves as plain text.

## Syntax the Converter Reads

| Syntax | Notes |
| --- | --- |
| Headings with `#` | One to six hashes. The converter drops closing hashes. |
| Paragraphs and line breaks | See the `softBreak` setting. |
| `*emphasis*`, `**strong**`, `~~strikethrough~~` | Underscores work for emphasis and strong. One or two tildes work for strikethrough. Emphasis follows the CommonMark rules, so `snake_case_names` stay as written. |
| Inline `code` | Any number of backticks, so code can contain backticks. |
| Fenced code blocks | Three or more backticks or tildes. The first word of the info string becomes a `language-` class, if it holds only letters, numbers, and a few symbols. |
| Block quotes | They can nest and can hold other blocks. A line that continues the paragraph above stays in the quote. |
| Lists | Bullets (`-`, `*`, `+`) and numbers (`1.` and `1)`). They can nest. A list that starts at another number keeps it. The converter tells tight and loose lists apart as CommonMark does. |
| Links | `[text](https://example.com "title")`, `<https://example.com>`, `<me@example.com>`, and bare `http` and `https` addresses. A link can't contain another link. See `baseUrl`, `linkSchemes`, `autolinkWww`, and `autolinkEmails`. |
| Images | `![description](https://github.com/a.png "title")`. The image shows only if `imageHosts` allows its host. Otherwise it becomes a link. |
| Tables | GitHub tables, with left, center, and right alignment. Write a pipe inside a cell as `\|`. A table needs a blank line before it. |
| Alerts | `> [!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, and `[!CAUTION]`, written on the first line of a quote, in any case. See [Alerts](../guides/alerts.md). |
| Horizontal rules | `---`, `***`, or `___`, with or without spaces between them. |
| Backslash escapes | `\*` shows a star. |
| HTML comments | The converter removes them from the output. |
| HTML tags | Shown as text, removed, or rebuilt from a short list. See [HTML](../guides/html.md). |

## Optional Syntax

Each of the following is off by default and has its own setting:

- Indented code, with `indentedCode`
- Setext headings, with `setextHeadings`
- Reference links, with `referenceLinks`
- Task lists, with `taskLists`
- Character references, with `decodeEntities`
- Emoji names, with `emoji` and `customEmoji`
- Inline diffs, with `inlineDiffs`
- `>>>` quotes, with `fencedQuotes`
- Math, with `math`, `mathFormat`, and `mathMaxLength`. See [Math](../settings/math.md).
- Mermaid diagrams, with `mermaid`. See [Render Diagrams](../guides/diagrams.md).

## Syntax the Converter Doesn't Read

The converter shows the following as plain text:

- Footnotes.
- `@mentions` and references such as `#123`, `!123`, and `~label`. They link to a repository, and the converter doesn't know which one.
- Syntax that needs headings to have ids or a page to be styled, such as GitLab's `[[_TOC_]]` and color chips.
- Emoji names that aren't in the built-in list, unless you add them with `customEmoji`.
- Reference link definitions that span more than one line.
