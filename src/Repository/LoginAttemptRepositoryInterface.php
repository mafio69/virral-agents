<?php

declare(strict_types=1);

namespace App\Repository;

interface LoginAttemptRepositoryInterface
{
    public function reserve(string $emailKey, string $ipKey, int $now, int $limit, int $window): int;

    public function release(int $attemptId): void;
}
