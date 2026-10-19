<?php

declare(strict_types=1);

use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\Database\DatabaseInitializer;
use DI\ContainerBuilder;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require __DIR__.'/../vendor/autoload.php';

try {
    $builder = new ContainerBuilder();
    (require __DIR__.'/../src/settings.php')($builder);
    (require __DIR__.'/../src/dependencies.php')($builder);
    $container = $builder->build();
    // Validate protection configuration before making any schema changes.
    $cipher = $container->get(DataCipherInterface::class);
    $container->get(DatabaseInitializer::class)->initialize();
    fwrite(STDOUT, "Database ready.\n");
} catch (Throwable $error) {
    // Report only a closed reason code or the exception class, never its message or data.
    $cause = $error;
    while ($cause->getPrevious() !== null && !$cause instanceof \App\Exception\DataProtectionException) {
        $cause = $cause->getPrevious();
    }
    $reason = $cause instanceof \App\Exception\DataProtectionException && $cause->reason !== null
        ? $cause->reason
        : (new \ReflectionClass($cause))->getShortName();
    fwrite(STDERR, "Database migration unavailable (reason: {$reason}). Check configuration and the data protection procedure.\n");
    exit(1);
}
