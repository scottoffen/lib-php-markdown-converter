---
sidebar_position: 4
---

# Comparisons

This page compares Markdown Converter with four other Markdown libraries for PHP, using measurements made on October 9, 2026. It reports what the measurements show, where each library is the better choice, and where the numbers can mislead.

## Libraries and Method

| Library | Version tested | Configuration |
| --- | --- | --- |
| Markdown Converter | current source | Defaults for safety, a release notes setup for speed |
| league/commonmark | 2.8, with the GitHub Flavored Markdown converter | `html_input: strip` and `allow_unsafe_links: false`, as its documentation recommends for untrusted text |
| Parsedown | 1.7.4 (stable) and 1.8.0 (master) | Safe mode |
| cebe/markdown | master, GitHub flavor | None available |
| michelf/php-markdown | 2.0.0, Markdown Extra | `no_markup` |

Parsedown Extra 0.9.0 appears only in the feature comparison.

- Every measurement ran on PHP 8.3.6 on Linux, from the command line with no opcache. Treat the speed numbers as relative. They will differ on your hardware and PHP version.
- Each speed figure is the median of up to 30 runs of one reused converter, measured in its own process.
- The hostile inputs for the speed tests come from this project's own test suite. They favor the library they were written for.

## Safety

Each library converted 25 hostile inputs: script and iframe tags, event handlers, `javascript:`, `vbscript:`, and `data:` links in several disguises, entity-hidden schemes, and autolink breakouts. The output was parsed as HTML and checked for active content. The table counts the inputs that produced it.

| Library | Defaults | Safest documented configuration |
| --- | --- | --- |
| Markdown Converter | 0 of 25 | not needed |
| league/commonmark | 18 of 25 | 0 of 25 |
| Parsedown | 23 of 25 | 0 of 25 |
| cebe/markdown | 23 of 25 | no safe mode exists |
| michelf/php-markdown | 22 of 25 | 9 of 25 |

Markdown Converter is the only library here that is safe without configuration. It is also the only one that blocks remote images by default, which stops tracking pixels. The other libraries can reach a safe result except cebe/markdown, which has no safe mode, and michelf/php-markdown, which still passes `javascript:` links with `no_markup` on.

Passing 25 checks doesn't prove that a converter is safe. This project's own tests use the same kinds of inputs, and the converter has not had a formal security audit. See [Security](../security.md).

## Speed

Markdown Converter was not built to be the fastest. It was built to be fast enough and safe. A difference of a millisecond or two is not meaningful, and neither is a difference of a few microseconds on a small text. Look at the order of magnitude, not at which number is smallest.

Times are in milliseconds unless noted, for the configurations in the first table.

| Input | Markdown Converter | league/commonmark | Parsedown | cebe/markdown | michelf/php-markdown |
| --- | --- | --- | --- | --- | --- |
| Release notes, 100 KB | 13.0 | 52.9 | 14.4 | 10.8 | 15.1 |
| Paragraphs and inline syntax, 60 KB | 8.1 | 25.6 | 6.4 | 5.6 | 6.1 |
| Tables, 60 KB | 11.4 | 69.2 | 17.1 | 8.8 | 22.4 |
| Lists, 60 KB | 19.7 | 87.9 | 20.8 | 15.8 | 27.3 |
| One small comment, reused converter (µs) | 37 | 154 | 29 | 33 | 42 |
| One small comment, new converter each time (µs) | 45 | 381 | 30 | 41 | 47 |

On realistic input, every library except league/commonmark converts a 100 KB page in 11 to 15 milliseconds, which is too close to rank. league/commonmark takes several times longer than the others on the same input. It does more work per document, and it still converts a 100 KB page in about 50 milliseconds.

The hostile inputs are 125 KB each, such as brackets that never close, thousands of unfinished links, 20,000 nested quote markers, and a list of 31,000 items. The slowest result for each library was:

| Library | Slowest hostile input | Highest memory use |
| --- | --- | --- |
| Markdown Converter | 121 ms | 16 MB |
| league/commonmark | 0.6 to 1.3 s | 207 MB |
| Parsedown | 5.1 s | 484 MB |
| cebe/markdown | 1.5 s | 26 MB |
| michelf/php-markdown | ran out of memory at 512 MB | over 512 MB |

The hostile inputs are where the differences are large enough to matter. Markdown Converter limits how much work one paragraph can cause, so no input took it long. The other libraries each have an input that is slow for them, but they are not slow everywhere. For example, league/commonmark handles a long run of backticks in 2 ms, where Markdown Converter takes 71 ms. league/commonmark has options that limit nesting depth and the number of delimiters in a line, and both default to no limit.

## Features

The following table shows 20 features, each probed by converting a small input and checking the output. The Parsedown Extra column uses the separate extra package. The probes cover common Markdown features and syntax from GitHub and GitLab. They aren't exhaustive, so the table shows which library has a given feature, not which library is better.

| Feature | Markdown Converter | league/commonmark | Parsedown | Parsedown Extra | cebe/markdown | michelf/php-markdown |
| --- | --- | --- | --- | --- | --- | --- |
| Tables with alignment | Yes | Yes | Yes | Yes | Yes | Yes |
| Task lists | Yes | Yes | No | No | No | No |
| Strikethrough | Yes | Yes | Yes | Yes | Yes | No |
| Bare address links | Yes | Yes | Yes | Yes | Yes | No |
| Code fence language class | Yes | Yes | Yes | Yes | Yes | Yes |
| GitHub alerts | Yes | No | No | No | No | No |
| Footnotes | No | Yes | No | Yes | No | Yes |
| Emoji names | Yes | No | No | No | No | No |
| Math | Yes | No | No | No | No | No |
| Mermaid diagrams | Yes | No | No | No | No | No |
| Heading ids | No | Yes | No | No | No | No |
| Setext headings | Yes | Yes | Yes | Yes | Yes | Yes |
| Indented code | Yes | Yes | Yes | Yes | Yes | Yes |
| Reference links | Yes | Yes | Yes | Yes | Yes | Yes |
| Hard line breaks | Yes | Yes | Yes | Yes | Yes | Yes |
| Definition lists | No | Yes | No | Yes | No | Yes |
| Abbreviations | No | No | No | Yes | No | Yes |
| Attributes such as `{#id}` | No | Yes | No | Yes | No | Yes |
| GitLab inline diffs | Yes | No | No | No | No | No |
| GitLab `>>>` quotes | Yes | No | No | No | No | No |

For league/commonmark, "No" means that none of its bundled extensions provides the feature. It also has extensions that weren't probed, such as smart punctuation, a table of contents, mentions, embeds, front matter, and external link attributes, and a larger set of extensions on Packagist.

Markdown Converter doesn't support footnotes, definition lists, abbreviations, attributes, or heading ids. Use league/commonmark if you need them.

## CommonMark Conformance

I ran all 655 examples from the CommonMark specification, version 0.31.2. Markdown Converter ran with the settings that come closest to CommonMark. The comparison ignores the `rel`, `target`, `loading`, and `referrerpolicy` attributes that Markdown Converter adds.

| Library | All 655 examples | The 430 examples outside the sections below |
| --- | --- | --- |
| league/commonmark | 655 (100%) | 430 (100%) |
| Markdown Converter | 459 (70.1%) | 406 (94.4%) |
| michelf/php-markdown | 368 (56.2%) | 242 (56.3%) |
| Parsedown | 352 (53.7%) | 246 (57.2%) |
| cebe/markdown | 338 (51.6%) | 227 (52.8%) |

The 430 leaves out six sections that depend on choices Markdown Converter makes on purpose: HTML blocks, raw HTML, links, images, link reference definitions, and autolinks. Markdown Converter doesn't copy raw HTML, drops relative links unless you set `baseUrl`, shows images only from hosts you list, and doesn't read link definitions that span lines.

### Differences by Design

Of the 24 examples that Markdown Converter fails inside the 430, 19 come from deliberate choices:

| Cause | Examples |
| --- | --- |
| Relative links are dropped without `baseUrl`. Eight examples fail because of this, and all eight pass when the address is absolute. | 22, 23, 406, 421, 424, 435, 475, 476 |
| Raw HTML is removed or shown as text. | 21, 31, 346, 477, 478, 479, 645, 646 |
| An address with a backslash in it is refused. | 20 |
| The class on a code fence can only hold letters, numbers, and a few symbols. | 34, 144 |

### Known Differences

The other five examples are differences that aren't fixed:

- With `decodeEntities` on, a character reference in a link address or title isn't decoded, so `&amp;` there is escaped twice (examples 32 and 33).
- With `indentedCode` on, a line that continues a paragraph without a `>` can join an indented code block inside a quote (example 238).
- A link reference definition between two list items doesn't make the list loose (example 319).
- A backtick in a link address isn't percent-encoded. The address is written as it was, inside a quoted attribute, and this has no effect on safety (example 348).

## Developer Experience

| | Markdown Converter | league/commonmark | Parsedown | cebe/markdown | michelf/php-markdown |
| --- | --- | --- | --- | --- | --- |
| Packages to install | 1 | 8 | 1 | 1 | 1 |
| Source size | 4,800 lines, with long comments | 19,700 lines, plus about 12,000 in dependencies | 2,000 lines in one file | 2,200 lines | 3,800 lines |
| Minimum PHP | 8.1 | 7.4 | 5.3 | 5.4 | 7.4 |
| How you configure it | Named arguments, 24 settings | Array of options, validated | Method calls | Properties | Properties |
| How you extend it | Settings only | Extensions, renderers, and events | Subclassing | Subclassing | Subclassing |
| Latest activity | October 2026 | October 2026 | February 2026, stable 1.7.4 with betas since | Last release 2018, last commit 2020 | June 2025 |

- Markdown Converter has no extension point. If you need custom syntax, league/commonmark has the most complete extension system of these libraries.
- Parsedown's stable release is 1.7.4. The 1.8 and 2.0 releases are still betas.

## Choosing a Library

Use Markdown Converter when you show Markdown that other people wrote on a web page, and you want safe results without configuration, GitHub-style extras, no dependencies, and bounded work on hostile input.

If you need the full CommonMark specification, footnotes, heading anchors, or custom syntax, consider using league/commonmark configure for untrusted text as its documentation describes.
