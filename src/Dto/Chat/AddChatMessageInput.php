<?php

declare(strict_types=1);

namespace App\Dto\Chat;

final readonly class AddChatMessageInput
{
    public function __construct(
        public int $chatId,
        public int $userId,
        public string $content,
        public array $images = [],
    ) {
    }
}
