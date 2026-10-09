---
sidebar_position: 5
---

# Security

The converter treats all input as untrusted. This page describes how it keeps its output safe and what you can add around it.

## Audit Status

The converter is designed to keep its output safe, but it has not had a formal security audit.

## Escaping

The converter escapes all text from the input before it writes it to the output. Text between tags has `&`, `<`, `>`, and `"` escaped. Text inside an attribute value has `'` escaped as well.

## HTML in the Input

By default, HTML in the text shows as text. With `html` set to `'strip'` or `'sanitize'`, the converter reads the HTML and writes what it keeps from a short list. It never copies a tag from the input.

## Tags in the Output

The output uses a fixed list of tags, with a short list of attributes on each. Turning on `html: 'sanitize'`, `inlineDiffs`, `math`, or `mermaid` adds a few more. See [Output Format](reference/output-format.md) for the full list.

## Link Addresses

A link address must match an allowlist. Links can use `http`, `https`, and `mailto`, and any other scheme you add with `linkSchemes`, except the ones that can run script or read local files.

The converter refuses any address that has one of the following in it:

- Whitespace
- A control or invisible character
- A backslash
- A user name

These checks block bypasses such as a tab inside `javascript:`. The converter reads the character references in an address in an HTML attribute first, so `jav&#x09;ascript:` is caught too.

The converter joins a relative link to `baseUrl`, and the link can't go above it.

## Images

An image must use `https` and come from a host in `imageHosts`. The list is empty by default, so no image loads until you allow a host. Every other image becomes a link.

## Math

With the `math` setting on, the converter escapes the TeX like any other text, so it can't add a tag or an attribute. The converter doesn't render math. A library on your page, such as KaTeX or MathJax, does that in the browser, so its settings are part of your page's safety. Leave KaTeX's `trust` option off, and see [Render Math](guides/math.md).

## Diagrams

With the `mermaid` setting on, the converter escapes the diagram source like any other text, so it can't add a tag or an attribute. The converter doesn't draw diagrams. The Mermaid library does that in the browser, and it reads source that a stranger wrote. Keep its `securityLevel` at `'strict'` or `'sandbox'`, keep the library up to date, and see [Render Diagrams](guides/diagrams.md).

## Limits

The converter caps input size, nesting depth, the length of one piece of math, and the number of emphasis marks, links, tags, and places that could start math that it reads in one block. You can change the size and depth limits with `maxLength` and `maxDepth`. See [Limitations](reference/limitations.md) for the other limits.

## Input Cleaning

The converter removes control characters and replaces each invalid UTF-8 byte with U+FFFD before it reads the text.

## Add a Content Security Policy

Consider adding a [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CSP) to pages that show the output. A Content Security Policy tells browsers which scripts and other resources a page may load, and it adds a second layer of protection if something goes wrong.

The `inlineStyles` setting writes `style` attributes. A policy that blocks inline styles blocks those too, so leave `inlineStyles` off and style the output with a stylesheet if you use such a policy.
