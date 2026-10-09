---
sidebar_position: 1
sidebar_label: Getting Started
slug: /
---

# Getting Started

Markdown Converter turns Markdown into HTML that is safe to show on a web page. It was written to display GitHub release notes in WordPress, but nothing in it depends on GitHub, release notes, or WordPress. Use it for any Markdown you didn't write yourself, such as documentation, descriptions, and comments.

The default settings are safe without any configuration. You change settings to adjust how the output looks or to turn on extra syntax.

## Requirements

- PHP 8.1 or later
- The mbstring extension

The package has no other dependencies.

## Installation

Install the package with Composer:

```bash
composer require scottoffen/markdown-converter
```

## Convert Markdown

Create a `MarkdownConverter` and call `toHtml()` with your Markdown:

```php
use ScottOffen\MarkdownConverter\MarkdownConverter;

$markdown = <<<'MD'
# Release notes

Fixed a **crash** on `save`. See [the guide](https://example.com/guide).

<script>alert(1)</script>

![Chart](https://example.com/chart.png)
MD;

$html = (new MarkdownConverter())->toHtml($markdown);
```

With the default settings, `$html` contains the following:

```html
<h1>Release notes</h1>
<p>Fixed a <strong>crash</strong> on <code>save</code>. See <a href="https://example.com/guide" rel="noopener noreferrer nofollow" target="_blank">the guide</a>.</p>
<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>
<p><a href="https://example.com/chart.png" rel="noopener noreferrer nofollow" target="_blank">Chart</a></p>
```

The converter shows the `script` tag as text, and it turns the image into a link because you haven't listed any image hosts. `toHtml()` returns a string with no wrapping element, so you can place it inside any container.

You can reuse one converter for as many conversions as you like. It keeps no state between calls, which makes it safe to use in a long-running process.

## Change Settings

Pass settings by name when you create the converter. For example, the following converter loads images from two hosts, adds small inline styles to tables and alerts, and moves every heading down four levels:

```php
$converter = new MarkdownConverter(
    imageHosts: ['github.com', '*.githubusercontent.com'],
    inlineStyles: true,
    headingOffset: 4,
);
```

Every setting is optional, and the [Settings](/settings) section describes each one.

## Next Steps

- [Common Setups](guides/common-setups.md) shows complete configurations for release notes, documentation pages, and visitor comments.
- [Render Math](guides/math.md) shows how to turn on math and render it with KaTeX or MathJax.
- [Render Diagrams](guides/diagrams.md) shows how to turn on Mermaid diagrams.
- [Settings](/settings) lists every setting, its default, and what it does.
- [Supported Syntax](reference/supported-syntax.md) lists what the converter reads and what it leaves as plain text.
- [Security](security.md) explains how the converter keeps its output safe.
