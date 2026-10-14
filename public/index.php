<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$containerBuilder = new ContainerBuilder();

$settings = require __DIR__ . '/../src/settings.php';
$settings($containerBuilder);

$dependencies = require __DIR__ . '/../src/dependencies.php';
$dependencies($containerBuilder);

$container = $containerBuilder->build();

$logger = $container->get(\Psr\Log\LoggerInterface::class);

// Run database migrations
$initializationStage = 'database_schema';
try {
    $migrationRunner = $container->get(\App\Infrastructure\Database\MigrationRunner::class);
    $migrationRunner->run();
    $initializationStage = 'user_data_key';
    $cipher = $container->get(\App\Infrastructure\DataProtection\DataCipherInterface::class);
    $initializationStage = 'user_data_readiness';
    (new \App\Infrastructure\DataProtection\DataProtectionMigration(
        $container->get(PDO::class),
        $cipher,
    ))->initializeEmptyDatabase();
} catch (\Throwable $e) {
    for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
        if ($cause instanceof \App\Exception\DataProtectionException && $cause->reason !== null) {
            $initializationStage = $cause->reason;
            break;
        }
    }
    $logger->critical('Database initialization unavailable', ['stage' => $initializationStage]);
    http_response_code(503);
    header('X-App-Error: '.$initializationStage);
    header('Cache-Control: no-store');
    require __DIR__ . '/error-pages/503.html';
    exit(1);
}

$app = Bridge::create($container);

$middleware = require __DIR__ . '/../src/middleware.php';
$middleware($app);

$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

$app->run();
