<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\AI\OvhAiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OvhAiClientTest extends TestCase
{
    public function testCatalogueRequestUsesAuthentication(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"data":[{"id":"Qwen3.8-27B"}]}'),
        ]));
        $handler->push(Middleware::history($history));
        $client = new OvhAiClient(
            'https://example.invalid/v1',
            'dummy-value',
            'unused',
            httpClient: new Client(['base_uri' => 'https://example.invalid/v1/', 'handler' => $handler])
        );
        $this->assertSame([['id' => 'Qwen3.8-27B']], $client->listModels());
        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/v1/models', $request->getUri()->getPath());
        $this->assertSame('Bearer dummy-value', $request->getHeaderLine('Authorization'));
    }

    public function testMalformedCatalogueIsRejected(): void
    {
        $client = new OvhAiClient(
            'https://example.invalid/v1',
            'dummy-value',
            'unused',
            httpClient: new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], '{"data":[{"id":42}]}'),
            ]))])
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider connection failed');
        $client->listModels();
    }

    public function testHttpErrorDoesNotExposeResponseOrPreviousException(): void
    {
        $client = new OvhAiClient(
            'https://example.invalid/v1',
            'dummy-value',
            'unused',
            httpClient: new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(401, [], 'private diagnostic dummy-value'),
            ]))])
        );
        try {
            $client->listModels();
            $this->fail('Expected an unavailable catalogue');
        } catch (RuntimeException $error) {
            $this->assertSame('AI provider connection failed', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }
}
