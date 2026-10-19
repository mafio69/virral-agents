<?php

declare(strict_types=1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    // HTTP and CLI share the same local configuration source. Existing process
    // values take precedence; encrypted values still require external decryption.
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    $settings = require __DIR__.'/../config/app.php';
    $sensitiveValue = static function (string $name, string $fallback = ''): string {
        // Środowisko procesu ma pierwszeństwo przed zaszyfrowanym wpisem z pliku.
        $candidates = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '' && !str_starts_with($value, 'encrypted:')) {
                return $value;
            }
        }
        return $fallback;
    };

    $settings['environment'] = $sensitiveValue('APP_ENV', $settings['environment']);
    $settings['user_data'] = ['key' => $sensitiveValue('USER_DATA_KEY')];
    $settings['ai_registry'] = ['key' => $sensitiveValue('DB_HASH')];
    $settings['zai'] = ['key' => $sensitiveValue('ZAI_API_KEY')];
    $settings['openai'] = ['key' => $sensitiveValue('OPEN_AI_KEY')];
    $settings['anthropic'] = ['key' => $sensitiveValue('ANTHROPIC_API_KEY')];
    $settings['google'] = ['key' => $sensitiveValue('GEMINI_KEY')];
    // The application runs in Docker; loopback here would point back to PHP-FPM.
    // Host-only installations can explicitly set MCP_SEARCH_URL to the host endpoint.
    $settings['search'] = ['mcp_url' => $sensitiveValue('MCP_SEARCH_URL', 'http://mcp-search:8000/mcp')];
    $settings['ovh_ai']['token'] = $sensitiveValue('OVH_AI_ENDPOINTS_ACCESS_TOKEN');
    $settings['mail']['dsn'] = $sensitiveValue('MAIL_DSN', $settings['mail']['dsn']);

    $containerBuilder->addDefinitions(['settings' => $settings]);
};
