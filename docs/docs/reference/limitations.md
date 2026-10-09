---
sidebar_position: 3
---

# Limitations

This page describes behavior that can surprise you. None of it is a bug, but knowing it saves time when the output doesn't match what you expected.

## Tables Need a Blank Line

A table with no blank line before it joins the paragraph above. The header and rows then appear as plain text. Leave a blank line before every table.

## Many Links and Tags in One Block

In one paragraph, heading, or table cell, the converter processes up to 200 `[` or `![` markers, inline diffs, and HTML tags that have a closing tag. Past the 200th, they aren't converted and appear as you wrote them. Link text can be at most 1,000 bytes long.

## Text in English Only

An alert's title, such as "Note" or "Warning", and the note that `maxLength` adds when it cuts off a long text are always in English. The note reads "The rest of this text was left out because it is too long." The converter doesn't translate either one.

## HTML Comments That Never Close

The converter removes HTML comments from the output. If a comment starts at the beginning of a line and has no `-->`, it also removes the rest of the text, as CommonMark does. If a comment starts in the middle of a line and has no `-->`, the converter shows it as text.

## Comments, Code, and Addresses Inside Link Text

The converter reads a comment, a code span, or an address in angle brackets before it decides where link text ends. A `]` inside one of them doesn't end the link text.

For example, in `[a <!-- ](https://example.com) --> b`, the `](https://example.com)` is part of the comment. The converter removes it, makes no link, and shows `[a  b`.

## Web Addresses Inside Square Brackets

If you write a bare web address between square brackets, such as `[see https://example.com/a]`, the link covers only the address. The closing `]` stays outside the link.

## Math Across Lines

This note applies when the `math` setting is on.

A paragraph ends at a line that starts a list, a heading, or another block. In `$a` followed on the next line by `+ b$`, the second line starts a list, so the converter doesn't see the math. Put the math in a `$$` block, where the lines are never read as Markdown.

A `$$` line indented four spaces or more doesn't open a block. If a later `$$` line closes it, the paragraph still shows the text between them as display math.
