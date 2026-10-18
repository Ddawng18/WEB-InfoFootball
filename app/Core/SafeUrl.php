<?php
declare(strict_types=1);

namespace App\Core;

final class SafeUrl
{
    public static function https(string $url): ?string
    {
        return self::validateHttps($url, true);
    }

    /** HTTPS hợp lệ nhưng bỏ qua danh sách link_hosts (dùng cho URL nguồn cào). */
    public static function httpsAny(string $url): ?string
    {
        return self::validateHttps($url, false);
    }

    private static function validateHttps(string $url, bool $checkAllowedHosts): ?string
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('//u', $url) !== 1
            || preg_match('/[\s\x00-\x1F\x7F]/u', $url) === 1
            || !str_starts_with($url, 'https://')) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if (strlen($host) > 1 && $host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }
        if (!self::validHost($host)) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        if (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) {
            return null;
        }

        if ($checkAllowedHosts) {
            $allowedHosts = array_values(array_filter(array_map(
                static fn (string $item): string => strtolower(rtrim(trim($item), '.')),
                explode(',', Config::string('san_pham', 'link_hosts', ''))
            )));
            if ($allowedHosts !== [] && !in_array($host, $allowedHosts, true)) {
                return null;
            }
        }

        return $url;
    }

    public static function anh(string $src): string
    {
        $safeUrl = self::https($src);
        if ($safeUrl !== null) {
            return $safeUrl;
        }
        if (preg_match('/^img\/[A-Za-z0-9._\/-]+$/', $src) === 1 && strpos($src, '..') === false) {
            return asset($src);
        }
        return asset('img/products/no-image.svg');
    }

    private static function validHost(string $host): bool
    {
        if (preg_match('/^[0-9.]+$/', $host) === 1) {
            $parts = explode('.', $host);
            if (count($parts) !== 4) {
                return false;
            }
            foreach ($parts as $part) {
                if ($part === '' || strlen($part) > 3 || (int) $part > 255) {
                    return false;
                }
            }
            return true;
        }

        if (strpos($host, ':') !== false) {
            if (preg_match('/^[0-9a-f:.]+$/i', $host) !== 1
                || strpos($host, ':::') !== false || substr_count($host, '::') > 1 || strpos($host, '.') !== false) {
                return false;
            }
            $groups = array_values(array_filter(explode(':', $host), static fn (string $part): bool => $part !== ''));
            foreach ($groups as $group) {
                if (strlen($group) > 4) {
                    return false;
                }
            }
            return strpos($host, '::') !== false ? count($groups) < 8 : count($groups) === 8;
        }

        return strlen($host) <= 253
            && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/i', $host) === 1;
    }
}
