<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Dto\Chat\SearchOptions;
use App\Exception\ValidationException;
use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\Search\SearchClientInterface;
use App\Infrastructure\Search\SearchException;
use App\Service\ChatReplyGenerator;
use App\Service\ChatService;
use Psr\Log\NullLogger;

final class ChatSearchTest extends ChatServiceTestCase
{
    public function testSearchRequiresExplicitApprovalForExactQueryAndOneCall(): void
    {
        foreach ([['engine' => 'mcp'], ['engine' => 'mcp', 'query' => 'Public'], ['engine' => 'mcp', 'query' => 'Public', 'approved' => 'true'],
            ['engine' => 'mcp', 'query' => '', 'approved' => true], ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'max_calls' => 2],
            ['engine' => 'google'], ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'fetch' => true]] as $input) {
            try {
                SearchOptions::fromInput($input);
                self::fail('Missing approval or unsupported operation must be rejected');
            } catch (ValidationException) { self::assertTrue(true); }
        }
        self::assertSame('off', SearchOptions::fromInput([])->engine);
    }

    public function testNormalChatDoesNotConnectToMcp(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::never())->method('search');
        $search->expects(self::never())->method('definition');
        $client = $this->createMock(AiClientInterface::class);
        $messages = [['role' => 'user', 'content' => 'Hello']];
        $client->expects(self::once())->method('generate')->with($messages, 'fixture')->willReturn('Hello back');
        self::assertSame('Hello back', (new ChatReplyGenerator($search))->generate($client, $messages, 'fixture', new SearchOptions())->text);
    }

    public function testSourcesAreSavedEncryptedAndApprovalIsNotReused(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::once())->method('search')->with('Public query', 5)->willReturn(['source' => 'searxng', 'results' => [
            ['title' => 'Fixture source', 'url' => 'https://example.org/source', 'snippet' => 'Untrusted fixture snippet'],
        ]]);
        $search->expects(self::once())->method('close');
        $client = $this->createMock(OvhAiClient::class);
        $count = 0;
        $client->expects(self::exactly(2))->method('generate')->willReturnCallback(function (array $messages) use (&$count): string {
            if (++$count === 1) {
                self::assertSame('system', $messages[0]['role']);
                self::assertStringContainsString('niezaufanymi', $messages[0]['content']);
                self::assertStringContainsString('https://example.org/source', $messages[2]['content']);
                self::assertStringNotContainsString('Private chat fixture', $messages[2]['content']);
            } else {
                self::assertSame('user', $messages[0]['role']);
                self::assertSame('Follow-up', $messages[2]['content']);
            }
            return 'Fixture answer';
        });
        $service = new ChatService($this->chatRepository, $client, new NullLogger(), replyGenerator: new ChatReplyGenerator($search));
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $pair = $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Private chat fixture', search: new SearchOptions('mcp', 1, 'Public query', true)));
        self::assertStringContainsString('https://example.org/source', $pair[1]['content']);
        self::assertSame('ok', $pair[1]['search']['status']);
        self::assertSame($pair[1]['content'], $service->getChatWithMessages($chat->id, $this->userId)->messages[1]['content']);
        self::assertStringNotContainsString('https://example.org/source', $this->pdo->query("SELECT content FROM chat_messages WHERE role = 'assistant'")->fetchColumn());
        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Follow-up'));
    }

    public function testEmptyAndUnavailableSearchDoNotProduceInventedAnswers(): void
    {
        foreach (['empty', 'unavailable'] as $status) {
            $search = $this->createMock(SearchClientInterface::class);
            $expect = $search->expects(self::once())->method('search');
            if ($status === 'empty') $expect->willReturn(['source' => 'perplexity-fast', 'results' => []]);
            else $expect->willThrowException(new SearchException());
            $search->expects(self::once())->method('close');
            $client = $this->createMock(AiClientInterface::class);
            $client->expects(self::never())->method('generate');
            $reply = (new ChatReplyGenerator($search))->generate($client, [], 'fixture', new SearchOptions('mcp', 1, 'Public', true));
            self::assertSame($status, $reply->search['status']);
            self::assertStringContainsString('zgody', $reply->text);
        }
    }

    public function testAnotherUserCannotTriggerSearch(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::never())->method('search');
        $service = new ChatService($this->chatRepository, $this->createMock(OvhAiClient::class), new NullLogger(), replyGenerator: new ChatReplyGenerator($search));
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $this->expectException(\App\Exception\NotFoundException::class);
        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId + 1, 'Private', search: new SearchOptions('mcp', 1, 'Public', true)));
    }
}
