<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\AiConnectionException;
use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Infrastructure\AI\AnthropicProvider;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\GeminiProvider;
use App\Infrastructure\AI\OpenAiProvider;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\AI\ProviderRegistry;
use App\Infrastructure\AI\ProviderTransport;
use App\Infrastructure\Database\MigrationRunner;
use App\Repository\ChatRepository;
use App\Service\ChatService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProviderRoutingTest extends TestCase
{
    public function testEachProviderReceivesOnlyItsOwnConversationAndKeepsItsNativeEndpoint(): void
    {
        $histories = ['openai' => [], 'anthropic' => [], 'google' => []];
        $catalogues = [
            'openai' => ['data' => [['id' => 'gpt-6-astra']]],
            'anthropic' => ['data' => [['id' => 'claude-sonnet-5-5']], 'has_more' => false],
            'google' => ['models' => [['name' => 'models/gemini-3-flash', 'supportedGenerationMethods' => ['generateContent']]]],
        ];
        $answers = [
            'openai' => ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'OpenAI fixture answer']]]]],
            'anthropic' => ['content' => [['type' => 'text', 'text' => 'Anthropic fixture answer']]],
            'google' => ['candidates' => [['content' => ['parts' => [['text' => 'Google fixture answer']]]]]],
        ];
        $bases = ['openai' => 'https://api.openai.com/v1/', 'anthropic' => 'https://api.anthropic.com/v1/', 'google' => 'https://generativelanguage.googleapis.com/v1beta/'];
        $classes = ['openai' => OpenAiProvider::class, 'anthropic' => AnthropicProvider::class, 'google' => GeminiProvider::class];
        $providers = [];
        foreach ($classes as $id => $class) {
            $response = static fn(array $body): Response => new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR));
            $handler = HandlerStack::create(new MockHandler([$response($catalogues[$id]), $response($answers[$id]), $response($answers[$id])]));
            $handler->push(Middleware::history($histories[$id]));
            $providers[$id] = new $class(new ProviderTransport($bases[$id], 'fixture-'.$id,
                http: new Client(['base_uri' => $bases[$id], 'handler' => $handler])));
        }
        $registry = new ProviderRegistry($this->createMock(ChatModelRegistryInterface::class), $providers);
        $pdo = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        (new MigrationRunner($pdo))->run();
        $service = ChatServiceFixture::create(new ChatRepository($pdo, ProtectedDataFixture::cipher($pdo)),
            new OvhAiClient('https://example.invalid/v1', '', 'Qwen3.6-27B'), new NullLogger(), $registry);
        $chats = [];
        foreach (array_keys($providers) as $id) {
            $selector = (new \App\Service\ModelCatalogService(new OvhAiClient('https://example.invalid/', '', 'fixture'), $registry, new NullLogger()))->getAvailableModels($id)[0]['id'];
            $chats[$id] = $service->createChat(new CreateChatInput(1, 'Fixture '.$id, $selector));
            $service->addMessage(new AddChatMessageInput($chats[$id]->id, 1, 'Private fixture '.$id));
        }
        $paths = ['openai' => '/v1/responses', 'anthropic' => '/v1/messages', 'google' => '/v1beta/models/gemini-3-flash:generateContent'];
        foreach ($chats as $id => $chat) {
            $service->addMessage(new AddChatMessageInput($chat->id, 1, 'Follow-up '.$id));
            self::assertCount(3, $histories[$id]);
            $request = $histories[$id][2]['request'];
            self::assertSame($paths[$id], $request->getUri()->getPath());
            self::assertSame(parse_url($bases[$id], PHP_URL_HOST), $request->getUri()->getHost());
            $body = (string)$request->getBody();
            self::assertStringContainsString('Private fixture '.$id, $body);
            foreach (array_diff(array_keys($providers), [$id]) as $other) {
                self::assertStringNotContainsString('Private fixture '.$other, $body);
                self::assertStringNotContainsString('fixture-'.$other, $request->getHeaderLine('Authorization'));
            }
        }
        self::assertSame(['ovh', 'zai', 'openai', 'anthropic', 'google'], array_column((new \App\Service\ModelCatalogService(new OvhAiClient('https://example.invalid/', 'fixture-ovh', 'fixture'), $registry, new NullLogger()))->getConnections(), 'id'));
        self::assertSame(['zai', 'openai', 'anthropic', 'google'], array_column((new \App\Service\ModelCatalogService(new OvhAiClient('https://example.invalid/', '', 'fixture'), $registry, new NullLogger()))->getConnections(), 'id'));
    }

    public function testEveryUnconfiguredProviderFailsBeforeUsingNetwork(): void
    {
        foreach ([OpenAiProvider::class, AnthropicProvider::class, GeminiProvider::class] as $class) {
            $provider = new $class(new ProviderTransport('https://example.invalid/', '', http: new Client(['handler' => HandlerStack::create(new MockHandler([]))])));
            try {
                $provider->listModels();
                self::fail('Missing key must be rejected');
            } catch (AiConnectionException $error) {
                self::assertSame('missing_configuration', $error->reason);
            }
        }
    }

    public function testApprovedMcpResultsReachAllThreeNativeProviders(): void
    {
        $fixtures = [
            [OpenAiProvider::class, 'gpt-6-astra', ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Fixture answer']]]]], 'input'],
            [AnthropicProvider::class, 'claude-sonnet-5-5', ['content' => [['type' => 'text', 'text' => 'Fixture answer']]], 'messages'],
            [GeminiProvider::class, 'gemini-3-flash', ['candidates' => [['content' => ['parts' => [['text' => 'Fixture answer']]]]]], 'contents'],
        ];
        foreach ($fixtures as [$class, $model, $answer, $historyKey]) {
            $requests = [];
            $handler = HandlerStack::create(new MockHandler([new Response(200, [], json_encode($answer))]));
            $handler->push(Middleware::history($requests));
            $provider = new $class(new ProviderTransport('https://example.invalid/', 'dummy-fixture', http: new Client(['base_uri' => 'https://example.invalid/', 'handler' => $handler])));
            $search = $this->createMock(\App\Infrastructure\Search\SearchClientInterface::class);
            $search->expects(self::once())->method('search')->with('Public query', 5)->willReturn(['source' => 'searxng', 'results' => [
                ['title' => 'Fixture', 'url' => 'https://example.org/source', 'snippet' => 'Public fixture'],
            ]]);
            $search->expects(self::once())->method('close');
            $reply = (new \App\Service\ChatReplyGenerator($search))->generate($provider, [['role' => 'user', 'content' => 'Private fixture question']], $model,
                new \App\Dto\Chat\SearchOptions('mcp', 1, 'Public query', true));
            self::assertStringContainsString('https://example.org/source', $reply->text);
            $payload = json_decode((string)$requests[0]['request']->getBody(), true);
            self::assertStringContainsString('https://example.org/source', json_encode($payload[$historyKey], JSON_UNESCAPED_SLASHES));
            self::assertStringContainsString('Private fixture question', json_encode($payload[$historyKey]));
            self::assertArrayNotHasKey('tools', $payload);
        }
    }
}
