<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiReply;

interface GroundedClientInterface extends AiClientInterface
{
    public function generateGrounded(array $messages, string $model): AiReply;
}
