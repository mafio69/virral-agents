<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\DataProtection\ProtectedStore;

final readonly class ChatRepository
{
    private ProtectedStore $store;

    public function __construct(private readonly PDO $pdo, DataCipherInterface $cipher)
    {
        $this->store = new ProtectedStore($pdo, $cipher);
    }

    public function create(int $userId, string $title, ?string $model = null): int
    {
        return $this->store->insert('chats', [
            'user_id' => $userId, 'title' => $title, 'created_at' => date('Y-m-d H:i:s'),
            'model' => \App\Infrastructure\AI\OvhModelDefaults::resolve($model),
        ]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, created_at, model FROM chats WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map(fn (array $row): array => $this->store->decrypt('chats', $row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, created_at, model FROM chats WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->store->decrypt('chats', $result) : null;
    }

    public function addMessage(int $chatId, string $role, string $content, array $images = []): int
    {
        $transaction = $images !== [];
        if ($transaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $id = $this->store->insert('chat_messages', [
                'chat_id' => $chatId, 'role' => $role, 'content' => $content, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($images !== []) {
                $this->store->insert('chat_message_images', ['message_id' => $id, 'data_url' => $images[0]]);
            }
            if ($transaction) {
                $this->pdo->commit();
            }
            return $id;
        } catch (\Throwable $error) {
            if ($transaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function findMessagesByChatId(int $chatId): array
    {
        // Rolling upgrade: existing conversations remain usable before the CLI migration.
        $attempts = $this->hasReplyAttempts();
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.chat_id, m.role, m.content, m.created_at, i.data_url, '
            .($attempts ? 'a.status' : 'NULL').' AS reply_status FROM chat_messages m '
            .($attempts ? 'LEFT JOIN chat_reply_attempts a ON a.message_id = m.id ' : '')
            .'LEFT JOIN chat_message_images i ON i.message_id = m.id WHERE m.chat_id = :chat_id ORDER BY m.id ASC'
        );
        $stmt->execute(['chat_id' => $chatId]);

        return array_map(function (array $row): array {
            $row = $this->store->decrypt('chat_messages', $row);
            if ($row['data_url'] !== null) {
                $image = $this->store->decrypt('chat_message_images', ['message_id' => $row['id'], 'data_url' => $row['data_url']]);
                $row['images'] = [$image['data_url']];
            }
            unset($row['data_url'], $row['chat_id']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function hasReplyAttempts(): bool
    {
        return $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'chat_reply_attempts'")->fetchColumn() !== false;
    }

    public function startReply(int $messageId, bool $research): void
    {
        if (!$this->hasReplyAttempts()) {
            return;
        }
        $this->pdo->prepare("INSERT INTO chat_reply_attempts (message_id, research, status) VALUES (?, ?, 'running')")
            ->execute([$messageId, (int)$research]);
    }

    public function claimRetry(int $messageId): bool
    {
        if (!$this->hasReplyAttempts()) {
            throw new \App\Exception\ValidationException('Ponawianie wymaga migracji bazy przez CLI.');
        }
        $query = $this->pdo->prepare("UPDATE chat_reply_attempts SET status = 'running' WHERE message_id = ? AND status = 'failed'");
        $query->execute([$messageId]);
        if ($query->rowCount() !== 1) {
            throw new \App\Exception\ValidationException('Odpowiedź jest nadal przetwarzana albo nie można jej ponowić. Sprawdź zapis rozmowy.');
        }
        $query = $this->pdo->prepare('SELECT research FROM chat_reply_attempts WHERE message_id = ?');
        $query->execute([$messageId]);
        return (bool)$query->fetchColumn();
    }

    public function finishReply(int $messageId, bool $success): void
    {
        if (!$this->hasReplyAttempts()) {
            return;
        }
        $this->pdo->prepare('UPDATE chat_reply_attempts SET status = ? WHERE message_id = ?')
            ->execute([$success ? 'done' : 'failed', $messageId]);
    }

    /**
     * Batch insert for chat messages
     */
    public function addMessagesBatch(array $messages): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($messages as $message) {
                $this->store->insert('chat_messages', [
                    'chat_id' => $message['chat_id'], 'role' => $message['role'],
                    'content' => $message['content'], 'created_at' => $message['created_at'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
}
