<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\AI\AiModel;
use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Exception\AiConnectionException;
use App\Exception\NotFoundException;
use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\OpenAiProvider;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\AI\ProviderRegistry;
use App\Infrastructure\AI\ProviderTransport;
use App\Infrastructure\Database\MigrationRunner;
use App\Repository\ChatRepository;
use App\Service\ChatService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProviderIntegrationTest extends TestCase
{
    private const IMAGE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXfoAAAAASUVORK5CYII=';
    private array $history = [];

    private function transport(array $responses, string $key = 'fixture-key'): ProviderTransport
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->history));
        return new ProviderTransport('https://api.openai.com/v1/', $key,
            http: new Client(['base_uri' => 'https://api.openai.com/v1/', 'handler' => $handler]));
    }

    private function legacy(): ChatModelRegistryInterface
    {
        return new class implements ChatModelRegistryInterface {
            public function listModels(): array { return []; }
            public function resolve(string $selector): AiModel { throw new AiConnectionException('model_unavailable'); }
            public function clientFor(AiModel $model): AiClientInterface { throw new AiConnectionException('missing_configuration'); }
        };
    }

    private function catalogue(): Response
    {
        return new Response(200, [], json_encode(['data' => [
            ['id' => 'gpt-6-astra'], ['id' => 'gpt-image-2'], ['id' => 'text-embedding-3-small'], ['id' => 'gpt-audio'],
        ]], JSON_THROW_ON_ERROR));
    }

    private function answer(string $text = 'Fixture answer'): Response
    {
        return new Response(200, [], json_encode(['output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]],
        ]], JSON_THROW_ON_ERROR));
    }

    public function testConnectionsWithoutUsableKeyAreHiddenButExistingSelectorsStillResolve(): void
    {
        foreach (['', '   ', 'encrypted:fixture'] as $key) {
            $registry = new ProviderRegistry($this->legacy(), [
                'openai' => new OpenAiProvider($this->transport([$this->catalogue()], $key)),
            ]);
            self::assertSame([], $registry->connections());
            try {
                $registry->resolve('openai:gpt-6-astra');
                self::fail('A provider without a key must not list models.');
            } catch (AiConnectionException $error) {
                self::assertSame('missing_configuration', $error->reason);
            }
        }
        self::assertSame([], $this->history);
        $configured = new ProviderRegistry($this->legacy(), ['openai' => new OpenAiProvider($this->transport([$this->catalogue()]))]);
        self::assertSame('gpt-6-astra', $configured->resolve('openai:gpt-6-astra')->idModel);
    }

    public function testOvhConnectionIsListedOnlyWithToken(): void
    {
        foreach (['' => false, 'no-token-set' => false, 'encrypted:fixture' => false, 'fixture-token' => true] as $token => $listed) {
            $catalog = new \App\Service\ModelCatalogService(new OvhAiClient('https://example.invalid/', (string)$token, 'fixture'), $this->legacy(), new \Psr\Log\NullLogger());
            self::assertSame($listed, in_array('ovh', array_column($catalog->getConnections(), 'id'), true));
            self::assertContains('zai', array_column($catalog->getConnections(), 'id'));
        }
    }

    public function testOpenAiCatalogueAndMultimodalStatelessResponse(): void
    {
        $provider = new OpenAiProvider($this->transport([$this->catalogue(), $this->answer()]));
        $registry = new ProviderRegistry($this->legacy(), ['openai' => $provider]);
        self::assertSame([['id' => 'openai', 'name' => 'OpenAI']], $registry->connections());
        $models = $registry->listFor('openai');
        self::assertCount(1, $models);
        self::assertTrue($models[0]['supports_images']);
        self::assertSame('openai:gpt-6-astra', $models[0]['id']);
        self::assertStringNotContainsString('fixture-key', json_encode($models));
        $resolved = $registry->resolve($models[0]['id']);
        self::assertSame('openai', $resolved->connection);
        self::assertSame('Fixture answer', $registry->clientFor($resolved)->generate([
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Fixture'], ['type' => 'image_url', 'image_url' => ['url' => self::IMAGE]]]],
        ], $resolved->idModel));
        self::assertCount(2, $this->history); // Catalogue is reused within the request.
        $request = $this->history[1]['request'];
        self::assertSame('https://api.openai.com/v1/responses', (string)$request->getUri());
        $body = json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($body['store']);
        self::assertSame(4096, $body['max_output_tokens']);
        self::assertSame(self::IMAGE, $body['input'][0]['content'][1]['image_url']);
        self::assertSame('input_text', $body['input'][0]['content'][0]['type']);
        self::assertArrayNotHasKey('tools', $body);
        self::assertFalse($this->history[1]['options']['allow_redirects']);
    }

    public function testConversationBindingOwnershipHistoryAndMissingConfiguration(): void
    {
        $pdo = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        (new MigrationRunner($pdo))->run();
        $chats = new ChatRepository($pdo, ProtectedDataFixture::cipher($pdo));
        $provider = new OpenAiProvider($this->transport([$this->catalogue(), $this->answer(), $this->answer('Second fixture')]));
        $registry = new ProviderRegistry($this->legacy(), ['openai' => $provider]);
        $ovh = new OvhAiClient('https://example.invalid/v1', '', 'Qwen3.6-27B');
        $service = ChatServiceFixture::create($chats, $ovh, new NullLogger(), $registry);
        $chat = $service->createChat(new CreateChatInput(1, 'Fixture', 'openai:gpt-6-astra'));
        $other = $service->createChat(new CreateChatInput(1, 'Other', 'openai:gpt-6-astra'));
        $chats->addMessage($other->id, 'user', 'Unrelated fixture');
        try {
            $service->addMessage(new AddChatMessageInput($chat->id, 2, 'Not owner'));
            self::fail('Owner check required');
        } catch (NotFoundException) {
            self::assertCount(1, $this->history);
        }
        $service->addMessage(new AddChatMessageInput($chat->id, 1, 'First fixture'));
        $service->addMessage(new AddChatMessageInput($chat->id, 1, 'Follow-up fixture'));
        $body = json_decode((string)$this->history[2]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(3, $body['input']);
        self::assertStringNotContainsString('Unrelated fixture', json_encode($body));
        $missing = ChatServiceFixture::create($chats, $ovh, new NullLogger(), new ProviderRegistry($this->legacy(), []));
        $before = count($chats->findMessagesByChatId($chat->id));
        try {
            $missing->addMessage(new AddChatMessageInput($chat->id, 1, 'Blocked'));
            self::fail('Missing provider must not fall back to OVH');
        } catch (AiConnectionException) {
            self::assertCount($before, $chats->findMessagesByChatId($chat->id));
            self::assertCount(3, $this->history);
        }
    }

    public function testUnknownModelIsRejected(): void
    {
        $registry = new ProviderRegistry($this->legacy(), ['openai' => new OpenAiProvider($this->transport([$this->catalogue()]))]);
        $this->expectException(AiConnectionException::class);
        $registry->resolve('openai:invented');
    }

    public function testMissingKeyDoesNotMakeNetworkRequest(): void
    {
        try {
            $this->transport([], '')->request('GET', 'models');
            self::fail('Missing key must be rejected');
        } catch (AiConnectionException $error) {
            self::assertSame('missing_configuration', $error->reason);
            self::assertSame([], $this->history);
        }
    }

    public function testProviderFailuresNeverExposeBodyOrPreviousException(): void
    {
        foreach ([401 => 'provider_auth', 403 => 'provider_auth', 404 => 'model_unavailable', 429 => 'provider_limit', 500 => 'connection_failed', 302 => 'connection_failed'] as $status => $reason) {
            try {
                $this->transport([new Response($status, [], 'sensitive fixture-key diagnostic')])->request('GET', 'models');
                self::fail('Provider failure expected');
            } catch (AiConnectionException $error) {
                self::assertSame($reason, $error->reason);
                self::assertNull($error->getPrevious());
                self::assertSame('AI provider connection failed', $error->getMessage());
            }
        }
    }

    public function testMalformedAndOversizedResponsesAreRejected(): void
    {
        foreach (['not json', 'null', str_repeat('x', 4194305)] as $body) {
            try {
                $this->transport([new Response(200, [], $body)])->request('GET', 'models');
                self::fail('Invalid response expected');
            } catch (AiConnectionException $error) {
                self::assertSame('invalid_response', $error->reason);
            }
        }
    }

    public function testNetworkFailuresAreNeutral(): void
    {
        try {
            $this->transport([new ConnectException('fixture-key diagnostic', new Request('GET', 'https://example.invalid'))])->request('GET', 'models');
            self::fail('Network failure expected');
        } catch (AiConnectionException $error) {
            self::assertSame('connection_failed', $error->reason);
            self::assertNull($error->getPrevious());
        }
    }
}
