<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Client address for rate limiting. X-Forwarded-For is honoured only when the
 * direct peer is a configured trusted proxy, and is read right to left so a
 * client cannot inject an address in front of the proxy-appended one.
 */
final readonly class ClientIpResolver
{
    /** @param list<string> $trustedProxies exact addresses or CIDR ranges */
    public function __construct(private array $trustedProxies = [])
    {
    }

    public function resolve(ServerRequestInterface $request): string
    {
        $peer = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        if (!is_string($peer) || !$this->isTrusted($peer)) {
            return is_string($peer) ? $peer : '';
        }
        $forwarded = array_reverse(array_map('trim', explode(',', $request->getHeaderLine('X-Forwarded-For'))));
        foreach ($forwarded as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                return $peer;
            }
            if (!$this->isTrusted($address)) {
                return $address;
            }
        }
        return $peer;
    }

    private function isTrusted(string $address): bool
    {
        foreach ($this->trustedProxies as $proxy) {
            if ($this->matches($address, $proxy)) {
                return true;
            }
        }
        return false;
    }

    private function matches(string $address, string $range): bool
    {
        [$network, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
        $addressBytes = @inet_pton($address);
        $networkBytes = @inet_pton($network);
        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }
        $maxBits = strlen($networkBytes) * 8;
        $prefix = $bits === null ? $maxBits : filter_var($bits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $maxBits]]);
        if ($prefix === false) {
            return false;
        }
        $fullBytes = intdiv($prefix, 8);
        if (strncmp($addressBytes, $networkBytes, $fullBytes) !== 0) {
            return false;
        }
        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($addressBytes[$fullBytes]) & $mask) === (ord($networkBytes[$fullBytes]) & $mask);
    }
}
