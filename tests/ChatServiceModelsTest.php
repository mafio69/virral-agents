<?php

declare(strict_types=1);

namespace Tests;

use App\Service\ChatService;
use PHPUnit\Framework\TestCase;

class ChatServiceModelsTest extends TestCase
{
    private ChatService $chatService;
    private \PDO $pdo;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create tables
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE chats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                created_at TEXT NOT NULL,
                model TEXT DEFAULT NULL
            );
            CREATE TABLE chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
            );
        ');

        // Create service dependencies
        $chatRepository = new \App\Repository\ChatRepository($this->pdo);
        
        $catalogue = ['data' => array_map(static fn(string $id): array => ['id' => $id], [
            'Qwen3.8-27B', 'Qwen3.6-27B', 'Qwen3.5-397B-A17B',
            'bge-m3', 'whisper-large-v3', 'Qwen3Guard-Gen-8B', 'stable-diffusion-xl',
        ])];
        $httpClient = new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([
                new \GuzzleHttp\Psr7\Response(200, [], json_encode($catalogue, JSON_THROW_ON_ERROR)),
            ])),
        ]);
        $ovhAiClient = new \App\Infrastructure\AI\OvhAiClient(
            'https://example.invalid/v1', 'dummy-value', 'Qwen3.8-27B', httpClient: $httpClient,
        );

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->chatService = new ChatService($chatRepository, $ovhAiClient, $logger);
    }

    public function testGetAvailableModels(): void
    {
        $models = $this->chatService->getAvailableModels();
        $this->assertSame(['Qwen3.8-27B', 'Qwen3.6-27B', 'Qwen3.5-397B-A17B'], array_column($models, 'id'));
        $this->assertSame(['code', 'visual', 'visual'], array_column($models, 'category'));
        $this->assertSame([true, true, true], array_column($models, 'supports_images'));
    }

    public function testModelDescriptionMapping(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->chatService);
        $method = $reflection->getMethod('getModelDescription');

        $description = $method->invoke($this->chatService, 'Qwen3-Coder-30B-A3B-Instruct');
        $this->assertEquals('Advanced coding model optimized for code generation and debugging', $description);

        $description = $method->invoke($this->chatService, 'Unknown-Model');
        $this->assertEquals('AI model for various tasks', $description);
    }

    public function testModelCategoryMapping(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->chatService);
        $method = $reflection->getMethod('getModelCategory');

        $this->assertEquals('code', $method->invoke($this->chatService, 'Qwen3-Coder-30B-A3B-Instruct'));
        $this->assertEquals('visual', $method->invoke($this->chatService, 'Qwen2.5-VL-72B-Instruct'));
        $this->assertEquals('visual', $method->invoke($this->chatService, 'Qwen3.6-27B'));
        $this->assertEquals('code', $method->invoke($this->chatService, 'Qwen3.8-27B'));
        $this->assertEquals('audio', $method->invoke($this->chatService, 'whisper-large-v3'));
        $this->assertEquals('guard', $method->invoke($this->chatService, 'Qwen3Guard-Gen-8B'));
        $this->assertEquals('general', $method->invoke($this->chatService, 'Mistral-7B-Instruct-v0.3'));
    }
}