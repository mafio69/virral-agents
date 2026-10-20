<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\AI\AiModel;
use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Exception\AiConnectionException;
use App\Exception\NotFoundException;
use App\Infrastructure\AI\AiClientFactoryInterface;
use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\AI\OpenAiCompatClient;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\AI\ZaiChatModelRegistry;
use App\Infrastructure\AI\ZaiEndpointPolicy;
use App\Infrastructure\Database\MigrationRunner;
use App\Repository\AiModelRepository;
use App\Repository\ChatRepository;
use App\Service\ChatService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ZaiChatIntegrationTest extends TestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXfoAAAAASUVORK5CYII=';
    private PDO $pdo;
    private ZaiChatModelRegistry $registry;
    private AiClientInterface $client;
    private ChatService $chat;
    private array $history = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $migrations = new MigrationRunner($this->pdo);
        $migrations->run();
        $migrations->run();
        $this->pdo->exec("INSERT INTO users (id,email,password_hash,created_at) VALUES (1,'fixture@example.invalid','dummy','now')");
        $this->pdo->exec("INSERT INTO ai_urls (id,url,created_at,updated_at) VALUES (1,'https://api.z.ai/api/paas/v4/','now','now')");
        $this->pdo->exec("INSERT INTO ai_providers (id,name,providers_secret,created_at,updated_at) VALUES (1,'Z.ai','unused-fixture','now','now')");
        $this->pdo->exec("INSERT INTO ai_models (id,provider_id,id_model,name_display,endpoint_url_id,created_at,updated_at) VALUES (1,1,'glm-5.3-flash','GLM Flash',1,'now','now')");
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"choices":[{"message":{"content":"Opis obrazu"}}]}'),
            new Response(200, [], '{"choices":[{"message":{"content":"Dalsza odpowiedź"}}]}'),
        ]));
        $handler->push(Middleware::history($this->history));
        $this->client = new OpenAiCompatClient(
            ZaiEndpointPolicy::BASE_URI,
            'dummy-value',
            'glm-5.3-flash',
            httpClient: new Client(['base_uri' => ZaiEndpointPolicy::BASE_URI, 'handler' => $handler])
        );
        $factory = new class ($this->client) implements AiClientFactoryInterface {
            public function __construct(private AiClientInterface $client)
            {
            }
            public function fromModel(AiModel $model): AiClientInterface
            {
                return $this->client;
            }
        };
        $this->registry = new ZaiChatModelRegistry(
            new AiModelRepository($this->pdo),
            static fn () => $factory,
            new class () implements \App\Infrastructure\AI\ModelCatalogueInterface {
                public function listModels(): array
                {
                    return [['id' => 'glm-5.3-flash', 'supports_images' => true]];
                }
            }
        );
        $this->chat = ChatServiceFixture::create(new ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo)), new OvhAiClient('https://example.invalid/v1', '', 'Qwen3.6-27B'), new NullLogger(), $this->registry);
    }

    public function testImageAndHistoryReachOnlySelectedProvider(): void
    {
        $selector = $this->registry->listModels()[0]['id'];
        $chat = $this->chat->createChat(new CreateChatInput(1, 'Obraz', $selector));
        $other = $this->chat->createChat(new CreateChatInput(1, 'Inna rozmowa', $selector));
        (new ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo)))->addMessage($other->id, 'user', 'Nie przekazuj tej historii');
        $this->chat->addMessage(new AddChatMessageInput($chat->id, 1, 'Opisz', [self::PNG]));
        $this->chat->addMessage(new AddChatMessageInput($chat->id, 1, 'Co dalej?'));
        $request = $this->history[1]['request'];
        $body = json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/api/paas/v4/chat/completions', $request->getUri()->getPath());
        $this->assertSame('api.z.ai', $request->getUri()->getHost());
        $this->assertSame('glm-5.3-flash', $body['model']);
        $this->assertSame(self::PNG, $body['messages'][0]['content'][1]['image_url']['url']);
        $this->assertCount(3, $body['messages']);
        $this->assertStringNotContainsString('Nie przekazuj', json_encode($body));
        $this->assertArrayNotHasKey('tools', $body);
        $this->assertFalse($this->history[1]['options']['allow_redirects']);
    }

    public function testChangedBindingBlocksSendingBeforeMessageIsSaved(): void
    {
        $chat = $this->chat->createChat(new CreateChatInput(1, 'Pin', $this->registry->listModels()[0]['id']));
        $this->pdo->exec("UPDATE ai_models SET id_model = 'glm-5.3' WHERE id = 1");
        try {
            $this->chat->addMessage(new AddChatMessageInput($chat->id, 1, 'Historia'));
            $this->fail('Expected binding rejection');
        } catch (AiConnectionException $error) {
            $this->assertSame('model_unavailable', $error->reason);
        }
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn());
        $this->assertSame([], $this->history);
    }

    public function testOwnerIsCheckedBeforeProviderAccess(): void
    {
        $chat = $this->chat->createChat(new CreateChatInput(1, 'Owner', $this->registry->listModels()[0]['id']));
        $this->expectException(NotFoundException::class);
        $this->chat->addMessage(new AddChatMessageInput($chat->id, 2, 'Nie moje'));
    }

    public function testOtherEndpointsAndDeletedProvidersCannotConfigurePublicModels(): void
    {
        $this->pdo->exec("UPDATE ai_urls SET url = 'https://api.z.ai/api/coding/paas/v4/' WHERE id = 1");
        $publicModels = $this->registry->listModels();
        $this->assertCount(1, $publicModels);
        $this->assertFalse($publicModels[0]['configured']);
        $this->assertSame('zai:glm-5.3-flash', $publicModels[0]['id']);
        $this->pdo->exec("UPDATE ai_urls SET url = 'https://api.z.ai/api/paas/v4/' WHERE id = 1");
        $this->pdo->exec("UPDATE ai_providers SET deleted_at = 'now' WHERE id = 1");
        $this->assertSame($publicModels, $this->registry->listModels());
        $this->expectException(AiConnectionException::class);
        $this->registry->resolve($publicModels[0]['id']);
    }

    public function testZaiCatalogueWorksWhenOvhIsUnconfiguredWithoutLeakingFields(): void
    {
        $models = (new \App\Service\ModelCatalogService(new OvhAiClient('https://example.invalid/', '', 'fixture'), $this->registry, new NullLogger()))->getAvailableModels('zai');
        $this->assertCount(1, $models);
        $this->assertTrue($models[0]['supports_images']);
        $this->assertSame('zai', $models[0]['provider']);
        $this->assertArrayNotHasKey('providers_secret', $models[0]);
        $this->assertArrayNotHasKey('url', $models[0]);
    }

    public function testProviderDiagnosticsDoNotEscapeTransport(): void
    {
        $client = new OpenAiCompatClient(
            ZaiEndpointPolicy::BASE_URI,
            'dummy-value',
            'glm-5.3',
            httpClient: new Client(['handler' => HandlerStack::create(new MockHandler([new Response(401, [], 'private diagnostic dummy-value')]))])
        );
        try {
            $client->generate([['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected access rejection');
        } catch (AiConnectionException $error) {
            $this->assertSame('provider_auth', $error->reason);
            $this->assertSame('AI provider connection failed', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    private function environmentRegistry(bool $configured = true): ZaiChatModelRegistry
    {
        $client = $this->client;
        $factory = new class ($client) implements AiClientFactoryInterface {
            public function __construct(private AiClientInterface $client)
            {
            }
            public function fromModel(AiModel $model): AiClientInterface
            {
                return $this->client;
            }
        };
        return new ZaiChatModelRegistry(
            new AiModelRepository($this->pdo),
            static fn () => $factory,
            new class () implements \App\Infrastructure\AI\ModelCatalogueInterface {
                public function listModels(): array
                {
                    return [['id' => 'glm-5.3-flash', 'supports_images' => true]];
                }
            },
            environmentClient: $configured ? $client : null
        );
    }

    private function chatUsing(ZaiChatModelRegistry $registry): ChatService
    {
        return ChatServiceFixture::create(
            new ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo)),
            new OvhAiClient('https://example.invalid/v1', '', 'Qwen3.6-27B'),
            new NullLogger(),
            $registry
        );
    }

    public function testEnvironmentConnectionSendsImagesAndHistoryToStandardZaiEndpoint(): void
    {
        $registry = $this->environmentRegistry();
        $this->pdo->exec('DELETE FROM ai_models');
        $this->pdo->exec('DELETE FROM ai_providers');
        $models = $registry->listModels();
        self::assertTrue($models[0]['configured']);
        self::assertFalse($models[0]['account_access_verified']);
        self::assertSame('zai:glm-5.3-flash', $models[0]['id']);
        self::assertStringNotContainsString('dummy-value', json_encode($models));
        $chatService = $this->chatUsing($registry);
        $chat = $chatService->createChat(new CreateChatInput(1, 'Fixture', $models[0]['id']));
        $chatService->addMessage(new AddChatMessageInput($chat->id, 1, 'Fixture image', [self::PNG]));
        $chatService->addMessage(new AddChatMessageInput($chat->id, 1, 'Fixture follow-up'));
        $request = $this->history[1]['request'];
        self::assertSame('https://api.z.ai/api/paas/v4/chat/completions', (string)$request->getUri());
        $body = json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('glm-5.3-flash', $body['model']);
        self::assertCount(3, $body['messages']);
        self::assertSame(self::PNG, $body['messages'][0]['content'][1]['image_url']['url']);
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM ai_providers')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM ai_models')->fetchColumn());
    }

    public function testUnknownEnvironmentModelIsRejectedBeforeCreatingConversation(): void
    {
        try {
            $this->chatUsing($this->environmentRegistry())->createChat(new CreateChatInput(1, 'Fixture', 'zai:glm-invented'));
            self::fail('Unknown model must be rejected');
        } catch (AiConnectionException $error) {
            self::assertSame('model_unavailable', $error->reason);
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM chats')->fetchColumn());
            self::assertSame([], $this->history);
        }
    }

    public function testEnvironmentKeyRemovalDoesNotSendHistoryToDatabaseProviderOrOvh(): void
    {
        $chat = $this->chatUsing($this->environmentRegistry())->createChat(new CreateChatInput(1, 'Fixture', 'zai:glm-5.3-flash'));
        try {
            $this->chatUsing($this->environmentRegistry(false))->addMessage(new AddChatMessageInput($chat->id, 1, 'Fixture'));
            self::fail('Missing environment configuration must be rejected');
        } catch (AiConnectionException $error) {
            self::assertSame('missing_configuration', $error->reason);
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn());
            self::assertSame([], $this->history);
        }
    }

    public function testEnvironmentConfigurationPreservesExistingDatabaseBindings(): void
    {
        $selector = $this->registry->listModels()[0]['id'];
        $registry = $this->environmentRegistry();
        self::assertSame(1, $registry->resolve($selector)->id);
        self::assertSame($this->client, $registry->clientFor($registry->resolve($selector)));
    }

    public function testEnvironmentChatOwnerIsCheckedBeforeProviderAccess(): void
    {
        $service = $this->chatUsing($this->environmentRegistry());
        $chat = $service->createChat(new CreateChatInput(1, 'Fixture', 'zai:glm-5.3-flash'));
        try {
            $service->addMessage(new AddChatMessageInput($chat->id, 2, 'Fixture'));
            self::fail('Another user must not access the conversation');
        } catch (NotFoundException) {
            self::assertSame([], $this->history);
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn());
        }
    }
}
