<?php

declare(strict_types=1);

namespace App\Repository;

interface ResearchRepositoryInterface
{
    public function read(int $userId, int $chatId): array;
    public function change(int $userId, int $chatId, callable $change): array;
    public function adoptDraft(int $userId, int $chatId): void;
}
