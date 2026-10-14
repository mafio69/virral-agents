<?php

declare(strict_types=1);

use App\Infrastructure\DataProtection\DataProtectionMigration;
use App\Infrastructure\DataProtection\SodiumDataCipher;
use App\Infrastructure\Database\MigrationRunner;

if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Uruchom lokalnie: dxrun php bin/protect-user-data.php --apply\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

try {
    $cipher = new SodiumDataCipher(getenv('USER_DATA_KEY') ?: '');
    $providerKey = getenv('DB_HASH') ?: '';
    if ($providerKey !== '' && hash_equals(strtolower($providerKey), strtolower(getenv('USER_DATA_KEY') ?: ''))) {
        throw new \App\Exception\DataProtectionException();
    }
    $settings = require dirname(__DIR__).'/config/app.php';
    $path = $settings['db']['path'];
    if (!is_file($path)) throw new RuntimeException();
    $pdo = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    (new MigrationRunner($pdo))->run();
    (new DataProtectionMigration($pdo, $cipher))->run();
    $checkpoint = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
    if ((int)$checkpoint[0] > 0) throw new RuntimeException();
    $pdo->exec('VACUUM');
    $checkpoint = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
    if ((int)$checkpoint[0] > 0) throw new RuntimeException();
    fwrite(STDOUT, "Migracja ochrony danych zakończona. Uruchom aplikację ponownie.\n");
} catch (Throwable) {
    fwrite(STDERR, "Migracja lub porządkowanie pliku bazy nie zostało zakończone. Sprawdź klucz, uprawnienia i zatrzymanie aplikacji. Nie usuwaj ani nie zmieniaj klucza.\n");
    exit(1);
}
