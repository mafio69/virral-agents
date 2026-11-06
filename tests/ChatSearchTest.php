<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Dto\Chat\SearchOptions;
use App\Dto\Chat\SearchQuery;
use App\Exception\ValidationException;
use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\Search\SearchClientInterface;
use App\Infrastructure\Search\SearchException;
use App\Service\ChatReplyGenerator;
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
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
        self::assertSame('off', SearchOptions::fromInput([])->engine);
    }

    public function testSearchOptionsRejectNonObjectInputButAcceptEmptyObject(): void
    {
        foreach (['mcp', null, 1, ['mcp'], [true], [['engine' => 'mcp']]] as $input) {
            try {
                SearchOptions::fromInput($input);
                self::fail('Non-object search options must be rejected: '.json_encode($input));
            } catch (ValidationException $error) {
                self::assertSame('Nieprawidłowe ustawienia wyszukiwania.', $error->getMessage());
            }
        }
        $options = SearchOptions::fromInput([]);
        self::assertSame(['off', 1, '', false], [$options->engine, $options->maxCalls, $options->query->query, $options->approved]);
        self::assertSame('Public', SearchOptions::fromInput(['engine' => 'mcp', 'query' => '  Public ', 'approved' => true])->query->query);
    }

    public function testSearchFiltersAreComposedIntoTheQuerySentToMcp(): void
    {
        $options = SearchOptions::fromInput(['engine' => 'mcp', 'query' => ' Symfony router ', 'approved' => true,
            'must_have' => [' twig '], 'exclude' => ['legacy', ''], 'exact_phrase' => ' konfiguracja  yaml ', 'sites' => ['SymFony.com']]);
        $composed = 'Symfony router +twig -legacy "konfiguracja yaml" site:symfony.com';
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::once())->method('search')->with($composed, 5)->willReturn(['source' => 'searxng', 'results' => [
            ['title' => 'Fixture', 'url' => 'https://symfony.com/doc', 'snippet' => 'Untrusted snippet'],
        ]]);
        $search->expects(self::once())->method('close');
        $client = $this->createMock(AiClientInterface::class);
        $client->expects(self::once())->method('generate')->willReturnCallback(static function (array $messages) use ($composed): string {
            $searchMessage = $messages[count($messages) - 1]['content'];
            $payload = substr($searchMessage, (int) strpos($searchMessage, '{'));
            $data = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
            self::assertSame($composed, $data['query']);
            return 'Fixture answer';
        });
        $reply = (new ChatReplyGenerator($search))->generate($client, [], 'fixture', $options);
        self::assertSame('ok', $reply->search['status']);
    }

    public function testSearchOptionsRejectInvalidFilters(): void
    {
        foreach ([
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'must_have' => ['-legacy']],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'must_have' => ['+legacy']],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'exclude' => ['tr"cker']],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'exclude' => array_fill(0, 11, 'x')],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'exact_phrase' => 'a"b'],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'sites' => ['https://example.com']],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'sites' => ['example.com/path']],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'sites' => array_fill(0, 6, 'example.com')],
            ['engine' => 'mcp', 'query' => 'Public', 'approved' => true, 'must_have' => 'twig'],
            ['exclude' => ['twig']],
            ['engine' => 'mcp', 'query' => str_repeat('a', 1001), 'approved' => true],
        ] as $input) {
            try {
                SearchOptions::fromInput($input);
                self::fail('Invalid filters must be rejected: '.json_encode($input, JSON_UNESCAPED_SLASHES));
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
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
        $service = ChatServiceFixture::create($this->chatRepository, $client, new NullLogger(), replyGenerator: new ChatReplyGenerator($search));
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $pair = $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Private chat fixture', search: new SearchOptions('mcp', 1, new SearchQuery('Public query'), true)));
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
            if ($status === 'empty') {
                $expect->willReturn(['source' => 'perplexity-fast', 'results' => []]);
            } else {
                $expect->willThrowException(new SearchException());
            }
            $search->expects(self::once())->method('close');
            $client = $this->createMock(AiClientInterface::class);
            $client->expects(self::never())->method('generate');
            $reply = (new ChatReplyGenerator($search))->generate($client, [], 'fixture', new SearchOptions('mcp', 1, new SearchQuery('Public'), true));
            self::assertSame($status, $reply->search['status']);
            self::assertStringContainsString('zgody', $reply->text);
        }
    }

    public function testAnotherUserCannotTriggerSearch(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::never())->method('search');
        $service = ChatServiceFixture::create($this->chatRepository, $this->createMock(OvhAiClient::class), new NullLogger(), replyGenerator: new ChatReplyGenerator($search));
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $this->expectException(\App\Exception\NotFoundException::class);
        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId + 1, 'Private', search: new SearchOptions('mcp', 1, new SearchQuery('Public'), true)));
    }
}
