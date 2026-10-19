<?php

declare(strict_types=1);
namespace Tests;

use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Exception\AiConnectionException;
use App\Exception\ValidationException;
use App\Infrastructure\AI\OvhAiClient;
use Psr\Log\NullLogger;

final class ChatRetryTest extends ChatServiceTestCase
{
    public function testFailedReplyCanBeRetriedWithoutDuplicatingUserAndRepeatedRetryIsIdempotent(): void
    {
        $client = $this->createMock(OvhAiClient::class);
        $calls = 0;
        $client->expects(self::exactly(2))->method('generate')->willReturnCallback(function (array $messages) use (&$calls): string {
            self::assertCount(1, $messages);
            self::assertSame('Synthetic question', $messages[0]['content']);
            if (++$calls === 1) throw new AiConnectionException('provider_timeout');
            return 'Synthetic answer';
        });
        $service = ChatServiceFixture::create($this->chatRepository, $client, new NullLogger());
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        try {
            $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Synthetic question'));
            self::fail('Expected timeout');
        } catch (AiConnectionException $error) { self::assertSame('provider_timeout', $error->reason); }
        $messages = $service->getChatWithMessages($chat->id, $this->userId)->messages;
        self::assertCount(1, $messages);
        self::assertSame('failed', $messages[0]['reply_status']);
        $id = (int)$messages[0]['id'];
        $pair = $service->retryMessage($chat->id, $this->userId, $id);
        self::assertSame($id, $pair[0]['id']);
        self::assertSame('Synthetic answer', $pair[1]['content']);
        self::assertSame($pair[1]['id'], $service->retryMessage($chat->id, $this->userId, $id)[1]['id']);
        self::assertCount(2, $service->getChatWithMessages($chat->id, $this->userId)->messages);
    }

    public function testRunningRequestAndOtherOwnerCannotStartAnotherGeneration(): void
    {
        $client = $this->createMock(OvhAiClient::class);
        $client->expects(self::never())->method('generate');
        $service = ChatServiceFixture::create($this->chatRepository, $client, new NullLogger());
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $id = $this->chatRepository->addMessage($chat->id, 'user', 'Synthetic');
        $this->chatRepository->startReply($id, false);
        foreach ([$this->userId, $this->userId + 1] as $owner) {
            try { $service->retryMessage($chat->id, $owner, $id); self::fail('Must refuse'); }
            catch (ValidationException|\App\Exception\NotFoundException) { self::assertTrue(true); }
        }
        $this->chatRepository->finishReply($id, false);
        self::assertFalse($this->chatRepository->claimRetry($id));
        $this->expectException(ValidationException::class);
        $this->chatRepository->claimRetry($id);
    }

    public function testRetryRetainsResearchModeWithoutAnotherSearchOrUserMessage(): void
    {
        $research = $this->createMock(\App\Service\ResearchServiceInterface::class);
        $research->method('evidence')->willReturn(['sources' => [], 'decisions' => []]);
        $researchReply = $this->createMock(\App\Service\ResearchReplyGeneratorInterface::class);
        $calls = 0;
        $researchReply->expects(self::exactly(2))->method('generate')->willReturnCallback(function () use (&$calls) {
            if (++$calls === 1) throw new AiConnectionException('provider_timeout');
            return new \App\Dto\AI\AiReply('Research answer');
        });
        $client = $this->createMock(OvhAiClient::class);
        $client->expects(self::never())->method('generate');
        $service = ChatServiceFixture::create($this->chatRepository, $client, new NullLogger(), research: $research, researchReply: $researchReply);
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        try { $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Synthetic', research: true)); }
        catch (AiConnectionException) {}
        $id = (int)$service->getChatWithMessages($chat->id, $this->userId)->messages[0]['id'];
        $service->retryMessage($chat->id, $this->userId, $id);
        self::assertCount(2, $service->getChatWithMessages($chat->id, $this->userId)->messages);
    }
    public function testExistingChatStillWorksBeforeOperatorRunsNewMigration(): void
    {
        $this->pdo->exec('DROP TABLE chat_reply_attempts');
        $client = $this->createMock(OvhAiClient::class);
        $client->method('generate')->willReturn('Synthetic answer');
        $service = ChatServiceFixture::create($this->chatRepository, $client, new NullLogger());
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Synthetic question'));
        self::assertCount(2, $service->getChatWithMessages($chat->id, $this->userId)->messages);
    }

}
