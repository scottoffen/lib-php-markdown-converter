<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests;

use ScottOffen\MarkdownConverter\Tests\Support\MarkdownTestCase;

/**
 * Converts real-looking release notes and compares the HTML with the expected
 * output.
 *
 * The test converts each `tests/fixtures/<name>.md` with the default settings
 * plus the hosts GitHub serves images from, and compares the result with
 * `tests/fixtures/<name>.html`. The expected files record the output, so a
 * change to the converter that alters it fails the test. It doesn't prove that
 * the output is right, so read an expected file before you update it.
 */
final class GoldenTest extends MarkdownTestCase
{
    public function testEveryFixtureConvertsToItsExpectedHtml(): void
    {
        $inputs = glob(__DIR__ . '/fixtures/*.md');

        $this->assertIsArray($inputs);
        $this->assertGreaterThanOrEqual(4, count($inputs), 'The fixtures are missing.');

        foreach ($inputs as $input) {
            $expectedFile = substr($input, 0, -3) . '.html';

            $this->assertFileExists($expectedFile, basename($input) . ' has no expected output.');

            $actual = $this->html((string) file_get_contents($input), ['imageHosts' => self::GITHUB_IMAGES]);

            $this->assertSame(
                rtrim((string) file_get_contents($expectedFile), "\n"),
                $actual,
                basename($input) . ' no longer converts to ' . basename($expectedFile),
            );
            $this->assertSafe($actual, basename($input));
        }
    }

    public function testTheFixturesDoNotDependOnLineEndings(): void
    {
        foreach (glob(__DIR__ . '/fixtures/*.md') ?: [] as $input) {
            $text = (string) file_get_contents($input);

            $this->assertSame(
                $this->html($text, ['imageHosts' => self::GITHUB_IMAGES]),
                $this->html(str_replace("\n", "\r\n", $text), ['imageHosts' => self::GITHUB_IMAGES]),
                basename($input) . ' differs when it uses Windows line endings.',
            );
        }
    }

    public function testTheHostileFixtureProducesNoActiveContent(): void
    {
        $html = $this->html((string) file_get_contents(__DIR__ . '/fixtures/hostile-notes.md'), ['imageHosts' => self::GITHUB_IMAGES]);

        $this->assertDoesNotMatchRegularExpression('/<script|<img src="x"|href="javascript|href="data:/i', $html);
        $this->assertStringNotContainsString('tracker.example/pixel.gif" alt', $html, 'The tracking image is not loaded.');
        $this->assertStringContainsString('<img src="https://user-images.githubusercontent.com/1/a.png"', $html);
    }
}
