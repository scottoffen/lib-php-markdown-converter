<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Support;

use PHPUnit\Framework\TestCase;
use ScottOffen\MarkdownConverter\MarkdownConverter;

/**
 * Provides the helpers that the tests share: convert some Markdown, and check
 * the result.
 */
abstract class MarkdownTestCase extends TestCase
{
    /**
     * The hosts GitHub serves images from. Images are off unless a test turns
     * them on.
     */
    protected const GITHUB_IMAGES = ['github.com', '*.githubusercontent.com'];

    /**
     * Converts Markdown with the given settings and returns the HTML.
     *
     * @param array<string, mixed> $options Arguments for the
     *     `MarkdownConverter` constructor, by name.
     */
    protected function html(string $markdown, array $options = []): string
    {
        return (new MarkdownConverter(...$options))->toHtml($markdown);
    }

    /**
     * Asserts that Markdown converts to the expected HTML.
     *
     * @param array<string, mixed> $options
     */
    protected function assertConverts(string $expected, string $markdown, array $options = [], string $message = ''): void
    {
        $this->assertSame($expected, $this->html($markdown, $options), $message !== '' ? $message : 'Converting ' . json_encode($markdown));
    }

    /**
     * Converts every input and compares it with the expected output.
     *
     * @param array<string, array{0: string, 1: string}> $cases The cases by
     *     name, each as `[markdown, expected html]`.
     * @param array<string, mixed> $options The arguments for the
     *     `MarkdownConverter` constructor, by name.
     */
    protected function assertAllConvert(array $cases, array $options = []): void
    {
        foreach ($cases as $name => [$markdown, $expected]) {
            $this->assertConverts($expected, $markdown, $options, $name . ': ' . json_encode($markdown));
        }
    }

    /**
     * The markup for a link with the default options.
     */
    protected function a(string $href, string $text, ?string $title = null): string
    {
        return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
            . ($title !== null ? ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"' : '')
            . ' rel="noopener noreferrer nofollow" target="_blank">' . $text . '</a>';
    }

    /**
     * The markup for an image with the default options.
     */
    protected function img(string $src, string $alt, ?string $title = null): string
    {
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"'
            . ($title !== null ? ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"' : '')
            . ' loading="lazy" referrerpolicy="no-referrer">';
    }

    /**
     * Checks that the output contains only allowed tags, attributes, and
     * addresses.
     *
     * @param list<string> $schemes The schemes that a link can use.
     */
    protected function assertSafe(string $html, string $message = '', array $schemes = ['http', 'https', 'mailto']): void
    {
        $this->assertSame([], HtmlChecker::violations($html, $schemes), $message !== '' ? $message : 'Unsafe output: ' . $html);
    }
}
