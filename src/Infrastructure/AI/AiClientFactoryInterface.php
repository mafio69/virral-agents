<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiModel;

interface AiClientFactoryInterface
{
    public function fromModel(AiModel $model): AiClientInterface;
}
