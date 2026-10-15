<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\AiConnectionException;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\GeminiProvider;
use App\Infrastructure\AI\ProviderRegistry;
use App\Infrastructure\AI\ProviderTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GeminiProviderTest extends TestCase
{
    private array $history = [];

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXfoAAAAASUVORK5CYII=';

    public function testImageReplyAndCatalogueCapability(): void
    {
        $provider = $this->provider([
            $this->response(['models' => [['name' => 'models/gemini-3.1-flash-image', 'supportedGenerationMethods' => ['generateContent']]]]),
            $this->response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
                ['thought' => true, 'inlineData' => ['mimeType' => 'image/png', 'data' => 'ignored']],
                ['inlineData' => ['mimeType' => 'image/png', 'data' => self::PNG]],
            ]]]]]),
        ]);
        self::assertTrue($provider->listModels()[0]['generates_images']);
        $reply = $provider->generateReply([['role' => 'user', 'content' => 'Fixture drawing']], 'gemini-3.1-flash-image');
        self::assertSame('', $reply->text);
        self::assertSame(['data:image/png;base64,'.self::PNG], $reply->images);
        $payload = json_decode((string)$this->history[1]['request']->getBody(), true);
        self::assertSame(['TEXT', 'IMAGE'], $payload['generationConfig']['responseModalities']);
        self::assertArrayNotHasKey('tools', $payload);
    }

    public function testUnsafeGeneratedImagesAndTruncatedResponsesAreRejected(): void
    {
        foreach ([
            ['mimeType' => 'image/svg+xml', 'data' => base64_encode('<svg/>')],
            ['mimeType' => 'image/jpeg', 'data' => self::PNG],
            ['mimeType' => 'image/png', 'data' => 'broken'],
        ] as $image) {
            try {
                $this->provider([$this->response(['candidates' => [['content' => ['parts' => [['inlineData' => $image]]]]]])])
                    ->generateReply([['role' => 'user', 'content' => 'Fixture']], 'gemini-3.1-flash-image');
                self::fail('Invalid image must fail');
            } catch (AiConnectionException $error) { self::assertSame('invalid_response', $error->reason); }
        }
        $this->expectException(AiConnectionException::class);
        $this->provider([$this->response(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => 'Partial']]]]]])])
            ->generateReply([['role' => 'user', 'content' => 'Fixture']], 'gemini-3.1-flash-image');
    }

    private function provider(array $responses): GeminiProvider
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->history));
        return new GeminiProvider(new ProviderTransport('https://generativelanguage.googleapis.com/v1beta/', 'fixture-key',
            authHeader: 'x-goog-api-key', authPrefix: '',
            http: new Client(['base_uri' => 'https://generativelanguage.googleapis.com/v1beta/', 'handler' => $handler])));
    }

    private function response(array $body): Response { return new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR)); }

    public function testCataloguePaginationFilteringAndRegistryBinding(): void
    {
        $provider = $this->provider([
            $this->response(['models' => [
                ['name' => 'models/gemini-3-flash', 'displayName' => 'Gemini fixture', 'supportedGenerationMethods' => ['generateContent']],
                ['name' => 'models/gemini-embedding', 'supportedGenerationMethods' => ['embedContent']],
                ['name' => 'models/gemini-image', 'supportedGenerationMethods' => ['generateContent']],
            ], 'nextPageToken' => 'fixture-next']),
            $this->response(['models' => [['name' => 'models/gemini-3-pro', 'supportedGenerationMethods' => ['generateContent']]]]),
        ]);
        $registry = new ProviderRegistry($this->createMock(ChatModelRegistryInterface::class), ['google' => $provider]);
        $models = $registry->listFor('google');
        self::assertCount(2, $models);
        self::assertSame('google:gemini-3-flash', $models[0]['id']);
        self::assertTrue($registry->supportsImages($models[0]['id']));
        $resolved = $registry->resolve($models[0]['id']);
        self::assertSame('google', $resolved->connection);
        self::assertSame($provider, $registry->clientFor($resolved));
        self::assertCount(2, $this->history);
        $request = $this->history[1]['request'];
        self::assertSame('fixture-key', $request->getHeaderLine('x-goog-api-key'));
        self::assertSame('', $request->getHeaderLine('Authorization'));
        self::assertStringContainsString('pageToken=fixture-next', $request->getUri()->getQuery());
        self::assertStringNotContainsString('fixture-key', (string)$request->getUri());
    }

    public function testNativeContentsSystemImageAndAssistantRole(): void
    {
        $provider = $this->provider([$this->response(['candidates' => [['content' => ['parts' => [
            ['text' => 'Hidden fixture', 'thought' => true], ['functionCall' => ['name' => 'ignored']], ['text' => 'Fixture answer'],
        ]]]]])]);
        self::assertSame('Fixture answer', $provider->generate([
            ['role' => 'system', 'content' => 'Fixture instruction'],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Fixture image'], ['type' => 'image_url', 'image_url' => ['url' => 'data:image/webp;base64,YQ==']]]],
            ['role' => 'assistant', 'content' => 'Fixture history'],
            ['role' => 'user', 'content' => 'Fixture follow-up'],
        ], 'gemini-3-flash'));
        $request = $this->history[0]['request'];
        self::assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash:generateContent', (string)$request->getUri());
        $body = json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Fixture instruction', $body['systemInstruction']['parts'][0]['text']);
        self::assertCount(3, $body['contents']);
        self::assertSame('model', $body['contents'][1]['role']);
        self::assertSame(['mimeType' => 'image/webp', 'data' => 'YQ=='], $body['contents'][0]['parts'][1]['inlineData']);
        self::assertSame(4096, $body['generationConfig']['maxOutputTokens']);
        self::assertArrayNotHasKey('tools', $body);
        self::assertFalse($this->history[0]['options']['allow_redirects']);
    }

    public function testInvalidModelCannotAlterRequestPath(): void
    {
        try {
            $this->provider([])->generate([['role' => 'user', 'content' => 'Fixture']], '../other?key=fixture');
            self::fail('Invalid model must be rejected');
        } catch (AiConnectionException $error) {
            self::assertSame('model_unavailable', $error->reason);
            self::assertSame([], $this->history);
        }
    }

    public function testRemoteImageIsRejectedWithoutFetchingIt(): void
    {
        try {
            $this->provider([])->generate([['role' => 'user', 'content' => [['type' => 'image_url', 'image_url' => ['url' => 'https://example.invalid/image']]]]], 'gemini-3-flash');
            self::fail('Remote images must be rejected');
        } catch (AiConnectionException) {
            self::assertSame([], $this->history);
        }
    }

    public function testBlockedMissingAndNonTextResponsesAreRejected(): void
    {
        foreach ([['promptFeedback' => ['blockReason' => 'SAFETY']], ['candidates' => []],
            ['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'ignored']]]]]]]] as $body) {
            try {
                $this->provider([$this->response($body)])->generate([['role' => 'user', 'content' => 'Fixture']], 'gemini-3-flash');
                self::fail('Text answer required');
            } catch (AiConnectionException $error) {
                self::assertSame('invalid_response', $error->reason);
            }
        }
    }

    public function testMalformedAndRepeatingCataloguePagesAreRejected(): void
    {
        foreach ([
            [$this->response(['models' => null])],
            [$this->response(['models' => [['name' => 123]]])],
            [$this->response(['models' => [], 'nextPageToken' => 'repeat']), $this->response(['models' => [], 'nextPageToken' => 'repeat'])],
        ] as $responses) {
            try {
                $this->provider($responses)->listModels();
                self::fail('Invalid catalogue expected');
            } catch (AiConnectionException $error) {
                self::assertSame('invalid_response', $error->reason);
            }
        }
    }
}
