<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ChatController;
use App\Dto\AI\AiModel;
use App\Exception\AiConnectionException;
use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\OpenAiProvider;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\AI\ProviderRegistry;
use App\Infrastructure\AI\ProviderTransport;
use App\Service\ModelCatalogService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ChatControllerErrorTest extends TestCase
{
    public function testModelListRejectsInvalidAndUnknownConnectionsAsValidationErrors(): void
    {
        foreach (['/api/ai-models?connection[]=openai', '/api/ai-models?connection=unknown-fixture'] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame(['error' => 'Nieznane połączenie AI.'], $this->data($response));
        }
    }

    public function testKnownConnectionWithoutKeyReportsMissingConfiguration(): void
    {
        foreach (['openai', 'ovh'] as $connection) {
            $response = $this->request('GET', '/api/ai-models?connection='.$connection);
            self::assertSame(503, $response->getStatusCode());
            self::assertSame('missing_configuration', $response->getHeaderLine('X-AI-Error'));
        }
        self::assertSame([['id' => 'zai', 'name' => 'Z.ai / GLM']], $this->data($this->request('GET', '/api/ai-connections')));
    }

    public function testResearchWithoutConfiguredServiceIsUnavailableNotInvalid(): void
    {
        $response = $this->request('GET', '/api/research?chat_id=1', 'fixture-session');
        self::assertSame(503, $response->getStatusCode());
        self::assertSame(['error' => 'Badanie źródeł jest niedostępne.'], $this->data($response));
    }

    public function testUnexpectedExceptionReturnsFriendlyApologyWithoutDebugDetailsAndLogsContext(): void
    {
        $chatService = $this->createMock(\App\Service\ChatService::class);
        $failure = new \RuntimeException('Sensitive SQL or internal debug trace');
        $chatService->method('getUserChats')->willThrowException($failure);

        $logged = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->willReturnCallback(
            static function (string $message, array $context) use (&$logged): void {
                $logged = ['message' => $message, 'context' => $context];
            }
        );

        $auth = $this->createMock(\App\Service\AuthService::class);
        $auth->method('validateToken')->willReturn(7);
        $controller = new ChatController(
            new \App\Mapper\Chat\CreateChatInputMapper(),
            new \App\Mapper\Chat\AddChatMessageInputMapper(),
            $chatService,
            $this->createMock(ModelCatalogService::class)
        );
        $app = AppFactory::create(container: new \DI\Container([
            ChatController::class => $controller,
            \App\Service\SessionAuthenticatorInterface::class => $auth,
            LoggerInterface::class => $logger,
            'settings' => ['displayErrorDetails' => false],
        ]));
        (require __DIR__.'/../src/routes.php')($app);
        (require __DIR__.'/../src/middleware.php')($app);
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://fixture.example/api/chats')
            ->withCookieParams(['token' => 'fixture-session']);

        $response = $app->handle($request);
        $payload = $this->data($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            ['error' => 'Przepraszamy, wystąpił nieoczekiwany problem. Pracujemy nad jego jak najszybszym usunięciem.'],
            $payload
        );
        self::assertStringNotContainsString('Sensitive SQL', (string)$response->getBody());
        self::assertStringNotContainsString('RuntimeException', (string)$response->getBody());
        self::assertSame('Application operation failed', $logged['message']);
        self::assertSame(\RuntimeException::class, $logged['context']['type']);
        self::assertSame(basename($failure->getFile()).':'.$failure->getLine(), $logged['context']['origin']);
        self::assertSame($failure->getLine(), $logged['context']['line']);
        self::assertSame('GET', $logged['context']['method']);
        self::assertSame('/api/chats', $logged['context']['path']);
    }

    private function request(string $method, string $path, string $session = ''): ResponseInterface
    {
        $legacy = new class () implements ChatModelRegistryInterface {
            public function listModels(): array
            {
                return [];
            }
            public function resolve(string $selector): AiModel
            {
                throw new AiConnectionException('model_unavailable');
            }
            public function clientFor(AiModel $model): AiClientInterface
            {
                throw new AiConnectionException('missing_configuration');
            }
        };
        $registry = new ProviderRegistry($legacy, ['openai' => new OpenAiProvider(new ProviderTransport('https://api.openai.com/v1/', ''))]);
        $catalog = new ModelCatalogService(new OvhAiClient('https://example.invalid/', '', 'fixture'), $registry, new NullLogger());
        $auth = $this->createMock(\App\Service\AuthService::class);
        $auth->method('validateToken')->willReturn(1);
        $controller = new ChatController(
            new \App\Mapper\Chat\CreateChatInputMapper(),
            new \App\Mapper\Chat\AddChatMessageInputMapper(),
            $this->createMock(\App\Service\ChatService::class),
            $catalog
        );
        $app = AppFactory::create(container: new \DI\Container([
            ChatController::class => $controller,
            \App\Service\SessionAuthenticatorInterface::class => $auth,
            LoggerInterface::class => new NullLogger(), 'settings' => ['displayErrorDetails' => false],
        ]));
        (require __DIR__.'/../src/routes.php')($app);
        (require __DIR__.'/../src/middleware.php')($app);
        $request = (new ServerRequestFactory())->createServerRequest($method, 'http://fixture.example'.$path);
        if ($session !== '') {
            $request = $request->withCookieParams(['token' => $session]);
        }

        return $app->handle($request);
    }

    private function data(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, 16, JSON_THROW_ON_ERROR);
    }
}
