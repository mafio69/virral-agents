<?php

declare(strict_types=1);

namespace App\Service;

interface ModelCatalogInterface
{
    public function getConnections(): array;
    public function getAvailableModels(?string $connection = null): array;
    public function supportsImages(string $modelId): bool;
}
