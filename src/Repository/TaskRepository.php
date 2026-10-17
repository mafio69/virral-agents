<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\DataProtection\ProtectedStore;

final readonly class TaskRepository
{
    private ProtectedStore $store;

    public function __construct(private readonly PDO $pdo, DataCipherInterface $cipher)
    {
        $this->store = new ProtectedStore($pdo, $cipher);
    }

    public function create(int $userId, string $title, ?string $description, string $status): int
    {
        return $this->store->insert('tasks', [
            'user_id' => $userId, 'title' => $title, 'description' => $description, 'status' => $status,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, description, status, created_at, updated_at
              FROM tasks WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map(fn(array $row): array => $this->store->decrypt('tasks', $row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, description, status, created_at, updated_at
              FROM tasks WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->store->decrypt('tasks', $result) : null;
    }

    public function update(int $id, string $title, ?string $description, string $status): void
    {
        $this->store->update('tasks', $id, [
            'title' => $title, 'description' => $description, 'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Batch insert for tasks
     */
    public function createBatch(array $tasks): array
    {
        $this->pdo->beginTransaction();
        try {
            $ids = [];
            foreach ($tasks as $task) {
                $ids[] = $this->store->insert('tasks', [
                    'user_id' => $task['user_id'], 'title' => $task['title'], 'description' => $task['description'],
                    'status' => $task['status'], 'created_at' => $task['created_at'], 'updated_at' => $task['updated_at'],
                ]);
            }
            $this->pdo->commit();
            return $ids;
        } catch (\Throwable $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
}
