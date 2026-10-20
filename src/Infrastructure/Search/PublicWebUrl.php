<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Exception\ValidationException;

final class PublicWebUrl
{
    public static function normalize(string $url): string
    {
        $parts = parse_url($url);
        if (strlen($url) > 4096 || !filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20<>\\\\]/', $url) || (isset($parts['port']) && !in_array($parts['port'], [80, 443], true))) {
            throw new ValidationException('Dozwolony jest wyłącznie publiczny adres HTTP/HTTPS bez danych logowania.');
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || str_contains($host, ':') || !str_contains($host, '.')
            || preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid|lan|home|onion)$/i', $host)) {
            throw new ValidationException('Adresy lokalne i adresy IP nie są źródłami WWW.');
        }
        return $parts['scheme'].'://'.$host.(isset($parts['port']) && $parts['port'] !== ($parts['scheme'] === 'https' ? 443 : 80) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public static function publicIp(string $ip): bool
    {
        // Use IPv4 only; reject non-global, multicast, carrier NAT and special-use ranges.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $number = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['192.0.2.0', 24], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$network, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($number & $mask) === (ip2long($network) & $mask)) {
                return false;
            }
        }
        return true;
    }
}
