<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Infrastructure\Database\MigrationRunner;
use App\Repository\ChatUsageRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ChatUsageRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ChatUsageRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        (new MigrationRunner($this->pdo))->run();

        $this->repository = new ChatUsageRepository($this->pdo);
    }

    public function testSaveAndFindByMessageId(): void
    {
        // Utwórz czat i wiadomość
        $this->pdo->exec("INSERT INTO users (email, password_hash, created_at) VALUES ('test@example.com', 'hash', '2026-10-26')");
        $this->pdo->exec("INSERT INTO chats (user_id, title, created_at) VALUES (1, 'Test chat', '2026-10-26')");
        $this->pdo->exec("INSERT INTO chat_messages (chat_id, role, content, created_at) VALUES (1, 'assistant', 'Odpowiedź', '2026-10-26')");
        $messageId = (int) $this->pdo->lastInsertId();

        $this->repository->save($messageId, 'gemini-2.5-flash', 150, 42);

        $usage = $this->repository->findByMessageId($messageId);
        $this->assertNotNull($usage);
        $this->assertSame($messageId, $usage['message_id']);
        $this->assertSame('gemini-2.5-flash', $usage['model']);
        $this->assertSame(150, $usage['prompt_tokens']);
        $this->assertSame(42, $usage['completion_tokens']);
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByMessageId(999999));
    }
}
