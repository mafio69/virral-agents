<?php

declare(strict_types=1);

namespace App\Dto\AI;

final readonly class AiModelInput
{
    public function __construct(
        public int $providerId,
        public string $idModel,
        public string $nameDisplay,
        public string $endpointUrl,
    ) {
    }
}
