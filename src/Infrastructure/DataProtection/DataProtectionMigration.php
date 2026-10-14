<?php

declare(strict_types=1);

namespace App\Infrastructure\DataProtection;

use App\Exception\DataProtectionException;
use PDO;

final readonly class DataProtectionMigration
{
    private const CHECK = 'virralAI/user-data/v1';

    public function __construct(private PDO $pdo, private DataCipherInterface $cipher)
    {
    }

    public function assertReady(): void
    {
        try {
            $state = $this->pdo->query('SELECT version, key_check FROM data_protection_state WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            if (!$state || (int)$state['version'] !== 1
                || $this->cipher->decrypt($state['key_check'], 'migration.key_check') !== self::CHECK) {
                throw new DataProtectionException();
            }
        } catch (\PDOException) {
            throw new DataProtectionException();
        }
    }

    public function run(): void
    {
        $this->pdo->exec('PRAGMA secure_delete = ON');
        $this->pdo->beginTransaction();
        try {
            if ($this->pdo->query('SELECT COUNT(*) FROM data_protection_state')->fetchColumn()) {
                $this->assertReady();
                $this->pdo->commit();
                return;
            }
            $fields = new ProtectedFields($this->cipher);
            foreach (ProtectedFields::TABLES as $table => $columns) {
                $identity = $table === 'chat_message_images' ? 'message_id' : 'id';
                $reader = $this->pdo->query('SELECT * FROM '.$table.' ORDER BY '.$identity);
                while ($row = $reader->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int)$row[$identity];
                    $changes = array_intersect_key($row, array_flip($columns));
                    if ($table === 'users') {
                        $changes['email_ciphertext'] = $row['email'];
                        $changes['email'] = $this->cipher->emailIndex($row['email']);
                        if ($row['email_verification_token'] !== null) {
                            $changes['email_verification_token'] = hash('sha256', $row['email_verification_token']);
                        }
                    }
                    $changes = $fields->encrypt($table, $changes, $id, $row);
                    $assignments = array_map(static fn(string $field): string => $field.' = :'.$field, array_keys($changes));
                    $statement = $this->pdo->prepare('UPDATE '.$table.' SET '.implode(',', $assignments).' WHERE '.$identity.' = :row_id');
                    $statement->execute($changes + ['row_id' => $id]);
                }
            }
            // Existing bearer credentials are invalidated rather than retained.
            $this->pdo->exec('DELETE FROM sessions');
            $statement = $this->pdo->prepare('INSERT INTO data_protection_state (id, version, key_check) VALUES (1, 1, :check)');
            $statement->execute(['check' => $this->cipher->encrypt(self::CHECK, 'migration.key_check')]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
