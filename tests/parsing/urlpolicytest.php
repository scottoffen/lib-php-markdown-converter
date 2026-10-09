<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Tests\Parsing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ScottOffen\MarkdownConverter\Options;
use ScottOffen\MarkdownConverter\Parsing\UrlPolicy;

final class UrlPolicyTest extends TestCase
{
    private function policy(array $hosts = ['github.com', '*.githubusercontent.com']): UrlPolicy
    {
        return new UrlPolicy($hosts);
    }

    public function testAddressesThatMayBeLinkedTo(): void
    {
        $policy = $this->policy();

        foreach ([
            'https://example.com',
            'http://example.com/a?b=c#d',
            'HTTPS://EXAMPLE.COM/A',
            'https://example.com:8443/a',
            'https://sub.example.com/a_(b)',
            'https://例え.jp/a',
            'mailto:me@example.com',
            'mailto:me@example.com?subject=Hi',
            'MAILTO:me@example.com',
        ] as $url) {
            $this->assertSame($url, $policy->link($url), $url);
        }
    }

    public function testAddressesThatAreRefused(): void
    {
        $policy = $this->policy();

        $refused = [
            'an empty address' => '',
            'javascript' => 'javascript:alert(1)',
            'javascript in mixed case' => 'JaVaScRiPt:alert(1)',
            'javascript with a leading space' => ' javascript:alert(1)',
            'javascript with a tab inside' => "java\tscript:alert(1)",
            'javascript with a newline inside' => "java\nscript:alert(1)",
            'javascript with a form feed inside' => "java\x0Cscript:alert(1)",
            'javascript with a null byte inside' => "java\x00script:alert(1)",
            'javascript with a control character in front' => "\x01javascript:alert(1)",
            'javascript with an entity for the colon' => 'javascript&colon;alert(1)',
            'javascript with an entity for a tab' => 'jav&#x09;ascript:alert(1)',
            'data' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'vbscript' => 'vbscript:msgbox(1)',
            'file' => 'file:///etc/passwd',
            'ftp' => 'ftp://example.com/a',
            'a relative path' => '/docs/page',
            'a relative file' => 'page.html',
            'an anchor' => '#section',
            'a protocol relative address' => '//evil.test/a',
            'a missing host' => 'https:///a',
            'only the scheme' => 'https://',
            'a space in the address' => 'https://example.com/a b',
            'a user name in the address' => 'https://user@example.com/',
            'a user name and password' => 'https://user:pass@example.com/',
            'a user name that looks like a host' => 'https://github.com@evil.test/',
            'a backslash' => 'https://example.com\\@evil.test/',
            'a backslash instead of a slash' => 'https:\\\\example.com',
            'a zero width space' => "https://exa\u{200B}mple.com",
            'a right to left override' => "https://example.com/\u{202E}gpj.exe",
            'a non breaking space' => "https://example.com/a\u{00A0}b",
            'an invalid UTF-8 byte' => "https://example.com/\xFF",
            'a mail address with no at sign' => 'mailto:example.com',
            'a mail address with an angle bracket' => 'mailto:a<b>@example.com',
            'a very long address' => 'https://example.com/' . str_repeat('a', 3000),
        ];

        foreach ($refused as $name => $url) {
            $this->assertNull($policy->link($url), $name);
        }
    }

    public function testTheClassCannotBeExtendedToLoosenItsRules(): void
    {
        $this->assertTrue((new ReflectionClass(UrlPolicy::class))->isFinal());
    }

    public function testSchemesThatRunScriptOrReadFilesAreRefusedByThePolicyItself(): void
    {
        // `Options` refuses these first, but a policy built without `Options`
        // must be equally strict.
        foreach (Options::FORBIDDEN_SCHEMES as $scheme) {
            foreach ([$scheme, strtoupper($scheme), ucfirst($scheme)] as $spelling) {
                try {
                    new UrlPolicy([], '', ['https', $spelling]);
                    $this->fail('The scheme ' . $spelling . ' was accepted.');
                } catch (InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function testOtherSchemesCanBeAddedInAnyCase(): void
    {
        $policy = new UrlPolicy([], '', ['https', 'FTP', 'Tel']);

        $this->assertSame('ftp://x.test/a', $policy->link('ftp://x.test/a'));
        $this->assertSame('tel:+15551234', $policy->link('tel:+15551234'));
        $this->assertNull($policy->link('http://x.test/'), 'A scheme that was not listed is still refused.');
    }

    public function testImagesFromAllowedHosts(): void
    {
        $policy = $this->policy();

        foreach ([
            'https://github.com/a.png',
            'https://GitHub.com/a.png',
            'https://github.com:443/a.png',
            'https://user-images.githubusercontent.com/1/a.png',
            'https://private-user-images.githubusercontent.com/1/a.png?jwt=abc',
            'https://a.b.githubusercontent.com/a.png',
        ] as $url) {
            $this->assertSame($url, $policy->image($url), $url);
        }
    }

    public function testImagesFromOtherHostsAreRefused(): void
    {
        $policy = $this->policy();

        $refused = [
            'another host' => 'https://example.com/a.png',
            'the bare wildcard domain' => 'https://githubusercontent.com/a.png',
            'a host that only ends the same way' => 'https://evilgithubusercontent.com/a.png',
            'an allowed name as a prefix of another host' => 'https://github.com.evil.test/a.png',
            'an allowed name as a subdomain' => 'https://github.com.evil.test/github.com/a.png',
            'a subdomain of an exact host' => 'https://evil.github.com/a.png',
            'plain http' => 'http://github.com/a.png',
            'a user name' => 'https://github.com@evil.test/a.png',
            'an address that is not a link at all' => 'javascript:alert(1)',
            'an ip address' => 'https://192.168.0.1/a.png',
            'a host with a non ASCII letter' => 'https://gıthub.com/a.png',
            'a dot at the start of the host' => 'https://.githubusercontent.com/a.png',
            'two dots in a row' => 'https://a..githubusercontent.com/a.png',
        ];

        foreach ($refused as $name => $url) {
            $this->assertNull($policy->image($url), $name);
        }
    }

    public function testTheHostListCanBeChanged(): void
    {
        $this->assertNotNull($this->policy(['*'])->image('https://anything.test/a.png'));
        $this->assertNull($this->policy(['*'])->image('http://anything.test/a.png'), 'Even then, only https.');
        $this->assertNull($this->policy([])->image('https://github.com/a.png'));
        $this->assertNotNull($this->policy(['cdn.example.com'])->image('https://cdn.example.com/a.png'));
        $this->assertNull($this->policy(['cdn.example.com'])->image('https://example.com/a.png'));
        $this->assertNotNull($this->policy(['  CDN.Example.com  '])->image('https://cdn.example.com/a.png'), 'Case and spaces in the list do not matter.');
    }
}
