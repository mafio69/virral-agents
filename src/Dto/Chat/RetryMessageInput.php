<?php

declare(strict_types=1);

namespace App\Dto\Chat;

final readonly class RetryMessageInput
{
    public function __construct(
        public int $chatId,
        public int $userId,
        public int $messageId,
    ) {
    }
}
