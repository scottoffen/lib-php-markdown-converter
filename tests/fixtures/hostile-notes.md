## Notes

<script>alert('xss')</script>

<img src=x onerror=alert(1)>

[Click me](javascript:alert(1)) or [this](JaVaScRiPt:alert(2)) or [that](data:text/html;base64,AAAA)

![tracking pixel](https://tracker.example/pixel.gif)

![fine](https://user-images.githubusercontent.com/1/a.png "A title")

[link](https://example.com "title\" onmouseover=\"alert(1)")

| a | b |
| - | - |
| <b>bold</b> | `<i>code</i>` |

> [!NOTE]
> <details open ontoggle=alert(1)>

- [ ] a task
- &lt;escaped&gt; entities stay as typed

Line one
Line two  
Line three\
Line four

<!-- hidden comment -->

Done.
