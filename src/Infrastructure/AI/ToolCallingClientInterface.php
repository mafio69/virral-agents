<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

interface ToolCallingClientInterface extends AiClientInterface
{
    /** @return array<string, mixed> Assistant message, including tool_calls when present. */
    public function generateWithTools(array $messages, array $tools, ?string $model = null): array;
}
