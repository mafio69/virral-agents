<?php

declare(strict_types=1);

namespace Tests;

use App\Http\ClientIpResolver;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ClientIpResolverTest extends TestCase
{
    public function testWithoutTrustedProxiesForwardedHeaderIsIgnored(): void
    {
        $resolver = new ClientIpResolver();

        self::assertSame('172.18.0.1', $resolver->resolve($this->request('172.18.0.1', '203.0.113.7')));
    }

    public function testUntrustedPeerCannotSpoofItsAddress(): void
    {
        $resolver = new ClientIpResolver(['172.16.0.0/12']);

        self::assertSame('198.51.100.9', $resolver->resolve($this->request('198.51.100.9', '203.0.113.7')));
    }

    public function testTrustedProxyYieldsTheRightmostUntrustedAddress(): void
    {
        $resolver = new ClientIpResolver(['172.16.0.0/12', '10.0.0.5']);

        // The client prepended a fake address; the proxies appended the real one.
        $request = $this->request('172.18.0.1', '192.0.2.66, 203.0.113.7, 10.0.0.5');

        self::assertSame('203.0.113.7', $resolver->resolve($request));
    }

    public function testDifferentClientsBehindOneProxyGetDifferentAddresses(): void
    {
        $resolver = new ClientIpResolver(['172.18.0.1']);

        self::assertSame('203.0.113.7', $resolver->resolve($this->request('172.18.0.1', '203.0.113.7')));
        self::assertSame('203.0.113.8', $resolver->resolve($this->request('172.18.0.1', '203.0.113.8')));
    }

    public function testMissingOrInvalidForwardedHeaderFallsBackToPeer(): void
    {
        $resolver = new ClientIpResolver(['172.18.0.1']);

        self::assertSame('172.18.0.1', $resolver->resolve($this->request('172.18.0.1', null)));
        self::assertSame('172.18.0.1', $resolver->resolve($this->request('172.18.0.1', 'not-an-ip, 203.0.113.7x')));
        self::assertSame('172.18.0.1', $resolver->resolve($this->request('172.18.0.1', '172.18.0.1')));
    }

    public function testIpv6RangesAndMalformedRangesAreHandled(): void
    {
        $resolver = new ClientIpResolver(['fd00::/8', '10.0.0.0/99', 'garbage']);

        self::assertSame('2001:db8::1', $resolver->resolve($this->request('fd12::1', '2001:db8::1')));
        self::assertSame('10.0.0.1', $resolver->resolve($this->request('10.0.0.1', '203.0.113.7')));
    }

    public function testPartialPrefixBoundary(): void
    {
        $resolver = new ClientIpResolver(['192.168.4.0/22']);

        self::assertSame('203.0.113.7', $resolver->resolve($this->request('192.168.7.255', '203.0.113.7')));
        self::assertSame('192.168.8.1', $resolver->resolve($this->request('192.168.8.1', '203.0.113.7')));
    }

    private function request(string $peer, ?string $forwardedFor): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'http://fixture.example/api/auth/login', ['REMOTE_ADDR' => $peer]);

        return $forwardedFor === null ? $request : $request->withHeader('X-Forwarded-For', $forwardedFor);
    }
}
