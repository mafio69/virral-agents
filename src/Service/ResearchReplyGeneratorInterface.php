<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AI\AiReply;
use App\Infrastructure\AI\AiClientInterface;

interface ResearchReplyGeneratorInterface
{
    public function generate(AiClientInterface $client, array $messages, string $model, int $userId, int $chatId): AiReply;
}
