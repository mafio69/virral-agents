<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Database\ConnectionFactory;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/virral-connection-'.bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (['/nested/deeper/app.sqlite', '/existing.sqlite'] as $file) {
            if (is_file($this->root.$file)) {
                unlink($this->root.$file);
            }
        }
        foreach (['/nested/deeper', '/nested'] as $dir) {
            if (is_dir($this->root.$dir)) {
                rmdir($this->root.$dir);
            }
        }
        if (is_file($this->root.'/blocker')) {
            unlink($this->root.'/blocker');
        }
        rmdir($this->root);
    }

    public function testCreatesMissingNestedDirectoryAndEnablesForeignKeys(): void
    {
        $pdo = (new ConnectionFactory($this->root.'/nested/deeper/app.sqlite'))->create();
        self::assertDirectoryExists($this->root.'/nested/deeper');
        self::assertSame(1, (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn());
    }

    public function testExistingDirectoryIsReused(): void
    {
        $pdo = (new ConnectionFactory($this->root.'/existing.sqlite'))->create();
        self::assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }

    public function testUncreatableDirectoryFailsWithGenericMessage(): void
    {
        // A regular file where the directory should be makes mkdir() fail on every platform.
        file_put_contents($this->root.'/blocker', 'fixture');
        $warnings = 0;
        set_error_handler(static function () use (&$warnings): bool {
            $warnings++;
            return true;
        }, E_WARNING);
        try {
            (new ConnectionFactory($this->root.'/blocker/db/app.sqlite'))->create();
            self::fail('Expected directory creation failure');
        } catch (\RuntimeException $error) {
            self::assertSame('Database directory could not be created.', $error->getMessage());
            self::assertStringNotContainsString($this->root, $error->getMessage());
        } finally {
            restore_error_handler();
        }
        self::assertSame(1, $warnings);
    }
}
