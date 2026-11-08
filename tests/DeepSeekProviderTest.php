<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\AiConnectionException;
use App\Infrastructure\AI\DeepSeekProvider;
use App\Infrastructure\AI\ProviderTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DeepSeekProviderTest extends TestCase
{
    private array $history = [];

    private function provider(array $responses, string $key = 'fixture-key', string $baseUrl = DeepSeekProvider::BASE_URI): DeepSeekProvider
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->history));
        return new DeepSeekProvider(new ProviderTransport(
            $baseUrl,
            $key,
            http: new Client(['base_uri' => $baseUrl, 'handler' => $handler])
        ));
    }

    private function response(array $body): Response
    {
        return new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function sentBody(int $index): array
    {
        return json_decode((string)$this->history[$index]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testFixedCatalogueNeedsNoNetworkCall(): void
    {
        $provider = $this->provider([]);
        $models = $provider->listModels();
        self::assertSame('deepseek-flash', $models[0]['id']);
        self::assertSame('deepseek-v4-pro', $models[1]['id']);
        foreach ($models as $model) {
            self::assertFalse($model['supports_images']);
            self::assertTrue($model['supports_tools']);
        }
        self::assertSame([], $this->history);
    }

    public function testPlainReplyKeepsTokenUsageWithoutOfferingTools(): void
    {
        $usage = ['prompt_tokens' => 1517, 'completion_tokens' => 342];
        $provider = $this->provider([$this->response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Fixture answer']]], 'usage' => $usage])]);

        $reply = $provider->generateReply([['role' => 'user', 'content' => 'Question']], 'deepseek-flash');

        self::assertSame('Fixture answer', $reply->text);
        self::assertSame($usage, $reply->usage);
        self::assertNull($reply->webRequest);
        $body = $this->sentBody(0);
        self::assertSame('/chat/completions', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('deepseek-flash', $body['model']);
        self::assertSame(4096, $body['max_tokens']);
        self::assertArrayNotHasKey('tools', $body);
        self::assertArrayNotHasKey('provider', $body);
    }

    public function testWebReplyOffersToolsAndReturnsApprovalRequest(): void
    {
        $provider = $this->provider([
            $this->response(['choices' => [['message' => ['content' => null, 'tool_calls' => [['type' => 'function', 'function' => ['name' => 'web_search', 'arguments' => '{"query":"Public fixture"}']]]]]]]),
        ]);
        $reply = $provider->generateWebReply([['role' => 'user', 'content' => 'Find']], 'deepseek-v4-pro');
        self::assertSame(['kind' => 'search', 'query' => 'Public fixture'], $reply->webRequest);
        $body = $this->sentBody(0);
        self::assertSame(['web_search', 'web_fetch'], array_column(array_column($body['tools'], 'function'), 'name'));
        self::assertFalse($body['parallel_tool_calls']);
    }

    public function testEmptyContentFailsWithClosedReason(): void
    {
        $provider = $this->provider([$this->response(['choices' => [['message' => ['role' => 'assistant', 'content' => '   ']]], 'usage' => ['prompt_tokens' => 10]])]);
        $this->expectException(AiConnectionException::class);
        $provider->generateReply([['role' => 'user', 'content' => 'Question']], 'deepseek-flash');
    }

    public function testFailuresUseClosedReasonsWithoutProviderContent(): void
    {
        $cases = [
            'provider_auth' => new Response(401, [], '{"error":{"message":"Private auth detail"}}'),
            'provider_limit' => new Response(429, [], '{"error":{"message":"Private rate detail"}}'),
            'provider_timeout' => new RequestException('cURL error 28', new Request('POST', 'chat/completions'), null, null, ['errno' => 28]),
        ];
        foreach ($cases as $reason => $response) {
            $provider = $this->provider([$response]);
            try {
                $provider->generateReply([['role' => 'user', 'content' => 'Question']], 'deepseek-flash');
                self::fail("Expected failure for $reason");
            } catch (AiConnectionException $error) {
                self::assertSame($reason, $error->reason);
                self::assertStringNotContainsString('Private', $error->getMessage());
            }
        }
    }

    public function testMissingKeyNeverContactsProvider(): void
    {
        $provider = $this->provider([], key: '');
        self::assertFalse($provider->isConfigured());
        try {
            $provider->generateReply([['role' => 'user', 'content' => 'Question']], 'deepseek-flash');
            self::fail('Empty key must fail without a request');
        } catch (AiConnectionException $error) {
            self::assertSame('missing_configuration', $error->reason);
        }
        self::assertSame([], $this->history);
    }

    public function testCustomBaseUrlFromEnvironmentIsUsed(): void
    {
        $provider = $this->provider([$this->response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Fixture answer']]]])], baseUrl: 'https://api.fixture.invalid/v1/');
        $provider->generateReply([['role' => 'user', 'content' => 'Question']], 'deepseek-flash');
        self::assertSame('api.fixture.invalid', $this->history[0]['request']->getUri()->getHost());
        self::assertSame('/v1/chat/completions', $this->history[0]['request']->getUri()->getPath());
    }
}
