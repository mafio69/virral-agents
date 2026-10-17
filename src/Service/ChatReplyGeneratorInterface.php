<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AI\AiReply;
use App\Dto\Chat\SearchOptions;
use App\Infrastructure\AI\AiClientInterface;

interface ChatReplyGeneratorInterface
{
    public function generate(AiClientInterface $client, array $messages, string $model, SearchOptions $search): AiReply;
}
