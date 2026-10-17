<?php

declare(strict_types=1);

namespace App\Dto\Chat;

final readonly class ModelSelector
{
    public function __construct(public string $value)
    {
    }

    public function isRegistry(): bool
    {
        return str_contains($this->value, ':');
    }

    public function provider(): string
    {
        return $this->isRegistry() ? explode(':', $this->value, 2)[0] : 'ovh';
    }

    public function modelId(): string
    {
        return $this->isRegistry() ? explode(':', $this->value, 2)[1] : $this->value;
    }

    public function isLegacyGlm(): bool
    {
        return !$this->isRegistry() && str_starts_with($this->value, 'glm-');
    }
}
