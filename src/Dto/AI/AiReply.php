<?php

declare(strict_types=1);

namespace App\Dto\AI;

final readonly class AiReply
{
    /** @param list<string> $images */
    public function __construct(public string $text, public array $images = []) {}
}
