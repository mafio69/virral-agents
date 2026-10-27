<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final readonly class ChatUsageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(int $messageId, string $model, int $promptTokens, int $completionTokens): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO chat_usage (message_id, model, prompt_tokens, completion_tokens, created_at)
             VALUES (:message_id, :model, :prompt_tokens, :completion_tokens, :created_at)'
        );
        $stmt->execute([
            'message_id' => $messageId,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{message_id: int, model: string, prompt_tokens: int, completion_tokens: int, created_at: string}|null
     */
    public function findByMessageId(int $messageId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT message_id, model, prompt_tokens, completion_tokens, created_at FROM chat_usage WHERE message_id = :message_id'
        );
        $stmt->execute(['message_id' => $messageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'message_id' => (int) $row['message_id'],
            'model' => (string) $row['model'],
            'prompt_tokens' => (int) $row['prompt_tokens'],
            'completion_tokens' => (int) $row['completion_tokens'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{prompt_tokens: int, completion_tokens: int}>
     */
    public function findByMessageIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT message_id, prompt_tokens, completion_tokens FROM chat_usage WHERE message_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($ids));

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['message_id']] = [
                'prompt_tokens' => (int) $row['prompt_tokens'],
                'completion_tokens' => (int) $row['completion_tokens'],
            ];
        }

        return $result;
    }
}
