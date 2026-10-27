<?php

declare(strict_types=1);

namespace Tests\Observability;

use DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LangfuseConfigTest extends TestCase
{
    #[DataProvider('langfuseEnabledDataProvider')]
    public function testLangfuseEnabledParsesCorrectly(?string $envValue, bool $expected): void
    {
        $backupEnv = getenv('LANGFUSE_ENABLED');
        $backupServer = $_SERVER['LANGFUSE_ENABLED'] ?? null;
        $backupGlobalEnv = $_ENV['LANGFUSE_ENABLED'] ?? null;

        try {
            if ($envValue !== null) {
                putenv("LANGFUSE_ENABLED={$envValue}");
                $_ENV['LANGFUSE_ENABLED'] = $envValue;
                $_SERVER['LANGFUSE_ENABLED'] = $envValue;
            } else {
                putenv('LANGFUSE_ENABLED');
                unset($_ENV['LANGFUSE_ENABLED'], $_SERVER['LANGFUSE_ENABLED']);
            }

            $containerBuilder = new ContainerBuilder();
            $loadSettings = require __DIR__ . '/../../src/settings.php';
            $loadSettings($containerBuilder);
            $container = $containerBuilder->build();

            $settings = $container->get('settings');
            $this->assertSame($expected, $settings['langfuse']['enabled']);
            $this->assertSame('https://cloud.langfuse.com', $settings['langfuse']['host']);
        } finally {
            if ($backupEnv !== false) {
                putenv("LANGFUSE_ENABLED={$backupEnv}");
            } else {
                putenv('LANGFUSE_ENABLED');
            }

            if ($backupServer !== null) {
                $_SERVER['LANGFUSE_ENABLED'] = $backupServer;
            } else {
                unset($_SERVER['LANGFUSE_ENABLED']);
            }

            if ($backupGlobalEnv !== null) {
                $_ENV['LANGFUSE_ENABLED'] = $backupGlobalEnv;
            } else {
                unset($_ENV['LANGFUSE_ENABLED']);
            }
        }
    }

    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    public static function langfuseEnabledDataProvider(): array
    {
        return [
            'value true string' => ['true', true],
            'value 1 string' => ['1', true],
            'value false string' => ['false', false],
            'value 0 string' => ['0', false],
            'value empty string' => ['', false],
        ];
    }
}
