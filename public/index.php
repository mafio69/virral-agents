<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();

$settings = require __DIR__ . '/../src/settings.php';
$settings($containerBuilder);

$dependencies = require __DIR__ . '/../src/dependencies.php';
$dependencies($containerBuilder);

$container = $containerBuilder->build();

$logger = $container->get(\Psr\Log\LoggerInterface::class);

// Readiness is checked without changing schema or user data during HTTP requests.
$initializationStage = 'user_data_key';
try {
    $cipher = $container->get(\App\Infrastructure\DataProtection\DataCipherInterface::class);
    $initializationStage = 'user_data_readiness';
    (new \App\Infrastructure\DataProtection\DataProtectionMigration(
        $container->get(PDO::class),
        $cipher,
    ))->assertReady();
} catch (\Throwable $error) {
    for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
        if ($cause instanceof \App\Exception\DataProtectionException && $cause->reason !== null) {
            $initializationStage = $cause->reason;
            break;
        }
    }
    $logger->critical('Database readiness unavailable', [
        'stage' => $initializationStage,
        'type' => get_class($error),
        'origin' => basename($error->getFile()).':'.$error->getLine(),
        'line' => $error->getLine(),
        'code' => $error->getCode(),
    ]);
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
