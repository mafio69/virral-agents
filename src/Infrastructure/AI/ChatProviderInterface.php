<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

interface ChatProviderInterface extends AiClientInterface, ModelCatalogueInterface
{
    public function id(): string;
    public function name(): string;
    public function isConfigured(): bool;
}
