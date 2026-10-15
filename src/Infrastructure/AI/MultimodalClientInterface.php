<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiReply;

interface MultimodalClientInterface extends AiClientInterface
{
    public function generateReply(array $messages, ?string $model = null): AiReply;
}
