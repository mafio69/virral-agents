<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\DataProtection\DataProtectionMigration;
use App\Infrastructure\DataProtection\SodiumDataCipher;
use App\Infrastructure\Database\MigrationRunner;
use PDO;

final class ProtectedDataFixture
{
    private static ?\WeakMap $ciphers = null;

    public static function cipher(PDO $pdo): DataCipherInterface
    {
        self::$ciphers ??= new \WeakMap();
        if (!isset(self::$ciphers[$pdo])) {
            $cipher = new SodiumDataCipher(bin2hex(random_bytes(32)));
            (new MigrationRunner($pdo))->run();
            (new DataProtectionMigration($pdo, $cipher))->run();
            self::$ciphers[$pdo] = $cipher;
        }
        return self::$ciphers[$pdo];
    }
}
