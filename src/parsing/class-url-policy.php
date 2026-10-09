<?php

declare(strict_types=1);

namespace ScottOffen\MarkdownConverter\Parsing;

use InvalidArgumentException;
use ScottOffen\MarkdownConverter\Options;

/**
 * Decides which addresses can appear in a link or an image.
 *
 * The policy is an allowlist: it refuses an address unless it positively
 * recognizes it. That blocks bypasses such as a tab or newline inside
 * `javascript:`. The policy also refuses any address with whitespace, a control
 * character, an invisible character, or a backslash, because browsers ignore or
 * rewrite those characters.
 *
 * The policy accepts a relative address only when a base address is set. It
 * joins the address to the base, and the address can't go above it.
 *
 * @internal
 */
final class UrlPolicy
{
    private const MAX_LENGTH = 2048;

    private readonly string $base;

    /**
     * @var list<string> The schemes that a link can use, in lowercase.
     */
    private readonly array $schemes;

    /**
     * Creates a policy for the given image hosts, base address, and link
     * schemes.
     *
     * @param list<string> $imageHosts The host names that images can come from.
     *     `"*"` allows every host. `"*.example.com"` allows subdomains of
     *     `example.com` but not `example.com` itself.
     * @param string $baseUrl The address that relative links are joined to, or
     *     an empty string for none.
     * @param list<string> $schemes The schemes that a link can use. The
     *     constructor lowercases them.
     *
     * @throws InvalidArgumentException If a scheme is one of
     *     `Options::FORBIDDEN_SCHEMES`. `Options` refuses these too, with a
     *     clearer message, but `UrlPolicy` decides what a link can be, so it
     *     doesn't rely on that.
     */
    public function __construct(
        private readonly array $imageHosts,
        string $baseUrl = '',
        array $schemes = Options::DEFAULT_LINK_SCHEMES,
    ) {
        $this->base = rtrim($baseUrl, '/');
        $this->schemes = array_map(strtolower(...), $schemes);

        foreach ($this->schemes as $scheme) {
            if (in_array($scheme, Options::FORBIDDEN_SCHEMES, true)) {
                throw new InvalidArgumentException('The scheme "' . $scheme . '" can run script or read local files, so it cannot be allowed.');
            }
        }
    }

    /**
     * Returns the address if it's safe to link to, or null if it isn't. A
     * relative address is returned joined to the base address.
     *
     * @param string $url The address as written.
     *
     * @return string|null The address to use in the link, or null if it can't
     *     be linked.
     */
    public function link(string $url): ?string
    {
        if ($url === '' || strlen($url) > self::MAX_LENGTH) {
            return null;
        }

        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return null;
        }

        if (preg_match('//u', $url) !== 1 || preg_match('/[\p{C}\p{Z}]/u', $url) === 1) {
            return null;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $m) === 1) {
            return $this->absolute($url, strtolower($m[1]));
        }

        $joined = $this->join($url);

        return $joined === null ? null : $this->absolute($joined, strtolower((string) parse_url($joined, PHP_URL_SCHEME)));
    }

    /**
     * Returns the address if an image can load from it, or null if it can't.
     * Only https addresses on an allowed host qualify.
     *
     * @param string $url The address as written.
     *
     * @return string|null The address to use in the `src` attribute, or null if
     *     the image can't load.
     */
    public function image(string $url): ?string
    {
        if ($this->imageHosts === []) {
            return null;
        }

        $href = $this->link($url);

        if ($href === null || stripos($href, 'https://') !== 0) {
            return null;
        }

        $host = strtolower((string) parse_url($href, PHP_URL_HOST));

        if (preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host) !== 1 || str_contains($host, '..')) {
            return null;
        }

        foreach ($this->imageHosts as $pattern) {
            if (self::hostMatches($pattern, $host)) {
                return $href;
            }
        }

        return null;
    }

    /**
     * Checks an address that has a scheme.
     *
     * @param string $url The address.
     * @param string $scheme The scheme of the address, in lowercase.
     *
     * @return string|null The address, or null if its scheme isn't allowed or
     *     the address is malformed.
     */
    private function absolute(string $url, string $scheme): ?string
    {
        if (!in_array($scheme, $this->schemes, true)) {
            return null;
        }

        if ($scheme === 'mailto') {
            return preg_match('~^mailto:[^<>"\'@]+@[^<>"\'@]+$~i', $url) === 1 ? $url : null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        if ($scheme === 'http' || $scheme === 'https') {
            if (preg_match('~^https?://~i', $url) !== 1 || !isset($parts['host']) || $parts['host'] === '') {
                return null;
            }
        } elseif (preg_match('~^[a-z][a-z0-9+.-]*:[^<>"\']+$~i', $url) !== 1) {
            return null;
        } elseif (str_contains($url, '://') && (!isset($parts['host']) || $parts['host'] === '')) {
            return null;
        }

        // An address with a user name or password can make a link look like it
        // goes somewhere else, as in `https://example.com@evil.test`, so the
        // policy refuses it.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    /**
     * Joins a relative address to the base address. Both `docs/page` and
     * `/docs/page` end up inside the base, and `..` can't go above it.
     *
     * @param string $url The relative address.
     *
     * @return string|null The joined address, or null if there is no base
     *     address or the address starts with `//` or `#`.
     */
    private function join(string $url): ?string
    {
        if ($this->base === '' || str_starts_with($url, '//') || str_starts_with($url, '#')) {
            return null;
        }

        $cut = strcspn($url, '?#');
        $path = substr($url, 0, $cut);
        $rest = substr($url, $cut);

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || preg_match('/^(?:\.|%2e){1}$/i', $segment) === 1) {
                continue;
            }

            if (preg_match('/^(?:\.|%2e){2}$/i', $segment) === 1) {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $joined = $this->base . ($segments === [] ? '' : '/' . implode('/', $segments));

        if ($segments !== [] && str_ends_with($path, '/')) {
            $joined .= '/';
        }

        return $joined . $rest;
    }

    private static function hostMatches(string $pattern, string $host): bool
    {
        $pattern = strtolower(trim($pattern));

        if ($pattern === '*') {
            return true;
        }

        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1);

            return strlen($host) > strlen($suffix) && str_ends_with($host, $suffix);
        }

        return $host === $pattern;
    }
}
