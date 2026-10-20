<?php

declare(strict_types=1);

namespace App\Infrastructure\DataProtection;

use PDO;

final readonly class ProtectedStore
{
    private ProtectedFields $fields;

    public function __construct(private PDO $pdo, DataCipherInterface $cipher)
    {
        (new DataProtectionMigration($pdo, $cipher))->assertReady();
        $this->fields = new ProtectedFields($cipher);
    }

    public function insert(string $table, array $row): int
    {
        $ownedTransaction = !$this->pdo->inTransaction();
        if ($ownedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            // Reserve an ID using encrypted placeholders; plaintext is never written.
            $placeholder = $this->fields->encrypt($table, $row, 0);
            $columns = array_keys($placeholder);
            $statement = $this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',', $columns).') VALUES (:'.implode(',:', $columns).')');
            $statement->execute($placeholder);
            $id = (int)$this->pdo->lastInsertId();
            $identity = $table === 'chat_message_images' ? (int)$row['message_id'] : $id;
            $this->update($table, $identity, $row);
            if ($ownedTransaction) {
                $this->pdo->commit();
            }
            return $identity;
        } catch (\Throwable $error) {
            if ($ownedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function update(string $table, int $id, array $row): void
    {
        $identity = $table === 'chat_message_images' ? 'message_id' : 'id';
        $lookup = $this->pdo->prepare('SELECT * FROM '.$table.' WHERE '.$identity.' = :row_id');
        $lookup->execute(['row_id' => $id]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new \App\Exception\DataProtectionException();
        }
        $row = $this->fields->encrypt($table, $row, $id, $existing);
        $assignments = array_map(static fn (string $field): string => $field.' = :'.$field, array_keys($row));
        $statement = $this->pdo->prepare('UPDATE '.$table.' SET '.implode(',', $assignments).' WHERE '.$identity.' = :row_id');
        $statement->execute($row + ['row_id' => $id]);
    }

    public function decrypt(string $table, array $row): array
    {
        return $this->fields->decrypt($table, $row, (int)$row[$table === 'chat_message_images' ? 'message_id' : 'id']);
    }
}
