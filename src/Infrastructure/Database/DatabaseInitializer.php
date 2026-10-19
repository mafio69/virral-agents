<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\DataProtection\DataProtectionMigration;
use PDO;

final readonly class DatabaseInitializer
{
    public function __construct(private PDO $pdo, private DataCipherInterface $cipher, private MigrationRunner $migrations)
    {
    }

    public function initialize(): void
    {
        $this->migrations->run();
        (new DataProtectionMigration($this->pdo, $this->cipher))->initializeEmptyDatabase();
    }
}
