<?php

declare(strict_types=1);

namespace App\Dto\AI;

/**
 * Dostawca AI. providersSecret jest ZASZYFROWANY (base64 nonce+ciphertext).
 * Odszyfrowanie wyłącznie przez SecretService w AiClientFactory.
 */
final readonly class AiProvider
{
    public function __construct(
        public int $id,
        public string $name,
        public string $providersSecret,
        public ?string $description,
        public ?int $urlId,
        public ?int $docsUrlId,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            name: (string)$row['name'],
            providersSecret: (string)$row['providers_secret'],
            description: $row['description'] ?? null,
            urlId: isset($row['url_id']) ? (int)$row['url_id'] : null,
            docsUrlId: isset($row['docs_url_id']) ? (int)$row['docs_url_id'] : null,
        );
    }
}
