<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Search\McpSearchClient;
use App\Infrastructure\Search\SearchException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class McpSearchClientTest extends TestCase
{
    public static function rpc(int $id, array $result, bool $sse = false): Response
    {
        $json = json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_THROW_ON_ERROR);
        return new Response(200, ['Content-Type' => $sse ? 'text/event-stream' : 'application/json', 'Mcp-Session-Id' => 'fixture-session'], $sse ? "event: message\ndata: ".$json."\n\n" : $json);
    }

    public static function tools(): array
    {
        return ['tools' => [['name' => 'web_search', 'inputSchema' => ['type' => 'object', 'properties' => [
            'query' => ['type' => 'string'], 'max_results' => ['type' => 'integer'],
        ], 'required' => ['query']]]]];
    }

    public function testSessionNegotiationTextJsonSseAndClose(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            self::rpc(1, ['protocolVersion' => '2025-06-18'], true), new Response(202), self::rpc(3, self::tools()),
            self::rpc(4, ['content' => [['type' => 'text', 'text' => json_encode(['source' => 'searxng', 'results' => [
                ['title' => null, 'url' => 'https://example.org/source', 'snippet' => str_repeat('a', 500)],
                ['title' => 'Invalid', 'url' => 'javascript:alert(1)', 'snippet' => 'Do not use'],
                ['title' => null, 'url' => null, 'snippet' => 'No address'],
            ]])]]], true), new Response(204),
        ]));
        $handler->push(Middleware::history($history));
        $client = new McpSearchClient('http://mcp.test/mcp', new Client(['handler' => $handler]));
        $result = $client->search('Public query', 5);
        $client->close();
        self::assertSame('searxng', $result['source']);
        self::assertCount(1, $result['results']);
        self::assertSame('', $result['results'][0]['title']);
        self::assertSame(400, strlen($result['results'][0]['snippet']));
        self::assertCount(5, $history);
        $initialize = json_decode((string)$history[0]['request']->getBody(), true);
        self::assertSame('initialize', $initialize['method']);
        $notification = json_decode((string)$history[1]['request']->getBody(), true);
        self::assertArrayNotHasKey('id', $notification);
        self::assertSame('notifications/initialized', $notification['method']);
        self::assertSame('2025-06-18', $history[3]['request']->getHeaderLine('MCP-Protocol-Version'));
        self::assertSame('fixture-session', $history[3]['request']->getHeaderLine('Mcp-Session-Id'));
        self::assertSame(['name' => 'web_search', 'arguments' => ['query' => 'Public query', 'max_results' => 5]], json_decode((string)$history[3]['request']->getBody(), true)['params']);
        self::assertFalse($history[3]['request']->hasHeader('Authorization'));
        self::assertSame('DELETE', $history[4]['request']->getMethod());
    }

    public function testProviderErrorsMcpErrorsAndMalformedJsonFailWithoutRetrying(): void
    {
        foreach ([
            ['content' => [['type' => 'text', 'text' => '{bad']]],
            ['isError' => true, 'content' => []],
            ['content' => [['type' => 'text', 'text' => '{"status":"error","source":null,"results":[],"errors":["searxng_timeout","perplexity_no_key"]}']]],
            ['content' => [['type' => 'text', 'text' => '{"source":null,"results":[],"errors":["unavailable"]}']]],
        ] as $result) {
            $history = [];
            $handler = HandlerStack::create(new MockHandler([self::rpc(1, ['protocolVersion' => '2025-03-26']), new Response(202), self::rpc(3, self::tools()), self::rpc(4, $result)]));
            $handler->push(Middleware::history($history));
            $client = new McpSearchClient('http://mcp.test/mcp', new Client(['handler' => $handler]));
            try {
                $client->search('Public query', 3);
                self::fail('Expected search error');
            } catch (SearchException $error) {
                self::assertSame('Wyszukiwanie jest niedostępne.', $error->getMessage());
                self::assertCount(4, $history);
            }
        }
    }

    public function testExplicitEmptyStatusAcceptsNullSourceButProviderFailureDoesNot(): void
    {
        foreach ([[], ['unavailable']] as $errors) {
            $body = ['status' => 'empty', 'source' => null, 'results' => [], 'errors' => $errors];
            $handler = HandlerStack::create(new MockHandler([
                self::rpc(1, ['protocolVersion' => '2025-03-26']), new Response(202),
                self::rpc(3, self::tools()),
                self::rpc(4, ['content' => [['type' => 'text', 'text' => json_encode($body)]]]),
            ]));
            $client = new McpSearchClient('http://mcp.test/mcp', new Client(['handler' => $handler]));
            if ($errors === []) {
                self::assertSame(['source' => null, 'results' => []], $client->search('Public query', 3));
            } else {
                $this->expectException(SearchException::class);
                $client->search('Public query', 3);
            }
        }
    }

    public function testSupportedProtocolsNegotiateAndUnknownVersionStopsBeforeTools(): void
    {
        foreach (McpSearchClient::SUPPORTED_PROTOCOLS as $version) {
            $history = [];
            $handler = HandlerStack::create(new MockHandler([
                self::rpc(1, ['protocolVersion' => $version]), new Response(202), self::rpc(3, self::tools()),
            ]));
            $handler->push(Middleware::history($history));
            $client = new McpSearchClient('http://mcp.test/mcp', new Client(['handler' => $handler]));
            self::assertSame('web_search', $client->definition()['function']['name']);
            self::assertSame($version, $history[2]['request']->getHeaderLine('MCP-Protocol-Version'));
        }
        $client = new McpSearchClient('http://mcp.test/mcp', new Client([
            'handler' => HandlerStack::create(new MockHandler([self::rpc(1, ['protocolVersion' => 'unknown'])])),
        ]));
        $this->expectException(SearchException::class);
        $client->definition();
    }

    public function testInvalidQueryNeverConnects(): void
    {
        $client = new McpSearchClient('http://mcp.test/mcp', new Client(['handler' => HandlerStack::create(new MockHandler([]))]));
        $this->expectException(SearchException::class);
        $client->search('  ', 3);
    }

    public function testTokenIsSentAsBearerOnEveryRequestIncludingClose(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            self::rpc(1, ['protocolVersion' => '2025-06-18']), new Response(202), self::rpc(3, self::tools()), new Response(204),
        ]));
        $handler->push(Middleware::history($history));
        $client = new McpSearchClient('http://mcp.test/mcp', new Client(['handler' => $handler]), 'fixture-mcp-token');
        self::assertSame('web_search', $client->definition()['function']['name']);
        $client->close();
        self::assertCount(4, $history);
        foreach ($history as $entry) {
            self::assertSame('Bearer fixture-mcp-token', $entry['request']->getHeaderLine('Authorization'));
        }
    }

    public function testRejectedOrMissingTokenIsReportedAsUnauthorizedWithoutLeakingIt(): void
    {
        foreach ([401, 403] as $status) {
            $client = new McpSearchClient('http://mcp.test/mcp', new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response($status, ['WWW-Authenticate' => 'Bearer'], '{"error":"Unauthorized"}'),
            ]))]), 'fixture-mcp-token');
            try {
                $client->definition();
                self::fail('Expected an authentication failure.');
            } catch (SearchException $error) {
                self::assertSame('unauthorized', $error->reason);
                self::assertSame('initialize', $error->stage);
                self::assertStringNotContainsString('fixture-mcp-token', $error->getMessage());
            }
        }
        $client = new McpSearchClient('http://mcp.test/mcp', new Client(['http_errors' => false, 'handler' => HandlerStack::create(new MockHandler([
            new Response(401, [], '{"error":"Unauthorized"}'),
        ]))]));
        try {
            $client->definition();
            self::fail('Expected an authentication failure.');
        } catch (SearchException $error) {
            self::assertSame('unauthorized', $error->reason);
        }
    }
}
