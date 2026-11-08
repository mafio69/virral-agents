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
        'min_level' => 'info',
        'date_format' => 'Y-m-d H:i:s',
        'timezone' => 'Europe/Warsaw',
    ],
    'app_url' => 'http://localhost:8181',
    'chat' => [
        // Ile ostatnich tur rozmowy trafia do modelu w całości; starsze tury
        // zostają jako przycięte wiadomości użytkownika (HistoryCompactor, T3.1).
        'context_recent_turns' => 8,
    ],
    'research' => [
        // Nagłówki fetchera stron (E2.2): realistyczny UA omija blokady 403 portali,
        // Accept-Language jak w przeglądarce. Fetch pozostaje bez cookies i bez JS.
        'fetch_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
        'fetch_accept_language' => 'pl-PL,pl;q=0.9,en;q=0.8',
    ],
    // Reverse proxies whose X-Forwarded-For is trusted for the login limit (addresses or CIDR).
    // Empty: REMOTE_ADDR is used as is. Override with TRUSTED_PROXIES (comma-separated).
    'trusted_proxies' => [],
    'mail' => [
        // Publiczna konfiguracja lokalna, bez danych logowania.
        // DSN zawierający poświadczenia lub prywatny adres należy przekazać przez MAIL_DSN.
        'dsn' => 'smtp://localhost:1025',
    ],
];
