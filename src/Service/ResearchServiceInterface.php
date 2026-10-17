<?php
declare(strict_types=1);
namespace App\Service;

interface ResearchServiceInterface
{
    public function state(int $userId, int $chatId): array;
    public function propose(int $userId, int $chatId, array $request, string $origin = 'user'): array;
    public function decide(int $userId, int $chatId, string $actionId, bool $allow): array;
    public function select(int $userId, int $chatId, string $sourceId, bool $selected): array;
    public function prepare(int $userId, int $chatId): array;
    public function evidence(int $userId, int $chatId): array;
    public function adoptDraft(int $userId, int $chatId): void;
}
