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

    $settings['user_data'] = ['key' => $sensitiveValue('USER_DATA_KEY')];
    $settings['ai_registry'] = ['key' => $sensitiveValue('DB_HASH')];
    $settings['zai'] = ['key' => $sensitiveValue('ZAI_API_KEY')];
    $settings['ovh_ai']['token'] = $sensitiveValue('OVH_AI_ENDPOINTS_ACCESS_TOKEN');
    $settings['mail']['dsn'] = $sensitiveValue('MAIL_DSN', $settings['mail']['dsn']);

    $containerBuilder->addDefinitions(['settings' => $settings]);
};
