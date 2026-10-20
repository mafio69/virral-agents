<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\AiConnectionException;
use App\Infrastructure\AI\AnthropicProvider;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\ProviderRegistry;
use App\Infrastructure\AI\ProviderTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AnthropicProviderTest extends TestCase
{
    private array $history = [];

    private function provider(array $responses): AnthropicProvider
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->history));
        return new AnthropicProvider(new ProviderTransport(
            'https://api.anthropic.com/v1/',
            'fixture-key',
            authHeader: 'x-api-key',
            authPrefix: '',
            headers: ['anthropic-version' => '2023-06-01'],
            http: new Client(['base_uri' => 'https://api.anthropic.com/v1/', 'handler' => $handler])
        ));
    }

    private function response(array $body): Response
    {
        return new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function testPaginatedAccountCatalogueAndCapabilityMetadata(): void
    {
        $provider = $this->provider([
            $this->response(['data' => [['id' => 'claude-opus-5-5', 'display_name' => 'Claude fixture', 'capabilities' => ['image_input' => ['supported' => false]]]], 'has_more' => true, 'last_id' => 'claude-opus-5-5']),
            $this->response(['data' => [['id' => 'claude-sonnet-5-5'], ['id' => 'claude-retired', 'lifecycle' => 'retired']], 'has_more' => false]),
        ]);
        $models = $provider->listModels();
        self::assertCount(2, $models);
        self::assertSame('Claude fixture', $models[0]['name']);
        self::assertFalse($models[0]['supports_images']);
        self::assertTrue($models[1]['supports_images']);
        self::assertSame($models, $provider->listModels());
        self::assertCount(2, $this->history);
        $request = $this->history[1]['request'];
        self::assertSame('api.anthropic.com', $request->getUri()->getHost());
        self::assertStringContainsString('after_id=claude-opus-5-5', $request->getUri()->getQuery());
        self::assertSame('fixture-key', $request->getHeaderLine('x-api-key'));
        self::assertSame('', $request->getHeaderLine('Authorization'));
        self::assertSame('2023-06-01', $request->getHeaderLine('anthropic-version'));
    }

    public function testNativeMessagesSystemImagesAndTextOnlyOutput(): void
    {
        $provider = $this->provider([$this->response(['content' => [
            ['type' => 'thinking', 'thinking' => 'Hidden fixture'],
            ['type' => 'tool_use', 'name' => 'ignored'],
            ['type' => 'text', 'text' => 'Fixture answer'],
        ]])]);
        $answer = $provider->generate([
            ['role' => 'system', 'content' => 'Fixture instruction'],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Fixture image'], ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,YQ==']]]],
            ['role' => 'assistant', 'content' => 'Fixture history'],
            ['role' => 'user', 'content' => 'Fixture follow-up'],
        ], 'claude-sonnet-5-5');
        self::assertSame('Fixture answer', $answer);
        $request = $this->history[0]['request'];
        self::assertSame('https://api.anthropic.com/v1/messages', (string)$request->getUri());
        $body = json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Fixture instruction', $body['system'][0]['text']);
        self::assertCount(3, $body['messages']);
        self::assertSame('assistant', $body['messages'][1]['role']);
        self::assertSame(['type' => 'base64', 'media_type' => 'image/png', 'data' => 'YQ=='], $body['messages'][0]['content'][1]['source']);
        self::assertSame(4096, $body['max_tokens']);
        self::assertArrayNotHasKey('tools', $body);
        self::assertFalse($this->history[0]['options']['allow_redirects']);
    }

    public function testRemoteImageIsRejectedWithoutNetworkAccess(): void
    {
        try {
            $this->provider([])->generate([['role' => 'user', 'content' => [['type' => 'image_url', 'image_url' => ['url' => 'https://example.invalid/private']]]]], 'claude-sonnet-5-5');
            self::fail('Remote images must be rejected');
        } catch (AiConnectionException $error) {
            self::assertSame('invalid_response', $error->reason);
            self::assertSame([], $this->history);
        }
    }

    public function testRepeatedCursorAndMalformedCatalogueAreRejected(): void
    {
        foreach ([
            [$this->response(['data' => 'invalid'])],
            [$this->response(['data' => [['id' => 'invalid/model']]])],
            [$this->response(['data' => [], 'has_more' => true, 'last_id' => 'repeat']), $this->response(['data' => [], 'has_more' => true, 'last_id' => 'repeat'])],
        ] as $responses) {
            try {
                $this->provider($responses)->listModels();
                self::fail('Invalid catalogue expected');
            } catch (AiConnectionException $error) {
                self::assertSame('invalid_response', $error->reason);
            }
        }
    }

    public function testNonTextOutputIsNotExecutedOrReturnedAsAnswer(): void
    {
        $this->expectException(AiConnectionException::class);
        $this->provider([$this->response(['content' => [['type' => 'tool_use', 'name' => 'ignored']]])])->generate([['role' => 'user', 'content' => 'Fixture']], 'claude-sonnet-5-5');
    }

    public function testRegistryKeepsAnthropicBindingAndImageCapability(): void
    {
        $provider = $this->provider([$this->response(['data' => [['id' => 'claude-sonnet-5-5']], 'has_more' => false])]);
        $legacy = $this->createMock(ChatModelRegistryInterface::class);
        $legacy->expects($this->never())->method('clientFor');
        $registry = new ProviderRegistry($legacy, ['anthropic' => $provider]);
        $model = $registry->resolve($registry->listFor('anthropic')[0]['id']);
        self::assertSame('anthropic', $model->connection);
        self::assertSame($provider, $registry->clientFor($model));
        self::assertTrue($registry->supportsImages('anthropic:claude-sonnet-5-5'));
        self::assertCount(1, $this->history);
    }
}
