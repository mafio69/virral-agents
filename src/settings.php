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
    $settings['openrouter'] = ['key' => $sensitiveValue('OPENROUTER_API_KEY'), 'models' => $sensitiveValue('OPENROUTER_MODELS')];
    $settings['langfuse'] = [
        'host' => $sensitiveValue('LANGFUSE_HOST', 'https://cloud.langfuse.com'),
        'public_key' => $sensitiveValue('LANGFUSE_PUBLIC_KEY'),
        'secret_key' => $sensitiveValue('LANGFUSE_SECRET_KEY'),
        'enabled' => filter_var($sensitiveValue('LANGFUSE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
    ];
    // The application runs in Docker; loopback here would point back to PHP-FPM.
    // Host-only installations can explicitly set MCP_SEARCH_URL to the host endpoint.
    $settings['search'] = [
        'mcp_url' => $sensitiveValue('MCP_SEARCH_URL', 'http://mcp-search:8000/mcp'),
        'mcp_token' => $sensitiveValue('MCP_AUTH_TOKEN', ''),
    ];
    $settings['ovh_ai']['token'] = $sensitiveValue('OVH_AI_ENDPOINTS_ACCESS_TOKEN');
    $settings['mail']['dsn'] = $sensitiveValue('MAIL_DSN', $settings['mail']['dsn']);
    $trustedProxies = $sensitiveValue('TRUSTED_PROXIES');
    if ($trustedProxies !== '') {
        $settings['trusted_proxies'] = array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));
    }

    $containerBuilder->addDefinitions(['settings' => $settings]);
};
