<?php

declare(strict_types=1);

// Isolated E2E application: production DI/routes/middleware, synthetic settings.
// This entry point never loads settings.php, dotenv files or the installation DB.
require __DIR__.'/../../vendor/autoload.php';
$_ENV['APP_ENV'] = 'dev';

use DI\Bridge\Slim\Bridge;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/health') {
    header('Content-Type: text/plain');
    echo 'isolated-e2e';
    return;
}
if (preg_match('~^/ui/(?:theme\.css|lib/(?:marked\.umd\.js|purify\.min\.js)|chat/[a-z-]+\.(?:js|css))$~D', $path)) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css' : 'text/javascript').'; charset=UTF-8');
    readfile(__DIR__.'/../../public'.$path);
    return;
}
if ($path === '/favicon.ico') {
    http_response_code(204);
    return;
}

$container = require __DIR__.'/container.php';
$app = Bridge::create($container);
(require __DIR__.'/../../src/routes.php')($app);
(require __DIR__.'/../../src/middleware.php')($app);
$app->run();
