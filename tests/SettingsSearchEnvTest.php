<?php

declare(strict_types=1);

namespace Tests;

use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;

final class SettingsSearchEnvTest extends TestCase
{
    /** @var array<string, array{getenv: string|false, env: ?string, server: ?string}> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $original) {
            if ($original['getenv'] === false) {
                putenv($name);
            } else {
                putenv($name.'='.$original['getenv']);
            }
            if ($original['env'] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $original['env'];
            }
            if ($original['server'] === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $original['server'];
            }
        }
        $this->saved = [];
    }

    public function testMcpAuthTokenReachesSearchSettings(): void
    {
        $this->setEnv('MCP_AUTH_TOKEN', 'fixture-auth-token');

        $settings = $this->loadSettings();

        self::assertSame('fixture-auth-token', $settings['search']['mcp_token']);
    }

    public function testLegacyMcpSearchTokenNameIsIgnored(): void
    {
        $this->setEnv('MCP_AUTH_TOKEN', '');
        $this->setEnv('MCP_SEARCH_TOKEN', 'legacy-search-token');

        $settings = $this->loadSettings();

        self::assertSame('', $settings['search']['mcp_token']);
    }

    private function setEnv(string $name, string $value): void
    {
        if (!array_key_exists($name, $this->saved)) {
            $original = getenv($name);
            $this->saved[$name] = [
                'getenv' => $original === false ? false : (string) $original,
                'env' => isset($_ENV[$name]) ? (string) $_ENV[$name] : null,
                'server' => isset($_SERVER[$name]) ? (string) $_SERVER[$name] : null,
            ];
        }
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function loadSettings(): array
    {
        $builder = new ContainerBuilder();
        (require dirname(__DIR__).'/src/settings.php')($builder);

        return $builder->build()->get('settings');
    }
}
