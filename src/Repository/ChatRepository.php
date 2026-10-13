<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final readonly class ChatRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, string $title, ?string $model = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO chats (user_id, title, created_at, model) VALUES (:user_id, :title, :created_at, :model)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'created_at' => date('Y-m-d H:i:s'),
            'model' => \App\Infrastructure\AI\OvhModelDefaults::resolve($model),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, created_at, model FROM chats WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, created_at, model FROM chats WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function addMessage(int $chatId, string $role, string $content, array $images = []): int
    {
        $transaction = $images !== [];
        if ($transaction) $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO chat_messages (chat_id, role, content, created_at) VALUES (:chat_id, :role, :content, :created_at)'
            );
            $stmt->execute([
                'chat_id' => $chatId, 'role' => $role, 'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$this->pdo->lastInsertId();
            if ($images !== []) {
                $stmt = $this->pdo->prepare('INSERT INTO chat_message_images (message_id, data_url) VALUES (?, ?)');
                $stmt->execute([$id, $images[0]]);
            }
            if ($transaction) $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($transaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function findMessagesByChatId(int $chatId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.role, m.content, m.created_at, i.data_url FROM chat_messages m LEFT JOIN chat_message_images i ON i.message_id = m.id WHERE m.chat_id = :chat_id ORDER BY m.id ASC'
        );
        $stmt->execute(['chat_id' => $chatId]);

        return array_map(static function (array $row): array {
            if ($row['data_url'] !== null) $row['images'] = [$row['data_url']];
            unset($row['data_url']);
            return $row;
        }, $stmt->fetchAll());
    }

    /**
     * Batch insert for chat messages
     */
    public function addMessagesBatch(array $messages): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO chat_messages (chat_id, role, content, created_at) VALUES (:chat_id, :role, :content, :created_at)'
            );
            
            foreach ($messages as $message) {
                $stmt->execute([
                    'chat_id' => $message['chat_id'],
                    'role' => $message['role'],
                    'content' => $message['content'],
                    'created_at' => $message['created_at'],
                ]);
            }
            
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
}
