<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

final class ConnectionFactory
{
    public function __construct(private readonly string $dbPath)
    {
    }

    public function create(): PDO
    {
        $dir = dirname($this->dbPath);
        // Another process may create the directory between the check and mkdir().
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Database directory could not be created.');
        }

        $pdo = new PDO('sqlite:'.$this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable foreign keys
        $pdo->exec('PRAGMA foreign_keys = ON;');

        return $pdo;
    }
}
