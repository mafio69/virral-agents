<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\LoginRateLimitException;
use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Repository\LoginAttemptRepositoryInterface;
use Closure;

/**
 * Limits password reset e-mails: 3 requests per 15 minutes, separately per e-mail and IP.
 * Shares the login_attempts table with LoginAttemptLimiter, but under different HMAC prefixes,
 * so reset requests and failed logins never consume each other's budget.
 */
final readonly class PasswordResetLimiter
{
    private const LIMIT = 3;
    private const WINDOW = 900;
    private const MESSAGE = 'Zbyt wiele prób wysłania linku resetującego hasło. Spróbuj ponownie później.';
    private Closure $clock;

    public function __construct(
        private LoginAttemptRepositoryInterface $repository,
        private DataCipherInterface $cipher,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? time(...);
    }

    /** Every request consumes the budget; there is no release, unlike a successful login. */
    public function reserve(string $email, string $clientIp): void
    {
        $packedIp = inet_pton($clientIp);
        $ip = $packedIp === false ? 'unknown' : bin2hex($packedIp);
        try {
            $this->repository->reserve(
                $this->cipher->emailIndex('reset-limit/email/'.strtolower(trim($email))),
                $this->cipher->emailIndex('reset-limit/ip/'.$ip),
                ($this->clock)(),
                self::LIMIT,
                self::WINDOW,
            );
        } catch (LoginRateLimitException $exception) {
            throw new LoginRateLimitException($exception->retryAfter, self::MESSAGE);
        }
    }
}
