<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\AI\AiModel;
use PDO;
use RuntimeException;

final readonly class AiModelRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?AiModel
    {
        $stmt = $this->pdo->prepare($this->baseSelect().' WHERE m.id = :id AND m.deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? AiModel::fromRow($row) : null;
    }

    /** @return list<AiModel> posortowane: price_out → price_in → price_cache → name */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            $this->baseSelect().' WHERE m.deleted_at IS NULL
            ORDER BY m.price_out IS NULL, m.price_out,
                     m.price_in IS NULL, m.price_in,
                     m.price_cache IS NULL, m.price_cache,
                     m.name_display'
        )->fetchAll();

        return array_map(static fn(array $row) => AiModel::fromRow($row), $rows);
    }

    /** @return list<AiModel> */
    public function findByProviderId(int $providerId): array
    {
        $stmt = $this->pdo->prepare(
            $this->baseSelect().' WHERE m.provider_id = :provider_id AND m.deleted_at IS NULL ORDER BY m.name_display'
        );
        $stmt->execute(['provider_id' => $providerId]);
        $rows = $stmt->fetchAll();

        return array_map(static fn(array $row) => AiModel::fromRow($row), $rows);
    }

    /**
     * Tworzy model. Unikalność (provider_id, id_model, endpoint_url_id) chroni przed duplikatami.
     *
     * @param array<string,mixed> $extra
     */
    public function create(
        int $providerId,
        string $idModel,
        string $nameDisplay,
        int $endpointUrlId,
        ?int $urlId = null,
        ?string $description = null,
        string $category = 'llm',
        ?float $priceIn = null,
        ?float $priceOut = null,
        ?float $priceCache = null,
    ): AiModel {
        $this->assertUnique($providerId, $idModel, $endpointUrlId);

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_models
                (provider_id, id_model, name_display, endpoint_url_id, url_id, description, category,
                 price_in, price_out, price_cache, created_at, updated_at)
             VALUES
                (:provider_id, :id_model, :name_display, :endpoint_url_id, :url_id, :description, :category,
                 :price_in, :price_out, :price_cache, :created_at, :updated_at)'
        );
        $stmt->execute([
            'provider_id' => $providerId,
            'id_model' => $idModel,
            'name_display' => $nameDisplay,
            'endpoint_url_id' => $endpointUrlId,
            'url_id' => $urlId,
            'description' => $description,
            'category' => $category,
            'price_in' => $priceIn,
            'price_out' => $priceOut,
            'price_cache' => $priceCache,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById((int)$this->pdo->lastInsertId())
            ?? throw new RuntimeException('Model create failed');
    }

    public function update(AiModel $model): void
    {
        $this->assertUnique($model->providerId, $model->idModel, $model->endpointUrlId, $model->id);

        $stmt = $this->pdo->prepare(
            'UPDATE ai_models SET
                name_display = :name_display, endpoint_url_id = :endpoint_url_id, url_id = :url_id,
                description = :description, category = :category,
                price_in = :price_in, price_out = :price_out, price_cache = :price_cache,
                updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $model->id,
            'name_display' => $model->nameDisplay,
            'endpoint_url_id' => $model->endpointUrlId,
            'url_id' => $model->urlId,
            'description' => $model->description,
            'category' => $model->category,
            'price_in' => $model->priceIn,
            'price_out' => $model->priceOut,
            'price_cache' => $model->priceCache,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ai_models SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL"
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['id' => $id, 'deleted_at' => $now, 'updated_at' => $now]);
    }

    private function assertUnique(int $providerId, string $idModel, int $endpointUrlId, ?int $excludeId = null): void
    {
        $sql = 'SELECT COUNT(*) FROM ai_models
                WHERE provider_id = :provider_id AND id_model = :id_model
                  AND endpoint_url_id = :endpoint_url_id AND deleted_at IS NULL';
        $params = ['provider_id' => $providerId, 'id_model' => $idModel, 'endpoint_url_id' => $endpointUrlId];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new \App\Exception\ValidationException(
                sprintf('Model "%s" already exists for this provider', $idModel)
            );
        }
    }

    /** @return list<AiModel> Active models with an active provider and endpoint. */
    public function findAvailableAtEndpoint(string $endpoint): array
    {
        $stmt = $this->pdo->prepare($this->baseSelect().'
            INNER JOIN ai_providers p ON p.id = m.provider_id
            INNER JOIN ai_urls u ON u.id = m.endpoint_url_id
            WHERE m.deleted_at IS NULL AND p.deleted_at IS NULL AND u.deleted_at IS NULL
              AND rtrim(u.url, \'/\') = :endpoint
            ORDER BY m.name_display, m.id');
        $stmt->execute(['endpoint' => rtrim($endpoint, '/')]);
        return array_map(static fn(array $row) => AiModel::fromRow($row), $stmt->fetchAll());
    }

    public function findOrCreateDiscovered(AiModel $connection, string $apiId): AiModel
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ai_models (provider_id, id_model, name_display, endpoint_url_id, created_at, updated_at)
            SELECT :provider, :model, :name, :endpoint, :created, :updated
            WHERE NOT EXISTS (
                SELECT 1 FROM ai_models
                WHERE provider_id = :provider AND id_model = :model
                  AND endpoint_url_id = :endpoint AND deleted_at IS NULL
            )');
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'provider' => $connection->providerId, 'model' => $apiId, 'name' => $apiId,
            'endpoint' => $connection->endpointUrlId, 'created' => $now, 'updated' => $now,
        ]);
        foreach ($this->findByProviderId($connection->providerId) as $model) {
            if ($model->endpointUrlId === $connection->endpointUrlId && $model->idModel === $apiId) return $model;
        }
        throw new RuntimeException('Discovered model registration failed');
    }

    private function baseSelect(): string
    {
        return 'SELECT m.id, m.provider_id, m.id_model, m.name_display, m.endpoint_url_id, m.url_id,
                       m.description, m.category, m.price_in, m.price_out, m.price_cache
                FROM ai_models m';
    }
}
