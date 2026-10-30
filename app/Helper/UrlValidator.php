<?php
namespace App\Helper;

final class UrlValidator
{
    /**
     * keepsession 跳转目标白名单校验。
     * 仅允许 http/https 协议 + 同源或明确允许的主机。
     * 不通过则返回 false —— 默认拒绝。
     */
    public static function isAllowed(string $url, string $serverHost, array $allowedHosts, string $serverScheme = 'http'): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\r\n]/', $url)) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower($parts['host']);
        $server = parse_url($serverScheme . '://' . $serverHost);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($server && $host === strtolower($server['host'] ?? '') && $scheme === $serverScheme
            && $port === ($server['port'] ?? ($serverScheme === 'https' ? 443 : 80))) {
            return true;
        }
        foreach ($allowedHosts as $h) {
            $allowed = is_string($h) ? parse_url('http://' . $h) : false;
            if ($allowed && !isset($allowed['user']) && !isset($allowed['pass']) && !isset($allowed['path']) && !isset($allowed['query']) && !isset($allowed['fragment'])
                && $host === strtolower($allowed['host'] ?? '')
                && $port === ($allowed['port'] ?? ($scheme === 'https' ? 443 : 80))) {
                return true;
            }
        }
        return false;
    }

    /** Resolve redirect references while preserving base authority and directory. */
    public static function resolve(string $base, string $reference): ?string
    {
        if (preg_match('/[\r\n]/', $reference)) { return null; }
        $baseParts = parse_url($base); $parts = parse_url($reference);
        if (!$baseParts || $parts === false || !isset($baseParts['scheme'], $baseParts['host'])) { return null; }
        if (isset($parts['scheme'])) { return $reference; }
        if (substr($reference, 0, 2) === '//') { return $baseParts['scheme'] . ':' . $reference; }
        $authority = $baseParts['scheme'] . '://' . $baseParts['host'] . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '');
        $path = $parts['path'] ?? '';
        if ($path === '') {
            $path = $baseParts['path'] ?? '/';
        } elseif ($path[0] !== '/') {
            $basePath = $baseParts['path'] ?? '/';
            $path = substr($basePath, 0, strrpos($basePath, '/') + 1) . $path;
        }
        $trailingDirectory = (bool) preg_match('~/(?:\.|\.\.)$~', $path);
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') { array_pop($segments); }
            elseif ($segment !== '.') { $segments[] = $segment; }
        }
        $query = $parts['query'] ?? (!isset($parts['path']) ? ($baseParts['query'] ?? null) : null);
        $path = '/' . ltrim(implode('/', $segments), '/');
        if ($trailingDirectory && substr($path, -1) !== '/') { $path .= '/'; }
        return $authority . $path . ($query !== null ? '?' . $query : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
