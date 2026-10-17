<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

/**
 * Wspólny kontrakt klienta AI (OpenAI-compatible).
 */
interface AiClientInterface
{
    /**
     * @param list<array{role: string, content: string|list<array<string, mixed>>}> $messages
     */
    public function generate(array $messages, ?string $model = null): string;
}
