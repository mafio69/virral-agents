<?php

declare(strict_types=1);

namespace App\Mapper\Chat;

use App\Dto\Chat\CreateChatInput;
use App\Exception\ValidationException;

final readonly class CreateChatInputMapper
{
    public function map(array $data, int $userId): CreateChatInput
    {
        $title = $data['title'] ?? '';
        $model = $data['model'] ?? null;

        if (!is_string($title) || trim($title) === '') {
            throw new ValidationException('Title is required');
        }

        if ($model !== null && (!is_string($model) || strlen($model) > 200)) {
            throw new ValidationException('Nieprawidłowy model.');
        }

        return new CreateChatInput($userId, trim($title), $model);
    }
}
