<?php

declare(strict_types=1);

use App\Infrastructure\DataProtection\DataProtectionMigration;
use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\Database\MigrationRunner;

if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Uruchom lokalnie: php bin/protect-user-data.php --apply\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

$stage = 'configuration';
try {
    $builder = new \DI\ContainerBuilder();
    (require dirname(__DIR__).'/src/settings.php')($builder);
    (require dirname(__DIR__).'/src/dependencies.php')($builder);
    $container = $builder->build();
    $stage = 'user_data_key';
    $cipher = $container->get(DataCipherInterface::class);
    $settings = $container->get('settings');
    $stage = 'database_file';
    $path = $settings['db']['path'];
    if (!is_file($path)) {
        throw new RuntimeException();
    }
    $stage = 'database_connection';
    $pdo = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $stage = 'database_schema';
    (new MigrationRunner($pdo))->run();
    $stage = 'user_data_migration';
    (new DataProtectionMigration($pdo, $cipher))->run();
    $stage = 'database_cleanup';
    $checkpoint = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
    if ((int)$checkpoint[0] > 0) {
        throw new RuntimeException();
    }
    $pdo->exec('VACUUM');
    $checkpoint = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
    if ((int)$checkpoint[0] > 0) {
        throw new RuntimeException();
    }
    fwrite(STDOUT, "Migracja ochrony danych zakończona. Uruchom aplikację ponownie.\n");
} catch (Throwable $error) {
    if ($error instanceof \App\Exception\DataProtectionException && $error->reason !== null) {
        $stage = $error->reason;
    }
    fwrite(STDERR, "Migracja lub porządkowanie pliku bazy nie zostało zakończone. Sprawdź klucz, uprawnienia i zatrzymanie aplikacji. Nie usuwaj ani nie zmieniaj klucza.\n");
    fwrite(STDERR, "Kod diagnostyczny: ".$stage."\n");
    exit(1);
}
