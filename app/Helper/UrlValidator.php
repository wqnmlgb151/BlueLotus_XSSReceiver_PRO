<?php
namespace App\Helper;

final class UrlValidator
{
    /**
     * keepsession 跳转目标白名单校验。
     * 仅允许 http/https 协议 + 同源或明确允许的主机。
     * 不通过则返回 false —— 默认拒绝。
     */
    public static function isAllowed(string $url, string $serverHost, array $allowedHosts): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower($parts['host']);
        if ($host === strtolower($serverHost)) {
            return true;
        }
        foreach ($allowedHosts as $h) {
            if ($host === strtolower($h)) {
                return true;
            }
        }
        return false;
    }
}
