<?php

declare(strict_types=1);

// Zwykła konfiguracja aplikacji. Nie wpisuj tutaj kluczy, haseł ani prywatnych ścieżek.
// Sekrety są dołączane przez src/settings.php z istniejącego środowiska.
return [
    'displayErrorDetails' => false,
    'environment' => 'prod',
    'db' => [
        'path' => dirname(__DIR__).'/var/database/app.sqlite',
    ],
    'ovh_ai' => [
        'url' => 'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1',
        'model' => 'Qwen3.6-27B',
        'timeout' => 300,
    ],
    'logger' => [
        'log_dir' => dirname(__DIR__).'/var/logs',
        'min_level' => 'warning',
        'date_format' => 'Y-m-d H:i:s',
        'timezone' => 'Europe/Warsaw',
    ],
    'app_url' => 'http://localhost:8181',
    'mail' => [
        // Publiczna konfiguracja lokalna, bez danych logowania.
        // DSN zawierający poświadczenia lub prywatny adres należy przekazać przez MAIL_DSN.
        'dsn' => 'smtp://localhost:1025',
    ],
];
