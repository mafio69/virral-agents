<?php

declare(strict_types=1);

// Reset only the explicitly disposable E2E database/counters before starting HTTP.
foreach (['app.sqlite', 'app.sqlite-wal', 'app.sqlite-shm', 'initialized', 'counters.json'] as $name) {
    $path = __DIR__.'/runtime/'.$name;
    if (is_file($path)) {
        unlink($path);
    }
}
$container = require __DIR__.'/container.php';
$container->get(\App\Infrastructure\Database\DatabaseInitializer::class)->initialize();
