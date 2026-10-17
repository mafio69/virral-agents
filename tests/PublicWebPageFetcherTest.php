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
}
