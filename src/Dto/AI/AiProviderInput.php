<?php

declare(strict_types=1);

namespace App\Dto\AI;

final readonly class AiProviderInput
{
    public function __construct(
        public string $name,
        public string $secret,
        public string $endpointUrl,
        public ?string $modelId,
        public ?string $modelName,
        public ?string $description,
    ) {
    }
}
