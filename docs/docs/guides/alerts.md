---
sidebar_position: 2
---

# Alerts

An alert is a block quote that the converter shows as a highlighted box. Alerts use [GitHub's syntax](https://docs.github.com/en/get-started/writing-on-github/getting-started-with-writing-and-formatting-on-github/basic-writing-and-formatting-syntax#alerts), so Markdown written for GitHub works without changes.

## Write an Alert

Start a block quote with a marker on its own line. The marker is `[!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, or `[!CAUTION]`, in any case. Write the content of the alert on the lines that follow:

```markdown
> [!WARNING]
> Back up your site before updating.
```

The converter writes the following HTML:

```html
<div class="markdown-alert markdown-alert-warning">
<p class="markdown-alert-title">Warning</p>
<p>Back up your site before updating.</p>
</div>
```

The marker must be on a line by itself. If other text follows it on the same line, such as a title, the converter makes an ordinary block quote and not an alert.

An alert can hold any content that a block quote can, including lists, code blocks, and links.

With the `fencedQuotes` setting on, an alert can also sit between two `>>>` lines, as in GitLab. The first line is then `>>> [!NOTE]`.

## Style an Alert

The converter writes a `markdown-alert` class on every alert, and a class for each kind, such as `markdown-alert-warning`. Use these classes in your own stylesheet.

If the page has no stylesheet for alerts, turn on the `inlineStyles` setting. Each kind of alert then gets its own border and title color. With `inlineStyles` on, the previous example becomes:

```html
<div class="markdown-alert markdown-alert-warning" style="border-left:4px solid #9a6700;margin:0 0 16px;padding:0 1em">
<p class="markdown-alert-title" style="color:#9a6700;font-weight:600">Warning</p>
<p>Back up your site before updating.</p>
</div>
```

## Limits of Alert Titles

The title comes from the kind of alert, and you can't change it. It is plain text with no icon, and it is always in English. The converter doesn't translate it.
