<?php

declare(strict_types=1);

namespace App\Mapper\Chat;

use App\Dto\Chat\AddChatMessageInput;
use App\Exception\ValidationException;

final readonly class AddChatMessageInputMapper
{
    public function map(array $data, int $chatId, int $userId): AddChatMessageInput
    {
        $content = $data['content'] ?? '';

        $images = (new \App\Service\ChatImageValidator())->validate($data['images'] ?? []);
        if (!is_string($content) || (trim($content) === '' && $images === []) || strlen($content) > 16000) {
            throw new ValidationException('Message content is required');
        }

        return new AddChatMessageInput($chatId, $userId, trim($content), $images, \App\Dto\Chat\SearchOptions::fromInput($data['search'] ?? []));
    }
}
