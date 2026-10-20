<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\AI\AiProvider;
use App\Exception\ValidationException;
use App\Infrastructure\Secrets\SecretService;
use PDO;

final readonly class AiProviderRepository
{
    public function __construct(
        private PDO $pdo,
        private SecretService $secretService,
    ) {
    }

    public function findById(int $id): ?AiProvider
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, providers_secret, description, url_id, docs_url_id FROM ai_providers WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? AiProvider::fromRow($row) : null;
    }

    /** @return list<AiProvider> */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, providers_secret, description, url_id, docs_url_id FROM ai_providers WHERE deleted_at IS NULL ORDER BY name'
        )->fetchAll();

        return array_map(static fn (array $row) => AiProvider::fromRow($row), $rows);
    }

    /**
     * Tworzy dostawcę. $secretPlaintext jest szyfrowany przed zapisem —
     * plaintext nigdy nie trafia do bazy ani do logów.
     */
    public function create(
        string $name,
        string $secretPlaintext,
        ?string $description = null,
        ?int $urlId = null,
        ?int $docsUrlId = null,
    ): AiProvider {
        $this->assertNameUnique($name);

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_providers (name, providers_secret, description, url_id, docs_url_id, created_at, updated_at)
             VALUES (:name, :providers_secret, :description, :url_id, :docs_url_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'name' => $name,
            'providers_secret' => $this->secretService->storeSecret($secretPlaintext),
            'description' => $description,
            'url_id' => $urlId,
            'docs_url_id' => $docsUrlId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById((int)$this->pdo->lastInsertId())
            ?? throw new \RuntimeException('Provider create failed');
    }

    public function update(AiProvider $provider, ?string $secretPlaintext = null): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE ai_providers SET name = :name, description = :description, url_id = :url_id, docs_url_id = :docs_url_id, updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $provider->id,
            'name' => $provider->name,
            'description' => $provider->description,
            'url_id' => $provider->urlId,
            'docs_url_id' => $provider->docsUrlId,
            'updated_at' => $now,
        ]);

        if ($secretPlaintext !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE ai_providers SET providers_secret = :providers_secret, updated_at = :updated_at WHERE id = :id'
            );
            $stmt->execute([
                'id' => $provider->id,
                'providers_secret' => $this->secretService->storeSecret($secretPlaintext),
                'updated_at' => $now,
            ]);
        }
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ai_providers SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL"
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['id' => $id, 'deleted_at' => $now, 'updated_at' => $now]);
    }

    private function assertNameUnique(string $name): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ai_providers WHERE name = :name AND deleted_at IS NULL'
        );
        $stmt->execute(['name' => $name]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new ValidationException(sprintf('Provider "%s" already exists', $name));
        }
    }
}
