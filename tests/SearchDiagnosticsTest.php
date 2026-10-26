<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\Search\McpSearchClient;
use App\Infrastructure\Search\SearchClientInterface;
use App\Infrastructure\Search\SearchException;
use App\Service\ChatReplyGenerator;
use App\Service\SearchDiagnostics;
use App\Dto\Chat\SearchOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SearchDiagnosticsTest extends TestCase
{
    public function testUnavailableReplyPreservesSafeReasonAndStageAndLogsNoQuery(): void
    {
        $exception = new SearchException('dns_failed', 'initialize');
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::once())->method('search')->willThrowException($exception);
        $search->expects(self::once())->method('close');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('MCP search failed', [
            'reason' => 'dns_failed',
            'stage' => 'initialize',
            'model' => 'fixture',
            'origin' => basename($exception->getFile()).':'.$exception->getLine(),
        ]);
        $model = $this->createMock(AiClientInterface::class);
        $model->expects(self::never())->method('generate');
        $reply = (new ChatReplyGenerator($search, $logger))->generate($model, [], 'fixture', new SearchOptions('mcp', 1, 'Public fixture query', true));
        self::assertSame(['status' => 'unavailable', 'reason' => 'dns_failed', 'stage' => 'initialize'], $reply->search);
        self::assertStringContainsString('Przepraszamy, wyszukiwanie WWW jest chwilowo niedostępne', $reply->text);
        self::assertStringNotContainsString('MCP_AUTH_TOKEN', $reply->text);
        self::assertStringNotContainsString('serwera MCP', $reply->text);
        self::assertStringNotContainsString('Public fixture query', $reply->text);
    }

    public function testMissingSearchConfigurationReturnsConfigurationSpecificMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'MCP search failed',
            self::callback(static fn (array $context): bool => $context['reason'] === 'missing_configuration' && $context['stage'] === 'definition')
        );
        $model = $this->createMock(AiClientInterface::class);
        $model->expects(self::never())->method('generate');
        $reply = (new ChatReplyGenerator(null, $logger))->generate($model, [], 'fixture', new SearchOptions('mcp', 1, 'Public fixture query', true));
        self::assertSame(['status' => 'unavailable', 'reason' => 'missing_configuration', 'stage' => 'definition'], $reply->search);
        self::assertSame(
            'Wyszukiwanie WWW nie jest skonfigurowane w tej instalacji. Nie uzyskano źródeł i nie przygotowano odpowiedzi na ich podstawie.',
            $reply->text
        );
    }

    public function testStreamTransportDnsFailureIsClassifiedWithoutExposingTransportText(): void
    {
        $failure = new ConnectException('php_network_getaddresses: getaddrinfo failed; fixture-private-diagnostic', new Request('POST', 'http://mcp.example/mcp'));
        $client = new McpSearchClient('http://mcp.example/mcp', new Client(['handler' => HandlerStack::create(new MockHandler([$failure]))]));
        try {
            $client->definition();
            self::fail('Expected DNS error');
        } catch (SearchException $error) {
            self::assertSame('dns_failed', $error->reason);
            self::assertSame('initialize', $error->stage);
            self::assertStringNotContainsString('fixture-private-diagnostic', $error->getMessage());
        }
    }

    public function testHttpErrorIsAttributedToToolsList(): void
    {
        $handler = HandlerStack::create(new MockHandler([
            McpSearchClientTest::rpc(1, ['protocolVersion' => '2025-03-26']), new Response(202), new Response(503),
        ]));
        $client = new McpSearchClient('http://mcp.example/mcp', new Client(['handler' => $handler]));
        try {
            $client->definition();
            self::fail('Expected HTTP error');
        } catch (SearchException $error) {
            self::assertSame('http_error', $error->reason);
            self::assertSame('tools_list', $error->stage);
        }
    }

    public function testHealthProbeNeverSearchesAndDoesNotExposeConfiguredUrl(): void
    {
        $client = $this->createMock(SearchClientInterface::class);
        $client->expects(self::never())->method('search');
        $client->expects(self::once())->method('definition')->willReturn(['function' => ['name' => 'web_search']]);
        $client->expects(self::once())->method('close');
        self::assertSame(
            ['available' => true, 'target' => 'custom', 'tool' => 'web_search', 'searched' => false],
            (new SearchDiagnostics($client, 'https://fixture.example/mcp'))->status()
        );
    }

    public function testUnresolvedDockerNameIsReportedWithoutConnecting(): void
    {
        $client = $this->createMock(SearchClientInterface::class);
        $client->expects(self::never())->method('definition');
        $client->expects(self::never())->method('search');
        self::assertSame(
            ['available' => false, 'target' => 'docker-service', 'reason' => 'dns_failed', 'stage' => 'initialize', 'searched' => false],
            (new SearchDiagnostics($client, 'http://mcp-search:8000/mcp', static fn () => false))->status()
        );
    }

    public function testArbitraryDiagnosticValuesAreNotReturned(): void
    {
        $error = new SearchException('fixture-sensitive-value', 'fixture-sensitive-stage');
        self::assertSame('unavailable', $error->reason);
        self::assertSame('unknown', $error->stage);
    }

    public function testHealthEndpointRequiresAuthentication(): void
    {
        $diagnostics = $this->createMock(\App\Service\SearchDiagnosticsInterface::class);
        $diagnostics->expects(self::once())->method('status')->willReturn(['available' => false, 'reason' => 'dns_failed', 'searched' => false]);
        $auth = $this->createMock(\App\Service\AuthService::class);
        $auth->method('validateToken')->with('fixture-session')->willReturn(1);
        $controller = new \App\Controller\ChatController(
            new \App\Mapper\Chat\CreateChatInputMapper(),
            new \App\Mapper\Chat\AddChatMessageInputMapper(),
            $this->createMock(\App\Service\ChatService::class),
            $this->createMock(\App\Service\ModelCatalogService::class),
            $diagnostics
        );
        $container = new \DI\Container([
            \App\Controller\ChatController::class => $controller,
            \App\Service\SessionAuthenticatorInterface::class => $auth,
            LoggerInterface::class => new \Psr\Log\NullLogger(), 'settings' => ['displayErrorDetails' => false],
        ]);
        $app = \Slim\Factory\AppFactory::create(container: $container);
        (require __DIR__.'/../src/routes.php')($app);
        (require __DIR__.'/../src/middleware.php')($app);
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', 'http://fixture.example/api/search/status');
        self::assertSame(401, $app->handle($request)->getStatusCode());
        $response = $app->handle($request->withCookieParams(['token' => 'fixture-session']));
        self::assertSame(200, $response->getStatusCode());
        self::assertFalse(json_decode((string)$response->getBody(), true)['searched']);
    }
}
