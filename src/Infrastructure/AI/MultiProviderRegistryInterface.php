<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

interface MultiProviderRegistryInterface extends ChatModelRegistryInterface
{
    public function connections(): array;
    public function listFor(string $connection): array;
    public function supportsImages(string $selector): bool;
}
