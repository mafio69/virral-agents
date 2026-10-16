<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

final class MigrationRunner
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS data_protection_state (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    version INTEGER NOT NULL,
    key_check TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id INTEGER NOT NULL,
    role TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_message_images (
    message_id INTEGER PRIMARY KEY,
    data_url TEXT NOT NULL,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS research_workspaces (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    chat_id INTEGER NOT NULL DEFAULT 0,
    payload TEXT NOT NULL,
    version INTEGER NOT NULL DEFAULT 0,
    UNIQUE (user_id, chat_id)
);

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    status TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ai_urls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT DEFAULT NULL
);
CREATE TABLE IF NOT EXISTS ai_providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    providers_secret TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    url_id INTEGER DEFAULT NULL,
    docs_url_id INTEGER DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT DEFAULT NULL
);
CREATE TABLE IF NOT EXISTS ai_models (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER NOT NULL,
    id_model TEXT NOT NULL,
    name_display TEXT NOT NULL,
    endpoint_url_id INTEGER NOT NULL,
    url_id INTEGER DEFAULT NULL,
    description TEXT DEFAULT NULL,
    category TEXT NOT NULL DEFAULT 'llm',
    price_in REAL DEFAULT NULL,
    price_out REAL DEFAULT NULL,
    price_cache REAL DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT DEFAULT NULL
);
SQL;

        $this->pdo->exec($sql);

        $this->addColumnIfNotExists('users', 'email_ciphertext', 'TEXT DEFAULT NULL');
        $this->addColumnIfNotExists('users', 'email_verification_token', 'TEXT DEFAULT NULL');
        $this->addColumnIfNotExists('users', 'email_confirmed_at', 'TEXT DEFAULT NULL');
        $this->addColumnIfNotExists('chats', 'model', "TEXT DEFAULT '".\App\Infrastructure\AI\OvhModelDefaults::MODEL."'");
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

        if (!in_array($column, $columns, true)) {
            $this->pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    }
}
