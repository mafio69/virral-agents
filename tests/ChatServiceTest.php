<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Exception\NotFoundException;
use App\Service\ChatService;
use PHPUnit\Framework\TestCase;

class ChatServiceTest extends TestCase
{
    private ChatService $chatService;
    private \PDO $pdo;
    private int $userId;

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
            CREATE TABLE chat_message_images (
                message_id INTEGER PRIMARY KEY,
                data_url TEXT NOT NULL,
                FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
            );
        ');

        // Create a test user
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['test@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $this->userId = (int)$this->pdo->lastInsertId();

        // Create service dependencies
        $chatRepository = new \App\Repository\ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        
        // Mock OVH AI client
        $ovhAiClient = $this->createMock(\App\Infrastructure\AI\OvhAiClient::class);
        $ovhAiClient->method('generate')->willReturn('AI response');
        $ovhAiClient->method('getBaseUrl')->willReturn('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/');
        
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->chatService = ChatServiceFixture::create($chatRepository, $ovhAiClient, $logger);
    }

    public function testCreateChat(): void
    {
        $input = new CreateChatInput(
            userId: $this->userId,
            title: 'Test Chat',
            model: 'Qwen3-Coder-30B-A3B-Instruct'
        );

        $result = $this->chatService->createChat($input);

        $this->assertIsInt($result->id);
        $this->assertEquals('Test Chat', $result->title);
        $this->assertEquals('Qwen3.6-27B', $result->model);
    }

    public function testCreateChatWithDefaultModel(): void
    {
        $input = new CreateChatInput(
            userId: $this->userId,
            title: 'Test Chat',
            model: null
        );

        $result = $this->chatService->createChat($input);

        $this->assertIsInt($result->id);
        $this->assertEquals('Test Chat', $result->title);
        $this->assertSame('Qwen3.6-27B', $result->model);
    }

    public function testGetUserChats(): void
    {
        // Create multiple chats
        $this->chatService->createChat(new CreateChatInput($this->userId, 'Chat 1', 'Model1'));
        $this->chatService->createChat(new CreateChatInput($this->userId, 'Chat 2', 'Model2'));
        $this->chatService->createChat(new CreateChatInput($this->userId, 'Chat 3', null));

        $chats = $this->chatService->getUserChats($this->userId);

        $this->assertCount(3, $chats);
        $this->assertEquals('Chat 1', $chats[0]->title);
        $this->assertEquals('Model1', $chats[0]->model);
        $this->assertEquals('Chat 2', $chats[1]->title);
        $this->assertEquals('Model2', $chats[1]->model);
    }

    public function testGetChatWithMessages(): void
    {
        $chat = $this->chatService->createChat(new CreateChatInput($this->userId, 'Test Chat', 'TestModel'));
        
        // Add a message
        $messageInput = new AddChatMessageInput($chat->id, $this->userId, 'Hello');
        $this->chatService->addMessage($messageInput);

        $result = $this->chatService->getChatWithMessages($chat->id, $this->userId);

        $this->assertEquals($chat->id, $result->id);
        $this->assertEquals('Test Chat', $result->title);
        $this->assertEquals('TestModel', $result->model);
        $this->assertIsArray($result->messages);
        $this->assertCount(2, $result->messages); // User message + AI response
    }

    public function testGetChatNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Chat not found');
        
        $this->chatService->getChatWithMessages(999, $this->userId);
    }

    public function testGetChatWrongUser(): void
    {
        // Create another user
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['other@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $otherUserId = (int)$this->pdo->lastInsertId();

        // Create chat for first user
        $chat = $this->chatService->createChat(new CreateChatInput($this->userId, 'Test Chat', 'TestModel'));

        // Try to get chat with different user
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Chat not found');
        
        $this->chatService->getChatWithMessages($chat->id, $otherUserId);
    }

    public function testAddMessage(): void
    {
        $chat = $this->chatService->createChat(new CreateChatInput($this->userId, 'Test Chat', 'TestModel'));
        
        $messageInput = new AddChatMessageInput($chat->id, $this->userId, 'Hello AI');
        $messages = $this->chatService->addMessage($messageInput);

        $this->assertCount(2, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Hello AI', $messages[0]['content']);
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('AI response', $messages[1]['content']);
    }

    public function testAddMessageChatNotFound(): void
    {
        $messageInput = new AddChatMessageInput(999, $this->userId, 'Hello');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Chat not found');
        
        $this->chatService->addMessage($messageInput);
    }

    public function testAddMessageWrongUser(): void
    {
        // Create another user
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['other@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $otherUserId = (int)$this->pdo->lastInsertId();

        // Create chat for first user
        $chat = $this->chatService->createChat(new CreateChatInput($this->userId, 'Test Chat', 'TestModel'));

        // Try to add message with different user
        $messageInput = new AddChatMessageInput($chat->id, $otherUserId, 'Hello');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Chat not found');
        
        $this->chatService->addMessage($messageInput);
    }
    public function testImagesAreSavedAndRemainInModelHistory(): void
    {
        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXfoAAAAASUVORK5CYII=';
        $client = $this->createMock(\App\Infrastructure\AI\OvhAiClient::class);
        $client->expects($this->exactly(2))->method('generate')->willReturnCallback(
            function (array $messages, ?string $model) use ($image): string {
                $this->assertSame('Qwen3.6-27B', $model);
                $this->assertSame('image_url', $messages[0]['content'][1]['type']);
                $this->assertSame($image, $messages[0]['content'][1]['image_url']['url']);
                return 'Analiza testowa';
            }
        );
        $service = ChatServiceFixture::create(new \App\Repository\ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo)), $client, new \Psr\Log\NullLogger());
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Obraz', 'Qwen3.6-27B'));
        $pair = $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, '', [$image]));
        $this->assertSame([$image], $pair[0]['images']);
        $this->assertSame('', $pair[0]['content']);
        $saved = $service->getChatWithMessages($chat->id, $this->userId);
        $this->assertSame([$image], $saved->messages[0]['images']);
        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Co jeszcze widzisz?'));
    }

    public function testUnsupportedModelRejectsImageBeforeSavingMessage(): void
    {
        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXfoAAAAASUVORK5CYII=';
        $chat = $this->chatService->createChat(new CreateChatInput($this->userId, 'Tekst', 'gpt-oss-20b'));
        try {
            $this->chatService->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Obraz', [$image]));
            $this->fail('Expected validation failure');
        } catch (\App\Exception\ValidationException) {
            $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn());
        }
    }

}