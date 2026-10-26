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

    public function testAddMessageWithAnthropicTokenKeysSavesCorrectTokens(): void
    {
        $replyGenerator = new class () implements ChatReplyGeneratorInterface {
            public function generate(AiClientInterface $client, array $messages, string $model, \App\Dto\Chat\SearchOptions $search): AiReply
            {
                return new AiReply('Odpowiedź Anthropic', usage: ['input_tokens' => 250, 'output_tokens' => 75]);
            }
        };

        $chatService = $this->createChatService(replyGenerator: $replyGenerator);
        $chat = $chatService->createChat(new CreateChatInput($this->userId, 'Czat Anthropic', 'claude-3-7-sonnet'));

        $result = $chatService->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie do Anthropic'));

        $aiMessageId = $result[1]['id'];
        $usage = $this->usageRepository->findByMessageId($aiMessageId);

        $this->assertNotNull($usage);
        $this->assertSame(250, $usage['prompt_tokens']);
        $this->assertSame(75, $usage['completion_tokens']);
    }

    public function testContinueMessageWithUsageSavesUsageRow(): void
    {
        $research = new class () implements \App\Service\ResearchServiceInterface {
            public function state(int $userId, int $chatId): array
            {
                return [];
            }
            public function propose(int $userId, int $chatId, array $request, string $origin = 'user'): array
            {
                return [];
            }
            public function decide(int $userId, int $chatId, string $actionId, bool $allow): array
            {
                return [];
            }
            public function select(int $userId, int $chatId, string $sourceId, bool $selected): array
            {
                return [];
            }
            public function prepare(int $userId, int $chatId): array
            {
                return [];
            }
            public function clear(int $userId, int $chatId): array
            {
                return [];
            }
            public function evidence(int $userId, int $chatId): array
            {
                return ['sources' => [], 'decisions' => []];
            }
            public function adoptDraft(int $userId, int $chatId): void
            {
            }
        };

        $researchReply = new class () implements \App\Service\ResearchReplyGeneratorInterface {
            public function generate(AiClientInterface $client, array $messages, string $model, int $userId, int $chatId): AiReply
            {
                return new AiReply('Odpowiedź z continue', usage: ['input_tokens' => 310, 'output_tokens' => 85]);
            }
        };

        $chatService = $this->createChatService(research: $research, researchReply: $researchReply);
        $chat = $chatService->createChat(new CreateChatInput($this->userId, 'Czat continue', 'gemini-2.5-flash'));

        // Dodaj wiadomość usera bezpośrednio do bazy
        $chatRepository = new ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $userMessageId = $chatRepository->addMessage($chat->id, 'user', 'Pytanie początkowe');

        $result = $chatService->continueMessage(new \App\Dto\Chat\RetryMessageInput($chat->id, $userMessageId, $this->userId));

        $this->assertIsArray($result);
        $aiMessageId = $result['id'];

        $usage = $this->usageRepository->findByMessageId($aiMessageId);
        $this->assertNotNull($usage, 'continueMessage powinno zapisać wiersz w chat_usage');
        $this->assertSame($aiMessageId, $usage['message_id']);
        $this->assertSame(310, $usage['prompt_tokens']);
        $this->assertSame(85, $usage['completion_tokens']);
    }

    private function createChatService(
        ?ChatReplyGeneratorInterface $replyGenerator = null,
        ?\App\Service\ResearchServiceInterface $research = null,
        ?\App\Service\ResearchReplyGeneratorInterface $researchReply = null,
    ): ChatService {
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
            research: $research,
            researchReply: $researchReply,
            chatUsageRepository: $this->usageRepository,
        );
    }
}
