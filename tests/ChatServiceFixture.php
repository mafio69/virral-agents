<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\OvhAiClient;
use App\Repository\ChatRepository;
use App\Service\ChatService;
use App\Service\ChatReplyGenerator;
use App\Service\ChatReplyGeneratorInterface;
use App\Service\ChatImageValidator;
use App\Service\ModelCatalogService;
use App\Service\ResearchServiceInterface;
use App\Service\ResearchReplyGeneratorInterface;
use Psr\Log\LoggerInterface;

final class ChatServiceFixture
{
    public static function create(
        ChatRepository $chatRepository,
        OvhAiClient $ovhAiClient,
        LoggerInterface $logger,
        ?ChatModelRegistryInterface $modelRegistry = null,
        ?ChatReplyGeneratorInterface $replyGenerator = null,
        ?ResearchServiceInterface $research = null,
        ?ResearchReplyGeneratorInterface $researchReply = null,
        ?\App\Repository\ChatUsageRepository $chatUsageRepository = null,
    ): ChatService {
        $modelRegistry ??= new class () implements ChatModelRegistryInterface {
            public function listModels(): array
            {
                return [];
            }
            public function resolve(string $selector): \App\Dto\AI\AiModel
            {
                throw new \App\Exception\AiConnectionException('missing_configuration');
            }
            public function clientFor(\App\Dto\AI\AiModel $model): \App\Infrastructure\AI\AiClientInterface
            {
                throw new \App\Exception\AiConnectionException('missing_configuration');
            }
        };
        $research ??= new class () implements ResearchServiceInterface {
            public function state(int $userId, int $chatId): array
            {
                return [];
            }
            public function propose(int $userId, int $chatId, array $request, string $origin = 'user'): array
            {
                throw new \LogicException('Research fixture not configured');
            }
            public function decide(int $userId, int $chatId, string $actionId, bool $allow): array
            {
                throw new \LogicException('Research fixture not configured');
            }
            public function select(int $userId, int $chatId, string $sourceId, bool $selected): array
            {
                throw new \LogicException('Research fixture not configured');
            }
            public function prepare(int $userId, int $chatId): array
            {
                throw new \LogicException('Research fixture not configured');
            }
            public function clear(int $userId, int $chatId): array
            {
                return [];
            }
            public function evidence(int $userId, int $chatId): array
            {
                throw new \LogicException('Research fixture not configured');
            }
            public function adoptDraft(int $userId, int $chatId): void
            {
            }
        };
        $researchReply ??= new class () implements ResearchReplyGeneratorInterface {
            public function generate(\App\Infrastructure\AI\AiClientInterface $client, array $messages, string $model, int $userId, int $chatId): \App\Dto\AI\AiReply
            {
                throw new \LogicException('Research fixture not configured');
            }
        };
        return new ChatService(
            $chatRepository,
            $ovhAiClient,
            $logger,
            $modelRegistry,
            $replyGenerator ?? new ChatReplyGenerator(),
            new ChatImageValidator(),
            new ModelCatalogService($ovhAiClient, $modelRegistry, $logger),
            $research,
            $researchReply,
            chatUsageRepository: $chatUsageRepository,
        );
    }
}
