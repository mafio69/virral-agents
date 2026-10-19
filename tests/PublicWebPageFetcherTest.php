<?php
declare(strict_types=1);
namespace Tests;
use App\Infrastructure\Search\PublicWebPageFetcher;
use App\Infrastructure\Search\PublicWebUrl;
use App\Infrastructure\Search\SearchException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PublicWebPageFetcherTest extends TestCase
{
    public function testPublicAddressNormalizationAndSpecialRanges(): void
    {
        self::assertSame('https://example.org/page', PublicWebUrl::normalize('https://EXAMPLE.org:443/page#part'));
        foreach (['127.0.0.1', '10.1.2.3', '172.16.1.1', '192.168.1.1', '169.254.169.254', '100.64.1.1', '198.18.0.1', '192.0.2.1', '224.0.0.1', '::1', '0.0.0.0'] as $ip) self::assertFalse(PublicWebUrl::publicIp($ip), $ip);
        self::assertTrue(PublicWebUrl::publicIp('93.184.216.34'));
    }
    public function testOnlyDefaultPortsAndUrlsWithoutCredentialsAreAccepted(): void
    {
        self::assertSame('http://example.org/a', PublicWebUrl::normalize('http://example.org:80/a'));
        self::assertSame('https://example.org/', PublicWebUrl::normalize('https://example.org'));
        self::assertSame('https://example.org:80/', PublicWebUrl::normalize('https://example.org:80/'));
        foreach (['https://example.org:8443/', 'http://example.org:22/', 'https://user:pass@example.org/', 'https://user@example.org/',
            'ftp://example.org/', 'https://localhost/', 'https://127.0.0.1/', 'https://intranet/', 'https://example.org/<script>'] as $url) {
            try {
                PublicWebUrl::normalize($url);
                self::fail('Expected rejected URL: '.$url);
            } catch (\App\Exception\ValidationException) {
                self::assertTrue(true);
            }
        }
    }
    public function testDnsRebindingOrPrivateAddressIsBlockedBeforeHttp(): void
    {
        $fetcher = new PublicWebPageFetcher(new Client(['handler' => HandlerStack::create(new MockHandler([]))]), static fn() => ['93.184.216.34', '127.0.0.1']);
        $this->expectException(SearchException::class);
        $fetcher->fetch('https://example.org/');
    }
    public function testHtmlExtractionIsBoundedAndUsesPinnedTlsIdentityWithoutCredentials(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><head><title>Title</title></head><body><script>untrustedScript()</script><p>Visible fixture</p><p>'.str_repeat('a', 17000).'</p></body></html>')]));
        $handler->push(Middleware::history($history));
        $fetcher = new PublicWebPageFetcher(new Client(['handler' => $handler]), static fn() => ['93.184.216.34'], true);
        $result = $fetcher->fetch('https://example.org/page');
        self::assertTrue($result['truncated']);
        self::assertSame(16000, mb_strlen($result['text']));
        self::assertStringContainsString('Visible fixture', $result['text']);
        self::assertStringNotContainsString('untrustedScript', $result['text']);
        self::assertSame('93.184.216.34', $history[0]['request']->getUri()->getHost());
        self::assertSame('example.org', $history[0]['request']->getHeaderLine('Host'));
        self::assertSame('example.org', $history[0]['options']['stream_context']['ssl']['peer_name']);
        self::assertTrue($history[0]['options']['verify']);
        self::assertFalse($history[0]['options']['allow_redirects']);
        self::assertFalse($history[0]['request']->hasHeader('Authorization'));
        self::assertFalse($history[0]['request']->hasHeader('Cookie'));
    }
    public function testRedirectPdfAndOversizedResponseAreNotReadAsSources(): void
    {
        foreach ([new Response(302, ['Location' => 'http://127.0.0.1/']), new Response(200, ['Content-Type' => 'application/pdf'], 'fixture'),
            new Response(200, ['Content-Type' => 'text/plain', 'Content-Length' => '2000000'], 'large')] as $response) {
            $history = [];
            $handler = HandlerStack::create(new MockHandler([$response]));
            $handler->push(Middleware::history($history));
            try {
                (new PublicWebPageFetcher(new Client(['handler' => $handler]), static fn() => ['93.184.216.34']))->fetch('https://example.org/');
                self::fail('Expected rejected page');
            } catch (SearchException) { self::assertCount(1, $history); }
        }
    }

    public function testRedirectsAreFollowedAcrossHostsWithFreshValidationForEveryHop(): void
    {
        $history = [];
        $resolved = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(301, ['Location' => 'https://example.org/a', 'Set-Cookie' => 'session=fixture']),
            new Response(302, ['Location' => 'https://www.example.net/c']),
            new Response(307, ['Location' => '/d?x=1']),
            new Response(200, ['Content-Type' => 'text/plain'], 'Final fixture text'),
        ]));
        $handler->push(Middleware::history($history));
        $resolve = static function (string $host) use (&$resolved): array {
            $resolved[] = $host;
            return [$host === 'www.example.net' ? '93.184.216.35' : '93.184.216.34'];
        };
        $result = (new PublicWebPageFetcher(new Client(['handler' => $handler]), $resolve, true))->fetch('http://example.org/a');
        self::assertSame('https://www.example.net/d?x=1', $result['url']);
        self::assertSame('Final fixture text', $result['text']);
        self::assertSame(['example.org', 'example.org', 'www.example.net', 'www.example.net'], $resolved);
        self::assertCount(4, $history);
        self::assertSame('http', $history[0]['request']->getUri()->getScheme());
        self::assertSame('https', $history[1]['request']->getUri()->getScheme());
        self::assertSame('93.184.216.35', $history[3]['request']->getUri()->getHost());
        self::assertSame('/d', $history[3]['request']->getUri()->getPath());
        self::assertSame('x=1', $history[3]['request']->getUri()->getQuery());
        self::assertSame('www.example.net', $history[3]['request']->getHeaderLine('Host'));
        self::assertSame('www.example.net', $history[3]['options']['stream_context']['ssl']['peer_name']);
        foreach ($history as $entry) {
            self::assertFalse($entry['options']['allow_redirects']);
            self::assertFalse($entry['request']->hasHeader('Cookie'));
            self::assertFalse($entry['request']->hasHeader('Authorization'));
        }
    }
    public function testUnsafeRedirectsStopBeforeRequestingTheTarget(): void
    {
        $chain = static fn(string ...$locations): array => array_map(static fn(string $location): Response => new Response(302, ['Location' => $location]), $locations);
        $cases = [
            'too_many_redirects' => [$chain('/1', '/2', '/3', '/4'), 4],
            'redirect_loop' => [$chain('/b', '/a'), 2],
            'insecure_redirect' => [$chain('http://example.org/a'), 1],
            'private_address' => [$chain('https://intranet.example.com/'), 1],
            'redirect' => [$chain('http://127.0.0.1/'), 1],
        ];
        foreach ($cases as $expected => [$responses, $requests]) {
            $history = [];
            $handler = HandlerStack::create(new MockHandler($responses));
            $handler->push(Middleware::history($history));
            $resolve = static fn(string $host): array => [$host === 'intranet.example.com' ? '10.0.0.5' : '93.184.216.34'];
            try {
                (new PublicWebPageFetcher(new Client(['handler' => $handler]), $resolve))->fetch('https://example.org/a');
                self::fail('Expected failure: '.$expected);
            } catch (SearchException $error) {
                self::assertSame($expected, $error->reason, $expected);
                self::assertCount($requests, $history, $expected);
            }
        }
    }
    public function testEveryFetchFailureHasClosedReasonWithoutResponseContent(): void
    {
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://example.org/');
        $cases = [
            'redirect' => new Response(301),
            'http_blocked' => new Response(403, ['Content-Type' => 'text/html'], 'Private denial body'),
            'http_not_found' => new Response(404, ['Content-Type' => 'text/html'], 'Missing'),
            'timeout' => new \GuzzleHttp\Exception\ConnectException('Private transport detail', $request, handlerContext: ['errno' => 28]),
            'connection_failed' => new \GuzzleHttp\Exception\ConnectException('Private transport detail', $request),
            'http_error' => new Response(500, ['Content-Type' => 'text/html'], 'Server failure'),
            'unsupported_type' => new Response(200, ['Content-Type' => 'application/pdf'], 'fixture'),
            'unsupported_encoding' => new Response(200, ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip'], 'fixture'),
            'too_large' => new Response(200, ['Content-Type' => 'text/plain'], str_repeat('a', 1048577)),
            'empty_content' => new Response(200, ['Content-Type' => 'text/html'], '<html><body><script>render()</script></body></html>'),
        ];
        foreach ($cases as $expected => $response) {
            $client = new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
            try {
                (new PublicWebPageFetcher($client, static fn() => ['93.184.216.34']))->fetch('https://example.org/');
                self::fail('Expected failure: '.$expected);
            } catch (SearchException $error) {
                self::assertSame($expected, $error->reason, $expected);
                self::assertSame('fetch', $error->stage, $expected);
                self::assertStringNotContainsString('Private', $error->getMessage());
            }
        }
        foreach (['dns_failed' => [], 'private_address' => ['10.0.0.8']] as $expected => $addresses) {
            try {
                (new PublicWebPageFetcher(new Client(['handler' => HandlerStack::create(new MockHandler([]))]), static fn() => $addresses))->fetch('https://example.org/');
                self::fail('Expected failure: '.$expected);
            } catch (SearchException $error) {
                self::assertSame($expected, $error->reason);
            }
        }
    }
}
