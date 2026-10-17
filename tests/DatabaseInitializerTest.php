<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Database\DatabaseInitializer;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\DataProtection\DataProtectionMigration;
use App\Infrastructure\DataProtection\SodiumDataCipher;
use App\Exception\DataProtectionException;
use PHPUnit\Framework\TestCase;
use PDO;

final class DatabaseInitializerTest extends TestCase
{
    public function testStartupCreatesReadySchemaAndCanRunAgainWithoutChangingProtectionState(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $cipher = new SodiumDataCipher(str_repeat('ab', 32));
        $initializer = new DatabaseInitializer($pdo, $cipher, new MigrationRunner($pdo));
        $initializer->initialize();
        (new DataProtectionMigration($pdo, $cipher))->assertReady();
        $check = $pdo->query('SELECT key_check FROM data_protection_state')->fetchColumn();
        $initializer->initialize();
        self::assertSame($check, $pdo->query('SELECT key_check FROM data_protection_state')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn());
    }

    public function testStartupDoesNotAutomaticallyMigrateExistingPlaintextUsers(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $migrations = new MigrationRunner($pdo);
        $migrations->run();
        $pdo->exec("INSERT INTO users (email, password_hash, created_at) VALUES ('fixture@example.invalid', 'fixture-hash', '2026-01-01')");
        try {
            (new DatabaseInitializer($pdo, new SodiumDataCipher(str_repeat('ab', 32)), $migrations))->initialize();
            self::fail('Existing data requires the explicit protection migration');
        } catch (DataProtectionException) {
            self::assertSame('fixture@example.invalid', $pdo->query('SELECT email FROM users')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM data_protection_state')->fetchColumn());
        }
    }
}
