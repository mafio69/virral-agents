<?php
declare(strict_types=1);
namespace App\Infrastructure\AI;

use App\Dto\AI\AiReply;

interface WebToolClientInterface extends AiClientInterface
{
    /** Proposes an operation only; the client never executes WWW. */
    public function generateWebReply(array $messages, string $model): AiReply;
}
