<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Repository\LoginAttemptRepositoryInterface;
use Closure;

final readonly class LoginAttemptLimiter implements LoginAttemptLimiterInterface
{
    private const LIMIT = 5;
    private const WINDOW = 900;
    private Closure $clock;

    public function __construct(
        private LoginAttemptRepositoryInterface $repository,
        private DataCipherInterface $cipher,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? time(...);
    }

    public function reserve(string $email, string $clientIp): int
    {
        $packedIp = inet_pton($clientIp);
        $ip = $packedIp === false ? 'unknown' : bin2hex($packedIp);
        return $this->repository->reserve(
            $this->cipher->emailIndex('login-limit/email/'.strtolower(trim($email))),
            $this->cipher->emailIndex('login-limit/ip/'.$ip),
            ($this->clock)(),
            self::LIMIT,
            self::WINDOW,
        );
    }

    public function release(int $attemptId): void
    {
        $this->repository->release($attemptId);
    }
}
