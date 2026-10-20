<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\AiConnectionException;
use App\Infrastructure\AI\OpenAiCompatClient;
use App\Infrastructure\AI\ProviderTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AiTimeoutTest extends TestCase
{
    public function testSlowModelBudgetIsExplicitAndTimeoutDoesNotExposeTransportDetails(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"choices":[{"message":{"content":"Synthetic answer"}}]}'),
            new ConnectException('Private transport diagnostics', new Request('POST', 'https://example.invalid/'), handlerContext: ['errno' => 28]),
        ]));
        $stack->push(Middleware::history($history));
        $client = new OpenAiCompatClient('https://example.invalid/', 'dummy-fixture', 'fixture', httpClient: new Client(['handler' => $stack]));
        self::assertSame('Synthetic answer', $client->generate([]));
        self::assertSame(300, $history[0]['options']['timeout']);
        try {
            $client->generate([]);
            self::fail('Timeout expected');
        } catch (AiConnectionException $error) {
            self::assertSame('provider_timeout', $error->reason);
            self::assertStringNotContainsString('Private', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }

    public function testNativeProviderTransportUsesSameBudgetAndTimeoutReason(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"ok":true}'),
            new ConnectException('Private transport diagnostics', new Request('POST', 'https://example.invalid/'), handlerContext: ['errno' => 28]),
            new Response(504, [], 'Private gateway body'),
        ]));
        $stack->push(Middleware::history($history));
        $transport = new ProviderTransport('https://example.invalid/', 'dummy-fixture', http: new Client(['handler' => $stack]));

        self::assertSame(['ok' => true], $transport->request('POST', 'generate', []));
        self::assertSame(300, $history[0]['options']['timeout']);

        foreach (['connect timeout', 'gateway timeout'] as $case) {
            try {
                $transport->request('POST', 'generate', []);
                self::fail('Timeout expected: '.$case);
            } catch (AiConnectionException $error) {
                self::assertSame('provider_timeout', $error->reason, $case);
                self::assertStringNotContainsString('Private', $error->getMessage());
                self::assertNull($error->getPrevious());
            }
        }
    }
}
