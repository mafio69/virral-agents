<?php

declare(strict_types=1);

namespace App\Service;

interface LoginAttemptLimiterInterface
{
    public function reserve(string $email, string $clientIp): int;

    public function release(int $attemptId): void;
}
