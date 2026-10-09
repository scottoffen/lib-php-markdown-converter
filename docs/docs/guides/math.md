---
sidebar_position: 4
---

# Render Math

The converter doesn't render math. With the `math` setting on, it finds the TeX in the Markdown, escapes it, and writes it in a form that a math library on your page can find. This page shows how to set up KaTeX and MathJax for each output format.

## Turn On Math

Set `math` to `true` when you create the converter:

```php
$converter = new MarkdownConverter(math: true);

$html = $converter->toHtml($markdown);
```

Then choose how the converter writes the math with the `mathFormat` setting. The default, `'pandoc'`, works with both libraries without extra configuration. See [Math](../settings/math.md) for the syntax and the three formats.

## Use the Pandoc Format With KaTeX

1. Load KaTeX and its auto-render extension on the page, as described in the [KaTeX auto-render documentation](https://katex.org/docs/autorender).
2. Put the converter's output in an element, and call `renderMathInElement()` on that element:

   ```html
   <div id="notes"><?= $html ?></div>

   <script>
     renderMathInElement(document.getElementById('notes'));
   </script>
   ```

KaTeX's auto-render extension finds `\(...\)` and `\[...\]` by default, so you don't need to set any delimiters.

## Use the Pandoc Format With MathJax

MathJax 3 reads `\(...\)` and `\[...\]` by default, so you only need to load it. MathJax typesets the page when it loads. If your script adds the converter's output after that, call `MathJax.typesetPromise()` with the element that holds the output:

```js
MathJax.typesetPromise([document.getElementById('notes')]);
```

See the [MathJax documentation](https://docs.mathjax.org) for how to load the library.

## Render Each Element Yourself

With `mathFormat` set to `'class'`, the converter writes bare TeX inside an element with the class `math-inline` or `math-display`. Render each one with KaTeX:

```js
document.querySelectorAll('#notes .math-inline, #notes .math-display').forEach((element) => {
  katex.render(element.textContent, element, {
    displayMode: element.classList.contains('math-display'),
    throwOnError: false,
  });
});
```

This keeps rendering inside the converter's output, and nothing else on the page changes.

## Find Delimiters in Text

With `mathFormat` set to `'delimiters'`, the converter writes the TeX with its own `$` and `$$` delimiters. KaTeX and MathJax don't look for a single `$` by default, so tell the library to:

```js
renderMathInElement(document.getElementById('notes'), {
  delimiters: [
    { left: '$$', right: '$$', display: true },
    { left: '$', right: '$', display: false },
  ],
});
```

## Keep the Page Safe

The converter escapes all TeX, so it can't add markup of its own. The math library then runs in the browser, so its settings matter too:

- Leave KaTeX's `trust` option at its default, `false`. With it on, TeX can use commands such as `\href` and `\includegraphics`.
- KaTeX and MathJax can write inline styles. If you use a Content Security Policy that blocks inline styles, check that your math still renders.

See [Security](../security.md) for the rest of the safety model.
