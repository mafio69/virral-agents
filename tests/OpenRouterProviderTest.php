<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\AiConnectionException;
use App\Infrastructure\AI\OpenRouterProvider;
use App\Infrastructure\AI\ProviderTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class OpenRouterProviderTest extends TestCase
{
    private array $history = [];

    private function provider(array $responses, array $allowed = []): OpenRouterProvider
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->history));
        return new OpenRouterProvider(new ProviderTransport(OpenRouterProvider::BASE_URI, 'fixture-key',
            http: new Client(['base_uri' => OpenRouterProvider::BASE_URI, 'handler' => $handler])), $allowed);
    }

    private function response(array $body): Response
    {
        return new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function catalogue(): Response
    {
        return $this->response(['data' => [
            ['id' => 'vendor/vision-model', 'name' => 'Vendor: Vision', 'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']], 'supported_parameters' => ['tools', 'max_tokens']],
            ['id' => 'vendor/text-model:free', 'name' => 'Vendor: Text (free)', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']], 'supported_parameters' => ['max_tokens']],
            ['id' => 'vendor/image-only', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['image']]],
            ['id' => '../escape', 'name' => 'Invalid fixture'],
            ['id' => 42],
        ]]);
    }

    private function sentBody(int $index): array
    {
        return json_decode((string)$this->history[$index]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCatalogueKeepsValidTextModelsWithCapabilities(): void
    {
        $provider = $this->provider([$this->catalogue()]);
        $models = $provider->listModels();
        self::assertSame(['vendor/text-model:free', 'vendor/vision-model'], array_column($models, 'id'));
        self::assertSame('Vendor: Vision', $models[1]['name']);
        self::assertTrue($models[1]['supports_images']);
        self::assertTrue($models[1]['supports_tools']);
        self::assertFalse($models[0]['supports_images']);
        self::assertFalse($models[0]['supports_tools']);
        self::assertSame($models, $provider->listModels());
        self::assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        self::assertSame('openrouter.ai', $request->getUri()->getHost());
        self::assertSame('/api/v1/models', $request->getUri()->getPath());
        self::assertSame('Bearer fixture-key', $request->getHeaderLine('Authorization'));
        // No attribution headers: the app name and referer are not sent to OpenRouter.
        self::assertFalse($request->hasHeader('HTTP-Referer'));
        self::assertFalse($request->hasHeader('X-Title'));
    }

    public function testAllowListFromEnvironmentLimitsCatalogue(): void
    {
        self::assertSame(['vendor/vision-model', 'vendor/missing'], OpenRouterProvider::allowedModels(' vendor/vision-model, ,vendor/missing,vendor/vision-model '));
        $models = $this->provider([$this->catalogue()], ['vendor/vision-model', 'vendor/missing'])->listModels();
        self::assertSame(['vendor/vision-model'], array_column($models, 'id'));
    }

    public function testChatCompletionDeniesDataCollectionAndPassesImages(): void
    {
        $provider = $this->provider([$this->response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Fixture answer']]]])]);
        $messages = [['role' => 'system', 'content' => 'Fixture system'], ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => 'Describe'], ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,AAAA']],
        ]]];
        self::assertSame('Fixture answer', $provider->generate($messages, 'vendor/vision-model'));
        $body = $this->sentBody(0);
        self::assertSame('/api/v1/chat/completions', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('vendor/vision-model', $body['model']);
        self::assertSame($messages, $body['messages']);
        self::assertSame(['data_collection' => 'deny'], $body['provider']);
        self::assertSame(4096, $body['max_tokens']);
        self::assertArrayNotHasKey('tools', $body);
    }

    public function testWebReplyOffersToolsOnlyToCapableModelsAndReturnsApprovalRequest(): void
    {
        $provider = $this->provider([
            $this->catalogue(),
            $this->response(['choices' => [['message' => ['content' => null, 'tool_calls' => [['type' => 'function', 'function' => ['name' => 'web_search', 'arguments' => '{"query":"Public fixture"}']]]]]]]),
            $this->response(['choices' => [['message' => ['content' => 'Plain fixture answer']]]]),
        ]);
        $reply = $provider->generateWebReply([['role' => 'user', 'content' => 'Find']], 'vendor/vision-model');
        self::assertSame(['kind' => 'search', 'query' => 'Public fixture'], $reply->webRequest);
        $body = $this->sentBody(1);
        self::assertSame(['web_search', 'web_fetch'], array_column(array_column($body['tools'], 'function'), 'name'));
        self::assertFalse($body['parallel_tool_calls']);
        $reply = $provider->generateWebReply([['role' => 'user', 'content' => 'Find']], 'vendor/text-model:free');
        self::assertSame('Plain fixture answer', $reply->text);
        self::assertNull($reply->webRequest);
        self::assertArrayNotHasKey('tools', $this->sentBody(2));
    }

    public function testFailuresUseClosedReasonsWithoutProviderContent(): void
    {
        $cases = [
            'provider_limit' => new Response(402, [], '{"error":{"message":"Private credit detail"}}'),
            'provider_auth' => new Response(401, [], '{"error":{"message":"Private auth detail"}}'),
            'model_unavailable' => new Response(404, [], '{"error":{"message":"Private missing model"}}'),
            'connection_failed' => new Response(503, [], '{"error":{"message":"Private no provider matches data policy"}}'),
            'invalid_response' => $this->response(['error' => ['message' => 'Private upstream detail']]),
        ];
        foreach ($cases as $expected => $response) {
            try {
                $this->provider([$response])->generate([['role' => 'user', 'content' => 'Fixture']], 'vendor/vision-model');
                self::fail('Expected failure: '.$expected);
            } catch (AiConnectionException $error) {
                self::assertSame($expected, $error->reason, $expected);
                self::assertStringNotContainsString('Private', $error->getMessage());
            }
        }
        foreach ([['choices' => []], ['choices' => [['message' => ['content' => '  ']]]]] as $body) {
            try {
                $this->provider([$this->response($body)])->generate([['role' => 'user', 'content' => 'Fixture']], 'vendor/vision-model');
                self::fail('Expected invalid response');
            } catch (AiConnectionException $error) {
                self::assertSame('invalid_response', $error->reason);
            }
        }
    }

    public function testRegistryResolvesSelectorsWithColonInModelId(): void
    {
        $provider = $this->provider([$this->catalogue()]);
        $registry = new \App\Infrastructure\AI\ProviderRegistry($this->createMock(\App\Infrastructure\AI\ChatModelRegistryInterface::class), ['openrouter' => $provider]);
        $listed = $registry->listFor('openrouter');
        self::assertSame(['openrouter:vendor/text-model:free', 'openrouter:vendor/vision-model'], array_column($listed, 'id'));
        self::assertSame('OpenRouter — Vendor: Vision', $listed[1]['name']);
        self::assertArrayNotHasKey('supports_tools', $listed[1]);
        $model = $registry->resolve('openrouter:vendor/text-model:free');
        self::assertSame('vendor/text-model:free', $model->idModel);
        self::assertSame($provider, $registry->clientFor($model));
        self::assertTrue($registry->supportsImages('openrouter:vendor/vision-model'));
        self::assertFalse($registry->supportsImages('openrouter:vendor/text-model:free'));
        $this->expectException(AiConnectionException::class);
        $registry->resolve('openrouter:vendor/image-only');
    }

    public function testMissingKeyNeverContactsOpenRouter(): void
    {
        $handler = HandlerStack::create(new MockHandler([]));
        $handler->push(Middleware::history($this->history));
        $provider = new OpenRouterProvider(new ProviderTransport(OpenRouterProvider::BASE_URI, '', http: new Client(['handler' => $handler])));
        try {
            $provider->listModels();
            self::fail('Expected missing configuration');
        } catch (AiConnectionException $error) {
            self::assertSame('missing_configuration', $error->reason);
        }
        self::assertSame([], $this->history);
    }
}
