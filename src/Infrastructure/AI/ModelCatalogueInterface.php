<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

interface ModelCatalogueInterface
{
    /** @return list<array{id: string, supports_images: bool}> */
    public function listModels(): array;
}
