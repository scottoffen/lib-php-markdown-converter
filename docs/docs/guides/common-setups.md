---
sidebar_position: 1
---

# Common Setups

This page shows complete converter configurations for three common uses. Each one lists the settings it uses and why. Start from the one closest to your use case, and then change what you need.

## Release Notes

To display release notes from GitHub in a browser, such as in a WordPress administrator screen:

```php
$converter = new MarkdownConverter(
    imageHosts: ['github.com', '*.githubusercontent.com'],
    html: 'sanitize',
    inlineStyles: true,
    headingOffset: 4,
    referenceLinks: true,
    emoji: true,
);
```

- `imageHosts` allows images from the hosts GitHub uses for pictures in release notes.
- `html` set to `'sanitize'` keeps a short list of tags that people commonly write in release notes, such as `details` and `kbd`. See [HTML](html.md).
- `inlineStyles` adds styles to tables, alerts, and code blocks, because the page that shows the notes has no stylesheet for them.
- `headingOffset` set to `4` turns `#` into an `h5` and every deeper level into an `h6`, so headings in the notes don't compete with the headings of the page around them.
- `referenceLinks` reads links written as `[text][label]`, which release notes generated from commit lists often use.
- `emoji` shows names such as `:bug:` and `:tada:` as emoji, as GitHub does.

The default `softBreak` setting turns a single newline into a line break, which matches how GitHub shows release notes.

## Documentation Pages

To display a documentation page, with links relative to the documentation and no line break at a single newline:

```php
$converter = new MarkdownConverter(
    softBreak: 'space',
    baseUrl: 'https://example.com/docs',
    imageHosts: ['example.com'],
    html: 'sanitize',
    setextHeadings: true,
    referenceLinks: true,
    indentedCode: true,
);
```

- `softBreak` set to `'space'` joins the lines of a paragraph, as a README file does.
- `baseUrl` joins relative links and images to your documentation address.
- `imageHosts` allows images from your own host.
- `setextHeadings`, `referenceLinks`, and `indentedCode` turn on older Markdown syntax that documentation written for other tools often uses.

## Visitor Comments

To display comments from visitors, use the default settings:

```php
$converter = new MarkdownConverter();
```

With the defaults, HTML shows as text, no image loads, and links can use only `http`, `https`, and `mailto`. Visitors can still write tables, alerts, and links.
