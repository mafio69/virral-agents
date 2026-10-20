<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\AI\AiModel;
use App\Dto\AI\AiReply;
use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\MultimodalClientInterface;
use App\Infrastructure\AI\OvhAiClient;
use Psr\Log\NullLogger;

final class GeneratedImageChatTest extends ChatServiceTestCase
{
    public function testGeneratedImageIsEncryptedRestoredAndNotReplayedWithoutSignatures(): void
    {
        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXfoAAAAASUVORK5CYII=';
        $client = $this->createMock(MultimodalClientInterface::class);
        $calls = [];
        $client->method('generateReply')->willReturnCallback(function (array $history) use (&$calls, $image): AiReply {
            $calls[] = $history;
            return new AiReply('', [$image]);
        });
        $registry = $this->createMock(ChatModelRegistryInterface::class);
        $registry->method('resolve')->willReturn(new AiModel(0, 0, 'gemini-3.1-flash-image', 'Fixture', 0, null, null, 'llm', null, null, null, 'google'));
        $registry->method('clientFor')->willReturn($client);
        $service = ChatServiceFixture::create($this->chatRepository, $this->createMock(OvhAiClient::class), new NullLogger(), $registry);
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'google:gemini-3.1-flash-image'));
        $reply = $service->addMessage(new AddChatMessageInput($this->userId, $chat->id, 'Draw fixture'));
        self::assertSame([$image], $reply[1]['images']);
        self::assertSame('', $reply[1]['content']);
        $restored = $service->getChatWithMessages($chat->id, $this->userId);
        self::assertSame([$image], $restored->messages[1]['images']);
        $stored = $this->pdo->query('SELECT data_url FROM chat_message_images')->fetchColumn();
        self::assertNotSame($image, $stored);
        self::assertStringNotContainsString('iVBORw0KGgo', $stored);
        $service->addMessage(new AddChatMessageInput($this->userId, $chat->id, 'Another drawing'));
        self::assertStringNotContainsString($image, json_encode($calls[1]));
        self::assertSame('assistant', $calls[1][1]['role']);
    }
}
