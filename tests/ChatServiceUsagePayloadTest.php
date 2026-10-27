<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\AI\AiReply;
use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\Database\MigrationRunner;
use App\Repository\ChatRepository;
use App\Repository\ChatUsageRepository;
use App\Service\ChatReplyGeneratorInterface;
use App\Service\ChatService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ChatServiceUsagePayloadTest extends TestCase
{
    private \PDO $pdo;
    private ChatUsageRepository $usageRepository;
    private int $userId;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        (new MigrationRunner($this->pdo))->run();

        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['test@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $this->userId = (int) $this->pdo->lastInsertId();

        $this->usageRepository = new ChatUsageRepository($this->pdo);
    }

    public function testAssistantPayloadIncludesUsageWhenAvailable(): void
    {
        $replyGenerator = new class () implements ChatReplyGeneratorInterface {
            public function generate(AiClientInterface $client, array $messages, string $model, \App\Dto\Chat\SearchOptions $search): AiReply
            {
                return new AiReply('Odpowiedź z tokenami', usage: ['prompt_tokens' => 120, 'completion_tokens' => 45]);
            }
        };

        $chatService = $this->createChatService($replyGenerator);
        $chat = $chatService->createChat(new CreateChatInput($this->userId, 'Czat payload usage', 'gemini-2.5-flash'));

        $result = $chatService->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie'));

        $this->assertCount(2, $result);
        $assistantMsg = $result[1];
        $this->assertSame('assistant', $assistantMsg['role']);
        $this->assertArrayHasKey('usage', $assistantMsg);
        $this->assertSame(['prompt_tokens' => 120, 'completion_tokens' => 45], $assistantMsg['usage']);
    }

    public function testAssistantPayloadHasNullUsageWhenReplyHasNoUsage(): void
    {
        $replyGenerator = new class () implements ChatReplyGeneratorInterface {
            public function generate(AiClientInterface $client, array $messages, string $model, \App\Dto\Chat\SearchOptions $search): AiReply
            {
                return new AiReply('Odpowiedź bez tokenów', usage: null);
            }
        };

        $chatService = $this->createChatService($replyGenerator);
        $chat = $chatService->createChat(new CreateChatInput($this->userId, 'Czat bez usage', 'gemini-2.5-flash'));

        $result = $chatService->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie'));

        $assistantMsg = $result[1];
        $this->assertArrayHasKey('usage', $assistantMsg);
        $this->assertNull($assistantMsg['usage']);
    }

    public function testGetChatWithMessagesAttachesUsageToAssistantMessages(): void
    {
        $replyGenerator = new class () implements ChatReplyGeneratorInterface {
            public function generate(AiClientInterface $client, array $messages, string $model, \App\Dto\Chat\SearchOptions $search): AiReply
            {
                return new AiReply('Nowa odpowiedź', usage: ['prompt_tokens' => 200, 'completion_tokens' => 50]);
            }
        };

        $chatService = $this->createChatService($replyGenerator);
        $chat = $chatService->createChat(new CreateChatInput($this->userId, 'Historia z usage', 'gemini-2.5-flash'));

        // 1. Dodaj starą wiadomość assistant bezpośrednio do bazy (bez wpisu w chat_usage)
        $chatRepository = new ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $oldUserMsgId = $chatRepository->addMessage($chat->id, 'user', 'Stare pytanie');
        $oldAiMsgId = $chatRepository->addMessage($chat->id, 'assistant', 'Stara odpowiedź bez usage');

        // 2. Dodaj nową parę wiadomości przez addMessage (będzie miała wiersz w chat_usage)
        $result = $chatService->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Nowe pytanie'));
        $newAiMsgId = $result[1]['id'];

        // Pobierz czat z wiadomościami
        $chatWithMessages = $chatService->getChatWithMessages($chat->id, $this->userId);
        $messages = $chatWithMessages->messages;

        $this->assertCount(4, $messages);

        // Stara wiadomość assistant powinna mieć usage === null
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertArrayHasKey('usage', $messages[1]);
        $this->assertNull($messages[1]['usage']);

        // Nowa wiadomość assistant powinna mieć usage z chat_usage
        $this->assertSame('assistant', $messages[3]['role']);
        $this->assertArrayHasKey('usage', $messages[3]);
        $this->assertSame(['prompt_tokens' => 200, 'completion_tokens' => 50], $messages[3]['usage']);
    }

    private function createChatService(?ChatReplyGeneratorInterface $replyGenerator = null): ChatService
    {
        $chatRepository = new ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $ovhAiClient = $this->createMock(\App\Infrastructure\AI\OvhAiClient::class);
        $ovhAiClient->method('generate')->willReturn('AI response');
        $ovhAiClient->method('getBaseUrl')->willReturn('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/');
        $logger = $this->createMock(LoggerInterface::class);

        return ChatServiceFixture::create(
            $chatRepository,
            $ovhAiClient,
            $logger,
            replyGenerator: $replyGenerator,
            chatUsageRepository: $this->usageRepository,
        );
    }
}
