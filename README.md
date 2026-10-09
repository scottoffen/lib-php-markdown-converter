# <img src="./logo.svg" height="32" valign="bottom"> Markdown Converter

Converts Markdown to safe HTML, with GitHub-style tables, images, and alerts.

`scottoffen/markdown-converter` converts Markdown to HTML that is safe to show on a web page. Use it for release notes, documentation, descriptions, comments, or any other Markdown you didn't write yourself. The default settings are safe without any changes, and you can configure them to fit your use case.

```php
use ScottOffen\MarkdownConverter\MarkdownConverter;

$html = (new MarkdownConverter())->toHtml($markdown);
```

## Features

- HTML in the text shows as text, and the converter never copies a tag from the input to the output.
- Links can use only `http`, `https`, and `mailto`.
- Images load only from hosts you list. Every other image becomes a link.
- Tables with alignment, the five GitHub alerts, strikethrough, and bare web addresses.
- Optional math syntax, `$...$`, `$$...$$`, and fenced `math` blocks, for pages that render it with KaTeX or MathJax.
- Optional Mermaid diagrams, written as an element that the Mermaid library on your page can render.
- Limits on input size and nesting depth keep conversion fast on large or malformed text.
- No dependencies other than PHP and the mbstring extension.

## Requirements

- PHP 8.1 or later
- The mbstring extension

## Installation

```bash
composer require scottoffen/markdown-converter
```

## Usage

```php
use ScottOffen\MarkdownConverter\MarkdownConverter;

$converter = new MarkdownConverter();

$html = $converter->toHtml($markdown);
```

`toHtml()` returns a string of HTML with no wrapping element, so you can place it inside any container. To change a setting, pass it by name when you create the converter:

```php
$converter = new MarkdownConverter(
    imageHosts: ['github.com', '*.githubusercontent.com'],
    inlineStyles: true,
);
```

Every setting is optional. The [documentation](https://scottoffen.github.io/lib-php-markdown-converter/) describes each one, the supported syntax, and how the converter keeps its output safe.

## Security

The converter treats all input as untrusted, but it has not had a formal security audit. See [Security](https://scottoffen.github.io/lib-php-markdown-converter/security) for how the converter protects its output and what else you can add.

## Support

If something doesn't convert the way you expect, [open an issue](https://github.com/scottoffen/lib-php-markdown-converter/issues).

## License

Released under the MIT License.
