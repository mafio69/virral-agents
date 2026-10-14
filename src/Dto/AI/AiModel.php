<?php

declare(strict_types=1);

namespace App\Dto\AI;

final readonly class AiModel
{
    public function __construct(
        public int $id,
        public int $providerId,
        public string $idModel,
        public string $nameDisplay,
        public int $endpointUrlId,
        public ?int $urlId,
        public ?string $description,
        public string $category,
        public ?float $priceIn,
        public ?float $priceOut,
        public ?float $priceCache,
        public ?string $connection = null,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            providerId: (int)$row['provider_id'],
            idModel: (string)$row['id_model'],
            nameDisplay: (string)$row['name_display'],
            endpointUrlId: (int)$row['endpoint_url_id'],
            urlId: isset($row['url_id']) ? (int)$row['url_id'] : null,
            description: $row['description'] ?? null,
            category: (string)($row['category'] ?? 'llm'),
            priceIn: isset($row['price_in']) ? (float)$row['price_in'] : null,
            priceOut: isset($row['price_out']) ? (float)$row['price_out'] : null,
            priceCache: isset($row['price_cache']) ? (float)$row['price_cache'] : null,
        );
    }
}
