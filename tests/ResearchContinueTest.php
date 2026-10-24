<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\AI\AiReply;
use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\CreateChatInput;
use App\Dto\Chat\RetryMessageInput;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Infrastructure\AI\OvhAiClient;
use App\Service\ResearchReplyGeneratorInterface;
use App\Service\ResearchServiceInterface;
use Psr\Log\NullLogger;

final class ResearchContinueTest extends ChatServiceTestCase
{
    public function testContinueAnswersTheLatestQuestionWithoutANewUserMessageAndSkipsApprovalNotices(): void
    {
        $service = $this->serviceWithReplies([
            new AiReply('AI prosi o zgodę na operację WWW — panel źródeł.', search: ['status' => 'approval_required'], kind: 'approval_request'),
            new AiReply('Answer from sources'),
        ], $captured);
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));

        $pair = $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie o słuchawki', research: true));
        self::assertSame('approval_request', $pair[1]['kind']);
        self::assertSame('message', $pair[0]['kind']);

        $answer = $service->continueMessage(new RetryMessageInput($chat->id, $this->userId, (int)$pair[0]['id']));
        self::assertSame('message', $answer['kind']);
        self::assertSame('Answer from sources', $answer['content']);

        $messages = $service->getChatWithMessages($chat->id, $this->userId)->messages;
        self::assertCount(3, $messages);
        self::assertSame('user', $messages[0]['role']);
        self::assertSame('approval_request', $messages[1]['kind']);
        self::assertSame('assistant', $messages[2]['role']);
        self::assertSame('message', $messages[2]['kind']);

        self::assertCount(1, $captured[1]);
        self::assertSame('Pytanie o słuchawki', $captured[1][0]['content']);
    }

    public function testContinuationInstructionReachesTheModelOnLaterSends(): void
    {
        $service = $this->serviceWithReplies([
            new AiReply('First answer'),
            new AiReply('Answer after continuation'),
        ], $captured);
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));

        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie', research: true));
        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Kontynuuj opracowanie źródeł.', research: true, kind: 'continuation'));

        $roles = array_map(static fn (array $message): string => $message['role'].'|'.$message['content'], $captured[1]);
        self::assertSame(['user|Pytanie', 'assistant|First answer', 'user|Kontynuuj opracowanie źródeł.'], $roles);
    }

    public function testContinueRejectsAnythingExceptTheLatestQuestion(): void
    {
        $service = $this->serviceWithReplies([new AiReply('Answer'), new AiReply('Answer')]);
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $first = $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pierwsze pytanie', research: true));
        $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Drugie pytanie', research: true));

        $this->expectException(ValidationException::class);
        $service->continueMessage(new RetryMessageInput($chat->id, $this->userId, (int)$first[0]['id']));
    }

    public function testContinueRejectsUnknownMessageAndForeignOwner(): void
    {
        $service = $this->serviceWithReplies([new AiReply('Answer')]);
        $chat = $service->createChat(new CreateChatInput($this->userId, 'Fixture', 'Qwen3.6-27B'));
        $pair = $service->addMessage(new AddChatMessageInput($chat->id, $this->userId, 'Pytanie', research: true));

        try {
            $service->continueMessage(new RetryMessageInput($chat->id, $this->userId, (int)$pair[0]['id'] + 999));
            self::fail('Expected validation error');
        } catch (ValidationException) {
        }
        $this->expectException(NotFoundException::class);
        $service->continueMessage(new RetryMessageInput($chat->id, $this->userId + 1, (int)$pair[0]['id']));
    }

    private function serviceWithReplies(array $replies, ?array &$captured = null): \App\Service\ChatService
    {
        $captured = [];
        $research = $this->createMock(ResearchServiceInterface::class);
        $research->method('evidence')->willReturn(['sources' => [], 'decisions' => []]);
        $researchReply = $this->createMock(ResearchReplyGeneratorInterface::class);
        $researchReply->method('generate')->willReturnCallback(function (\App\Infrastructure\AI\AiClientInterface $client, array $messages) use (&$replies, &$captured): AiReply {
            $captured[] = $messages;
            return array_shift($replies) ?? new AiReply('Fixture answer');
        });
        $client = $this->createMock(OvhAiClient::class);
        $client->expects(self::never())->method('generate');
        return ChatServiceFixture::create($this->chatRepository, $client, new NullLogger(), research: $research, researchReply: $researchReply);
    }
}
