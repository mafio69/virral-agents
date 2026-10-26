<?php

declare(strict_types=1);

namespace Tests;

use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;

final class SettingsSearchEnvTest extends TestCase
{
    /** @var array<string, array{getenv: string|false, env: ?string, server: ?string}> */
    private array $saved = [];

    /** @var array<string, mixed> */
    private array $initialEnv = [];

    /** @var array<string, mixed> */
    private array $initialServer = [];

    protected function setUp(): void
    {
        $this->initialEnv = $_ENV;
        $this->initialServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        try {
            foreach (array_keys($_ENV + $_SERVER) as $name) {
                $key = (string) $name;
                if (!array_key_exists($key, $this->saved) && !array_key_exists($key, $this->initialEnv) && !array_key_exists($key, $this->initialServer)) {
                    putenv($key);
                }
            }
            foreach ($this->saved as $name => $original) {
                if ($original['getenv'] === false) {
                    putenv($name);
                } else {
                    putenv($name.'='.$original['getenv']);
                }
            }
        } finally {
            $_ENV = $this->initialEnv;
            $_SERVER = $this->initialServer;
            $this->saved = [];
        }
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
        $this->snapshotName($name);
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function snapshotName(string $name): void
    {
        if (!array_key_exists($name, $this->saved)) {
            $original = getenv($name);
            $this->saved[$name] = [
                'getenv' => $original === false ? false : (string) $original,
                'env' => isset($_ENV[$name]) ? (string) $_ENV[$name] : null,
                'server' => isset($_SERVER[$name]) ? (string) $_SERVER[$name] : null,
            ];
        }
    }

    private function loadSettings(): array
    {
        $beforeKeys = array_fill_keys(array_map('strval', array_keys($_ENV + $_SERVER)), true);
        $builder = new ContainerBuilder();
        try {
            (require dirname(__DIR__).'/src/settings.php')($builder);
        } finally {
            foreach (array_keys($_ENV + $_SERVER) as $name) {
                $key = (string) $name;
                if (!isset($beforeKeys[$key]) && !array_key_exists($key, $this->saved)) {
                    $this->saved[$key] = ['getenv' => false, 'env' => null, 'server' => null];
                }
            }
        }

        return $builder->build()->get('settings');
    }
}
