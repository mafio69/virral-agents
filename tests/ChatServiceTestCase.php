<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Secrets\SecretDecryptor;
use App\Infrastructure\Secrets\SecretEncryptor;
use App\Infrastructure\Secrets\SecretKey;
use App\Infrastructure\Secrets\SecretService;
use App\Repository\AiModelRepository;
use App\Repository\AiProviderRepository;
use App\Repository\AiUrlRepository;
use App\Repository\ChatRepository;
use PHPUnit\Framework\TestCase;

/**
 * Wspólny setup bazy in-memory dla testów czatu / modeli AI.
 * Sekret dostawcy: wyłącznie DUMMY wartość.
 */
abstract class ChatServiceTestCase extends TestCase
{
    protected \PDO $pdo;
    protected ChatRepository $chatRepository;
    protected AiModelRepository $aiModelRepository;
    protected AiProviderRepository $aiProviderRepository;
    protected AiUrlRepository $aiUrlRepository;
    protected SecretService $secretService;
    protected int $userId;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE chats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                created_at TEXT NOT NULL,
                model_legacy TEXT DEFAULT NULL,
                model_id INTEGER DEFAULT NULL REFERENCES ai_models(id)
            );
            CREATE TABLE chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
            );
            CREATE TABLE ai_urls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL UNIQUE,
                description TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL
            );
            CREATE TABLE ai_providers (
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
            CREATE TABLE ai_models (
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
SQL);

        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['test@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $this->userId = (int)$this->pdo->lastInsertId();

        // Dummy 32B hex — nigdy prawdziwy klucz w testach.
        $key = new SecretKey(str_repeat('ab', 32));
        $this->secretService = new SecretService(
            new SecretEncryptor($key),
            new SecretDecryptor($key),
        );

        $this->chatRepository = new ChatRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $this->aiUrlRepository = new AiUrlRepository($this->pdo);
        $this->aiProviderRepository = new AiProviderRepository($this->pdo, $this->secretService);
        $this->aiModelRepository = new AiModelRepository($this->pdo);
    }

    /** Tworzy dostawcę + URL + model; zwraca id modelu. */
    protected function createModelFixture(
        string $idModel = 'glm-5',
        string $nameDisplay = 'GLM 5',
        ?float $priceOut = null,
    ): int {
        $url = $this->aiUrlRepository->findOrCreate('https://api.example.com/v1');
        static $providerSeq = 0;
        $provider = $this->aiProviderRepository->create('TestProvider-'.++$providerSeq, 'sk_dummy_secret', 'Test provider');

        return $this->aiModelRepository->create(
            providerId: $provider->id,
            idModel: $idModel,
            nameDisplay: $nameDisplay,
            endpointUrlId: $url->id,
            priceOut: $priceOut,
        )->id;
    }
}
