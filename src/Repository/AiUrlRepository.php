<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\AI\AiUrl;
use PDO;

final readonly class AiUrlRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?AiUrl
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, url, description FROM ai_urls WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? AiUrl::fromRow($row) : null;
    }

    public function findByUrl(string $url): ?AiUrl
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, url, description FROM ai_urls WHERE url = :url AND deleted_at IS NULL'
        );
        $stmt->execute(['url' => $url]);
        $row = $stmt->fetch();

        return $row ? AiUrl::fromRow($row) : null;
    }

    /**
     * Wyszukiwarka URL (aktywowana od 3. znaku): istnieje → zwróć, nie ma → dopisz.
     */
    public function findOrCreate(string $url, ?string $description = null): AiUrl
    {
        $url = trim($url);
        $existing = $this->findByUrl($url);
        if ($existing !== null) {
            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_urls (url, description, created_at, updated_at) VALUES (:url, :description, :created_at, :updated_at)'
        );
        $stmt->execute([
            'url' => $url,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new AiUrl((int)$this->pdo->lastInsertId(), $url, $description);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ai_urls SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL"
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['id' => $id, 'deleted_at' => $now, 'updated_at' => $now]);
    }
}
