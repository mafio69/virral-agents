<?php
declare(strict_types=1);
namespace Tests;
use App\Dto\AI\AiReply;
use App\Infrastructure\AI\AnthropicProvider;
use App\Infrastructure\AI\GeminiProvider;
use App\Infrastructure\AI\OpenAiProvider;
use App\Infrastructure\AI\ProviderTransport;
use App\Infrastructure\AI\OpenAiCompatClient;
use App\Infrastructure\AI\WebToolClientInterface;
use App\Service\ResearchReplyGenerator;
use App\Service\ResearchServiceInterface;
use App\Service\ChatReplyGeneratorInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ResearchAgentTest extends TestCase
{
    public function testNativeProviderToolResponsesOnlyProposeOperations(): void
    {
        $fixtures = [
            [OpenAiProvider::class, 'gpt-6-astra', ['output' => [['type' => 'function_call', 'name' => 'web_search', 'arguments' => '{"query":"Public query"}']]]],
            [AnthropicProvider::class, 'claude-sonnet-5-5', ['content' => [['type' => 'tool_use', 'name' => 'web_search', 'input' => ['query' => 'Public query']]]]],
            [GeminiProvider::class, 'gemini-3-flash', ['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'web_search', 'args' => ['query' => 'Public query']]]]]]]]],
        ];
        foreach ($fixtures as [$class, $model, $body]) {
            $http = new Client(['base_uri' => 'https://fixture.example/', 'handler' => HandlerStack::create(new MockHandler([new Response(200, [], json_encode($body))]))]);
            $provider = new $class(new ProviderTransport('https://fixture.example/', 'dummy-fixture', http: $http));
            $reply = $provider->generateWebReply([['role' => 'user', 'content' => 'Fixture question']], $model);
            self::assertSame(['kind' => 'search', 'query' => 'Public query'], $reply->webRequest);
            self::assertSame('', $reply->text);
        }
        $http = new Client(['base_uri' => 'https://fixture.example/', 'handler' => HandlerStack::create(new MockHandler([new Response(200, [], '{"choices":[{"message":{"role":"assistant","tool_calls":[{"function":{"name":"web_fetch","arguments":"{\\"url\\":\\"https://example.org/page\\"}"}}]}}]}')]))]);
        $reply = (new OpenAiCompatClient('https://fixture.example/', 'dummy-fixture', 'fixture', httpClient: $http))->generateWebReply([['role' => 'user', 'content' => 'Fixture']], 'fixture');
        self::assertSame(['kind' => 'fetch', 'url' => 'https://example.org/page'], $reply->webRequest);
    }

    public function testAgentProposalIsPersistedAsPendingNotExecuted(): void
    {
        $research = $this->createMock(ResearchServiceInterface::class);
        $research->method('evidence')->willReturn(['sources' => [], 'decisions' => []]);
        $research->expects(self::once())->method('propose')->with(1, 4, ['kind' => 'search', 'query' => 'Public query'], 'ai')->willReturn(['actions' => [['status' => 'pending']]]);
        $client = $this->createMock(WebToolClientInterface::class);
        $client->method('generateWebReply')->willReturn(new AiReply('', webRequest: ['kind' => 'search', 'query' => 'Public query']));
        $reply = (new ResearchReplyGenerator($research, $this->createMock(ChatReplyGeneratorInterface::class)))->generate($client, [['role' => 'user', 'content' => 'Question']], 'fixture', 1, 4);
        self::assertSame('approval_required', $reply->search['status']);
        self::assertStringContainsString('Allow', $reply->text);
    }

    public function testOnlyLoadedEvidenceIsCitedAndDeniedPageIsNeverClaimedAsRead(): void
    {
        $research = $this->createMock(ResearchServiceInterface::class);
        $research->method('evidence')->willReturn(['sources' => [
            ['url' => 'https://example.org/read', 'status' => 'loaded', 'text' => 'Fetched fixture', 'truncated' => true],
            ['url' => 'https://example.org/denied', 'status' => 'denied', 'text' => '', 'truncated' => false],
        ], 'decisions' => []]);
        $client = $this->createMock(WebToolClientInterface::class);
        $client->expects(self::once())->method('generateWebReply')->willReturnCallback(static function (array $messages): AiReply {
            self::assertStringContainsString('Fetched fixture', $messages[2]['content']);
            self::assertStringContainsString('niezaufane', $messages[0]['content']);
            return new AiReply('Fixture synthesis');
        });
        $reply = (new ResearchReplyGenerator($research, $this->createMock(ChatReplyGeneratorInterface::class)))->generate($client, [['role' => 'user', 'content' => 'Question']], 'fixture', 1, 1);
        self::assertStringContainsString('https://example.org/read', $reply->text);
        self::assertStringNotContainsString('https://example.org/denied', $reply->text);
        self::assertStringContainsString('skrócona', $reply->text);
    }
}
