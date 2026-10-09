---
sidebar_position: 2
---

# Links and Images

These settings control which addresses links and images can use.

## `imageHosts`

Default: `[]`

The hosts that images can load from. With the empty list, no image loads and each one becomes a link. Images must use `https`.

| Value | What it allows |
| --- | --- |
| `['github.com', '*.githubusercontent.com']` | The hosts that GitHub uses. |
| `'*.example.com'` | Subdomains of `example.com`, but not `example.com` itself. |
| `'*'` | Every host. |

```php
$converter = new MarkdownConverter(imageHosts: ['example.com']);

$converter->toHtml('![Chart](https://example.com/chart.png)');
// <p><img src="https://example.com/chart.png" alt="Chart" loading="lazy" referrerpolicy="no-referrer"></p>
```

An image from a host that isn't on the list becomes a link to the image, so a page doesn't load pictures from a server you didn't authorize.

## `baseUrl`

Default: `''`

The address to join relative links and images to, such as `'https://example.com/docs'`. Both `page` and `/page` end up inside it, and `..` can't go above it.

```php
$converter = new MarkdownConverter(baseUrl: 'https://example.com/docs');

$converter->toHtml('[Page](page) [Up](../../x)');
// Both links point inside https://example.com/docs
```

With no base address, a relative link shows its text only.

A link to an anchor, such as `#top`, always shows its text only, because the converter doesn't write heading ids.

## `linkSchemes`

Default: `['http', 'https', 'mailto']`

The schemes that a link can use. Add `'ftp'` or `'tel'` if you need them.

Schemes that can run script or read local files, such as `javascript`, `data`, and `file`, can't be added. The converter throws an `InvalidArgumentException` if you try.

A link that uses a scheme that isn't allowed shows its text only.

## `autolinkWww`

Default: `false`

Links bare addresses that start with `www.`, as `http` links.

## `autolinkEmails`

Default: `false`

Links bare mail addresses.
