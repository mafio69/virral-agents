<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

interface SearchClientInterface
{
    public function definition(): array;
    public function search(string $query, int $maxResults): array;
    public function close(): void;
}
