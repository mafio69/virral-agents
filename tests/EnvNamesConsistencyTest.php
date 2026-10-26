<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class EnvNamesConsistencyTest extends TestCase
{
    public function testSettingsNamesAreExposedToFpmWorkers(): void
    {
        $missing = array_values(array_diff($this->settingsNames(), $this->fpmNames()));

        self::assertSame([], $missing, 'Zmienne ze settings.php niedostępne dla workerów PHP-FPM: '.implode(', ', $missing));
    }

    public function testFpmNamesAreProvidedByCompose(): void
    {
        $missing = array_values(array_diff($this->fpmNames(), $this->composeNames()));

        self::assertSame([], $missing, 'Zmienne z php-fpm-env.conf nieprzekazane przez Compose: '.implode(', ', $missing));
    }

    public function testMcpAuthTokenIsConfiguredEndToEnd(): void
    {
        self::assertContains('MCP_AUTH_TOKEN', $this->settingsNames(), 'brak w src/settings.php');
        self::assertContains('MCP_AUTH_TOKEN', $this->fpmNames(), 'brak w docker/php-fpm-env.conf');
        self::assertContains('MCP_AUTH_TOKEN', $this->composeNames(), 'brak w docker-compose.yml');
    }

    /** @return list<string> */
    private function settingsNames(): array
    {
        $content = file_get_contents(dirname(__DIR__).'/src/settings.php');
        self::assertNotFalse($content);
        preg_match_all("/\\\$sensitiveValue\('([A-Z0-9_]+)'/", $content, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @return list<string> */
    private function fpmNames(): array
    {
        $content = file_get_contents(dirname(__DIR__).'/docker/php-fpm-env.conf');
        self::assertNotFalse($content);
        preg_match_all('/^env\[([A-Z0-9_]+)\]/m', $content, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @return list<string> */
    private function composeNames(): array
    {
        $content = file_get_contents(dirname(__DIR__).'/docker-compose.yml');
        self::assertNotFalse($content);
        preg_match_all('/^      ([A-Z0-9_]+): \$\{[A-Z0-9_]+/m', $content, $matches);

        return array_values(array_unique($matches[1]));
    }
}
