<?php

declare(strict_types=1);

namespace App\Mapper\Chat;

use App\Dto\Chat\RetryMessageInput;

final readonly class RetryMessageInputMapper
{
    public function map(int $chatId, int $messageId, int $userId): RetryMessageInput
    {
        return new RetryMessageInput($chatId, $userId, $messageId);
    }
}
