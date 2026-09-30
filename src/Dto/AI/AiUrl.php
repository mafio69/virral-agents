<?php

declare(strict_types=1);

namespace App\Dto\AI;

final readonly class AiUrl
{
    public function __construct(
        public int $id,
        public string $url,
        public ?string $description,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            url: (string)$row['url'],
            description: $row['description'] ?? null,
        );
    }
}
