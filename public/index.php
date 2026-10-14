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
try {
    $migrationRunner = $container->get(\App\Infrastructure\Database\MigrationRunner::class);
    $migrationRunner->run();
    (new \App\Infrastructure\DataProtection\DataProtectionMigration(
        $container->get(PDO::class),
        $container->get(\App\Infrastructure\DataProtection\DataCipherInterface::class),
    ))->assertReady();
} catch (\Throwable $e) {
    $logger->critical('Database initialization unavailable');
    http_response_code(503);
    require __DIR__ . '/error-pages/503.html';
    exit(1);
}

$app = Bridge::create($container);

$middleware = require __DIR__ . '/../src/middleware.php';
$middleware($app);

$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

$app->run();
