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

final class ChatServiceUsageTest extends TestCase
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

    public function testAddMessageWithUsageSavesUsageRow(): void
    {
        $replyGenerator = new class () implements ChatReplyGeneratorInterface {
            public function generate(AiClientInterface $client, array $messages, string $model, \App\Dto\Chat\SearchOptions $search): AiReply
            {
                return new AiReply('Odpowiedź z tokenami', usage: ['prompt_tokens' => 120, 'completion_tokens' => 45]);
            }
        };

        $chatService = $this->createChatService($replyGenerator);
        $chat = $chatService->createChat(new CreateChatInput($this->userId, 'Czat z tokenami', 'gemini-2.5-flash'));

        $result = $chatService->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie'));

        $this->assertCount(2, $result);
        $aiMessageId = $result[1]['id'];

        $usage = $this->usageRepository->findByMessageId($aiMessageId);
        $this->assertNotNull($usage, 'Wiersz chat_usage powinien zostać zapisany');
        $this->assertSame($aiMessageId, $usage['message_id']);
        $this->assertSame(120, $usage['prompt_tokens']);
        $this->assertSame(45, $usage['completion_tokens']);
    }

    public function testAddMessageWithoutUsageDoesNotThrowAndDoesNotSaveUsageRow(): void
    {
        $replyGenerator = new class () implements ChatReplyGeneratorInterface {
            public function generate(AiClientInterface $client, array $messages, string $model, \App\Dto\Chat\SearchOptions $search): AiReply
            {
                return new AiReply('Odpowiedź bez tokenów', usage: null);
            }
        };

        $chatService = $this->createChatService($replyGenerator);
        $chat = $chatService->createChat(new CreateChatInput($this->userId, 'Czat bez tokenów', 'gemini-2.5-flash'));

        $result = $chatService->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie'));

        $this->assertCount(2, $result);
        $aiMessageId = $result[1]['id'];

        $usage = $this->usageRepository->findByMessageId($aiMessageId);
        $this->assertNull($usage, 'Wiersz chat_usage nie powinien istnieć, gdy usage jest null');
    }

    private function createChatService(ChatReplyGeneratorInterface $replyGenerator): ChatService
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
