<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\DataProtection\DataCipherInterface;
use PDO;

/** A whole workspace is authenticated and encrypted, including URLs and page text. */
final readonly class ResearchRepository implements ResearchRepositoryInterface
{
    public function __construct(private PDO $pdo, private DataCipherInterface $cipher)
    {
        (new \App\Infrastructure\DataProtection\DataProtectionMigration($pdo, $cipher))->assertReady();
    }

    public function read(int $userId, int $chatId): array
    {
        $query = $this->pdo->prepare('SELECT payload FROM research_workspaces WHERE user_id = ? AND chat_id = ?');
        $query->execute([$userId, $chatId]);
        $payload = $query->fetchColumn();
        return $payload === false ? self::emptyState() : json_decode($this->cipher->decrypt($payload, $this->context($userId, $chatId)), true, 64, JSON_THROW_ON_ERROR);
    }

    public function change(int $userId, int $chatId, callable $change): array
    {
        $this->pdo->beginTransaction();
        try {
            // Acquire the SQLite write lock before reading; no network inside this transaction.
            $query = $this->pdo->prepare('INSERT OR IGNORE INTO research_workspaces (user_id, chat_id, payload) VALUES (?, ?, ?)');
            $query->execute([$userId, $chatId, $this->encode(self::emptyState(), $userId, $chatId)]);
            $state = $change($this->read($userId, $chatId));
            $update = $this->pdo->prepare('UPDATE research_workspaces SET payload = ?, version = version + 1 WHERE user_id = ? AND chat_id = ?');
            $update->execute([$this->encode($state, $userId, $chatId), $userId, $chatId]);
            $this->pdo->commit();
            return $state;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function adoptDraft(int $userId, int $chatId): void
    {
        $this->pdo->beginTransaction();
        try {
            $draft = $this->read($userId, 0);
            $query = $this->pdo->prepare('INSERT OR IGNORE INTO research_workspaces (user_id, chat_id, payload) VALUES (?, ?, ?)');
            $query->execute([$userId, $chatId, $this->encode($draft, $userId, $chatId)]);
            if ($query->rowCount() !== 1) {
                throw new \App\Exception\ValidationException('Rozmowa ma już własną listę źródeł. Szkic zachowano.');
            }
            $delete = $this->pdo->prepare('DELETE FROM research_workspaces WHERE user_id = ? AND chat_id = 0');
            $delete->execute([$userId]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function context(int $userId, int $chatId): string
    {
        return 'research:owner:'.$userId.':chat:'.$chatId;
    }
    private function encode(array $state, int $userId, int $chatId): string
    {
        return $this->cipher->encrypt(json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $this->context($userId, $chatId));
    }
    private static function emptyState(): array
    {
        return ['results' => [], 'sources' => [], 'actions' => []];
    }
}
