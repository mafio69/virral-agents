<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiModel;

interface ChatModelRegistryInterface
{
    /** @return list<array<string, mixed>> */
    public function listModels(): array;
    public function resolve(string $selector): AiModel;
    public function clientFor(AiModel $model): AiClientInterface;
}
