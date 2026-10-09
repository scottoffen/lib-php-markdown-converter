<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ScottOffen\MarkdownConverter\MarkdownConverter;
use ScottOffen\MarkdownConverter\Options;
use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

final class MarkdownConverterTest extends MarkdownTestCase
{
    public function testTheDefaultsMatchWhatGitHubDoes(): void
    {
        $this->assertConverts("<h1>a</h1>\n<p>b<br>c</p>", "# a\nb\nc");
    }

    /**
     * Tests that `MarkdownConverter` and `Options` list the same settings.
     *
     * `MarkdownConverter` lists every setting so editors can show them, and
     * `Options` is where the settings are kept and checked. The two lists must
     * say the same thing, or a setting could be offered and then ignored
     * without notice.
     */
    public function testMarkdownAndOptionsListTheSameSettings(): void
    {
        $converterParameters = (new ReflectionMethod(MarkdownConverter::class, '__construct'))->getParameters();
        $options = (new ReflectionMethod(Options::class, '__construct'))->getParameters();

        $this->assertSame(
            array_map(static fn ($parameter): string => $parameter->getName(), $options),
            array_map(static fn ($parameter): string => $parameter->getName(), $converterParameters),
            'The settings, or their order, differ between Options and MarkdownConverter.',
        );

        foreach ($options as $index => $parameter) {
            $name = $parameter->getName();
            $other = $converterParameters[$index];
            $type = $parameter->getType();
            $otherType = $other->getType();

            $this->assertTrue($type instanceof ReflectionNamedType && $otherType instanceof ReflectionNamedType, $name . ' needs a plain type.');
            $this->assertSame($type->getName(), $otherType->getName(), 'The type of ' . $name . ' differs.');
            $this->assertTrue($parameter->isDefaultValueAvailable() && $other->isDefaultValueAvailable(), $name . ' needs a default in both places.');
            $this->assertSame($parameter->getDefaultValue(), $other->getDefaultValue(), 'The default of ' . $name . ' differs.');
        }
    }

    public function testEverySettingReachesTheOptions(): void
    {
        $changed = [
            'headingOffset' => 3,
            'softBreak' => 'space',
            'imageHosts' => ['images.test'],
            'inlineStyles' => true,
            'linkTarget' => '_self',
            'maxLength' => 1000,
            'maxDepth' => 3,
            'html' => 'strip',
            'decodeEntities' => true,
            'baseUrl' => 'https://x.test/docs',
            'linkSchemes' => ['https'],
            'autolinkWww' => true,
            'autolinkEmails' => true,
            'indentedCode' => true,
            'setextHeadings' => true,
            'referenceLinks' => true,
            'taskLists' => true,
            'emoji' => true,
            'customEmoji' => ['party' => 'yay'],
            'inlineDiffs' => true,
            'fencedQuotes' => true,
            'math' => true,
            'mathFormat' => 'class',
            'mathMaxLength' => 2000,
            'mermaid' => true,
        ];

        foreach ((new ReflectionMethod(Options::class, '__construct'))->getParameters() as $parameter) {
            $name = $parameter->getName();

            $this->assertTrue(isset($changed[$name]), 'Add a changed value for the new setting ' . $name . ' to this test.');
            $this->assertTrue($changed[$name] !== $parameter->getDefaultValue(), 'The value for ' . $name . ' is the default, so it proves nothing.');
        }

        $stored = (new ReflectionProperty(MarkdownConverter::class, 'options'))->getValue(new MarkdownConverter(...$changed));

        foreach ($changed as $name => $value) {
            $this->assertSame($value, $stored->{$name}, $name . ' did not reach the options.');
        }
    }

    public function testEveryOptionIsChecked(): void
    {
        $invalid = [
            'a negative heading offset' => ['headingOffset' => -1],
            'a heading offset that is too large' => ['headingOffset' => 6],
            'an unknown soft break setting' => ['softBreak' => 'wrap'],
            'an empty image host' => ['imageHosts' => ['github.com', ' ']],
            'an unknown link target' => ['linkTarget' => '_top'],
            'a zero length limit' => ['maxLength' => 0],
            'no depth' => ['maxDepth' => 0],
            'too much depth' => ['maxDepth' => 21],
        ];

        foreach ($invalid as $name => $options) {
            try {
                new MarkdownConverter(...$options);
                $this->fail('No exception for ' . $name);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testHeadingsCanBePushedDown(): void
    {
        $this->assertConverts("<h4>a</h4>\n<h5>b</h5>\n<h6>c</h6>\n<h6>d</h6>", "# a\n## b\n### c\n#### d", ['headingOffset' => 3]);
        $this->assertConverts("<h5>a</h5>\n<h6>b</h6>", "# a\n## b", ['headingOffset' => 4]);
        $this->assertConverts('<h6>a</h6>', '###### a', ['headingOffset' => 5]);
    }

    public function testTheLinkTarget(): void
    {
        $this->assertConverts('<p><a href="https://x.test" rel="noopener noreferrer nofollow" target="_self">a</a></p>', '[a](https://x.test)', ['linkTarget' => '_self']);
        $this->assertConverts('<p><a href="https://x.test" rel="noopener noreferrer nofollow">a</a></p>', '[a](https://x.test)', ['linkTarget' => '']);
    }

    public function testInlineStylesAreOffByDefaultAndOnWhenAsked(): void
    {
        $markdown = "> q\n\n```\nc\n```\n\n![a](https://github.com/a.png)";

        $this->assertStringNotContainsString('style=', $this->html($markdown));

        $styled = $this->html($markdown, ['inlineStyles' => true, 'imageHosts' => self::GITHUB_IMAGES]);

        $this->assertStringContainsString('<blockquote style="border-left:4px solid #c3c4c7;margin:0 0 16px;padding:0 1em;color:#50575e">', $styled);
        $this->assertStringContainsString('<pre style="overflow:auto;padding:8px 12px;background:#f6f7f7;border:1px solid #dcdcde">', $styled);
        $this->assertStringContainsString('style="max-width:100%;height:auto"', $styled);
        $this->assertSafe($styled);
    }

    public function testLineEndingsAreNormalized(): void
    {
        $this->assertConverts("<p>a</p>\n<p>b</p>", "a\r\n\r\nb");
        $this->assertConverts("<p>a</p>\n<p>b</p>", "a\r\rb");
        $this->assertConverts("<ul>\n<li>a</li>\n<li>b</li>\n</ul>", "- a\r\n- b\r\n");
    }

    public function testAByteOrderMarkIsDropped(): void
    {
        $this->assertConverts('<h1>a</h1>', "\xEF\xBB\xBF# a");
    }

    public function testControlCharactersAreRemoved(): void
    {
        $this->assertConverts('<p>ab</p>', "a\x00\x01\x08\x0B\x0C\x1F\x7Fb");

    }

    public function testInvalidUtf8IsReplacedNotRejected(): void
    {
        $html = $this->html("a \xFF\xFE b");

        $this->assertStringStartsWith('<p>a ', $html);
        $this->assertStringEndsWith(' b</p>', $html);
        $this->assertTrue(mb_check_encoding($html, 'UTF-8'), 'The output must be valid UTF-8.');
    }

    public function testEachInvalidByteBecomesTheReplacementCharacter(): void
    {
        $this->assertAllConvert([
            'a bad byte' => ["a \xFF b", "<p>a \u{FFFD} b</p>"],
            'a bad byte after a good start' => ["ab\xC3\x28cd", "<p>ab\u{FFFD}(cd</p>"],
            'an overlong encoding' => ["a\xC0\xAFb", "<p>a\u{FFFD}\u{FFFD}b</p>"],
            'valid text is left alone' => ["caf\u{E9} \u{65E5}\u{672C}", "<p>caf\u{E9} \u{65E5}\u{672C}</p>"],
        ]);
    }

    public function testTabsInIndentationCountAsFourSpaces(): void
    {
        $this->assertConverts("<ul>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ul>", "- a\n\t- b");
        $this->assertConverts("<ul>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ul>", "- a\n  \t- b");
    }

    public function testTooMuchInputIsCutOffWithANote(): void
    {
        $html = $this->html(str_repeat("line\n\n", 100), ['maxLength' => 60]);

        $this->assertLessThanOrEqual(10, substr_count($html, '<p>line</p>'));
        $this->assertStringEndsWith('<p><em>The rest of this text was left out because it is too long.</em></p>', $html);
        $this->assertSafe($html);
    }

    public function testInputExactlyAtTheLimitIsNotCutOff(): void
    {
        $html = $this->html('abcde', ['maxLength' => 5]);

        $this->assertSame('<p>abcde</p>', $html);
    }

    public function testACutOffNeverSplitsACharacter(): void
    {
        $note = '<p><em>The rest of this text was left out because it is too long.</em></p>';

        $this->assertAllConvert([
            'two bytes of a character that needs two' => ["\u{E9}\u{E9}\u{E9}", "<p>\u{E9}\u{E9}</p>\n" . $note],
            'one byte short of a character' => ["aaaa\u{E9}", "<p>aaaa</p>\n" . $note],
            'two of the three bytes of a character' => ["aaa\u{65E5}", "<p>aaa</p>\n" . $note],
        ], ['maxLength' => 5]);
    }

    public function testACutOffOnACharacterBoundaryKeepsTheCharacter(): void
    {
        $this->assertConverts("<p>\u{E9}\u{E9}</p>", "\u{E9}\u{E9}", ['maxLength' => 4]);
    }

    /**
     * Tests that what a converter has read doesn't change what it makes of the
     * next text.
     *
     * The texts are the kinds most likely to leave state behind: reference
     * definitions, brackets and tags used up to their limits, and unclosed
     * tags. The test converts each with a new converter, and then with one
     * converter that has already read all the others, in the order written, in
     * the opposite order, and in an order that repeats some of them.
     */
    public function testWhatAConverterReadBeforeDoesNotChangeWhatItMakesOfTheNextText(): void
    {
        $settings = ['html' => 'sanitize', 'referenceLinks' => true, 'inlineDiffs' => true, 'taskLists' => true, 'emoji' => true, 'imageHosts' => ['*']];
        $texts = [
            'a definition' => "[a]: https://x.test/a

See [a].",
            'the label used without its definition' => 'See [a] and [b][a].',
            'another definition for the same label' => "[a]: https://x.test/other

[a]",
            'brackets used up to the limit' => str_repeat('[x](https://x.test) ', 250),
            'a few links, which need the limit to be whole again' => str_repeat('[y](https://y.test) ', 150),
            'tags that are never closed' => str_repeat("<details>
", 800),
            'a tag that is closed' => "<details>
<summary>S</summary>

Body [a]

</details>",
            'tags nested more deeply than the limit' => str_repeat('<b>', 40) . 'x' . str_repeat('</b>', 40),
            'tags nested lightly' => '<b><i>y</i></b> {+ added +}',
            'a comment that is never closed' => 'text <!-- never closed',
            'a task list and emoji' => "- [x] done :tada:
- [ ] todo",
            'an alert with a definition' => "> [!NOTE]
> See [a].

[a]: https://x.test/note",
        ];

        $alone = [];

        foreach ($texts as $name => $text) {
            $alone[$name] = (new MarkdownConverter(...$settings))->toHtml($text);
        }

        $shared = new MarkdownConverter(...$settings);
        $orders = [
            'in order' => array_keys($texts),
            'in the opposite order' => array_reverse(array_keys($texts)),
            'with some repeated' => ['a definition', 'the label used without its definition', 'brackets used up to the limit', 'a definition', 'tags that are never closed', 'a tag that is closed', 'brackets used up to the limit', 'the label used without its definition'],
        ];

        foreach ($orders as $order => $names) {
            foreach ($names as $name) {
                $this->assertSame($alone[$name], $shared->toHtml($texts[$name]), $name . ' ' . $order);
            }
        }
    }

    public function testADefinitionInOneTextIsNotAvailableToTheNext(): void
    {
        $converter = new MarkdownConverter(referenceLinks: true);

        $this->assertStringContainsString('<a href="https://x.test"', $converter->toHtml("[a]

[a]: https://x.test"));
        $this->assertSame('<p>[a]</p>', $converter->toHtml('[a]'));
    }

    public function testOneConverterCanBeUsedManyTimes(): void
    {
        $converter = new MarkdownConverter();

        $first = $converter->toHtml("[a](https://x.test)\n\n*b*");
        $second = $converter->toHtml("[a](https://x.test)\n\n*b*");
        $converter->toHtml(str_repeat('[', 1000));

        $this->assertSame($first, $second);
        $this->assertSame($first, $converter->toHtml("[a](https://x.test)\n\n*b*"));
    }
}
