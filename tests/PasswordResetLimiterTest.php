<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\LoginRateLimitException;
use App\Repository\LoginAttemptRepository;
use App\Service\LoginAttemptLimiter;
use App\Service\PasswordResetLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

final class PasswordResetLimiterTest extends TestCase
{
    private PDO $pdo;
    private int $now = 10000;
    private PasswordResetLimiter $limiter;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->limiter = $this->newLimiter();
    }

    private function newLimiter(): PasswordResetLimiter
    {
        return new PasswordResetLimiter(
            new LoginAttemptRepository($this->pdo),
            ProtectedDataFixture::cipher($this->pdo),
            fn () => $this->now
        );
    }

    public function testFourthRequestWithinWindowIsBlocked(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        try {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
            self::fail('Fourth request must be blocked');
        } catch (LoginRateLimitException $error) {
            self::assertGreaterThan(0, $error->retryAfter);
            self::assertStringContainsString('wysłania', $error->getMessage());
        }
    }

    public function testLoginLimiterBucketsDoNotCollideWithResetBuckets(): void
    {
        $loginLimiter = new LoginAttemptLimiter(
            new LoginAttemptRepository($this->pdo),
            ProtectedDataFixture::cipher($this->pdo),
            fn () => $this->now
        );
        for ($i = 0; $i < 5; ++$i) {
            $loginLimiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        // Five failed logins do not consume the reset budget: all three requests pass.
        for ($i = 0; $i < 3; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        $this->expectException(LoginRateLimitException::class);
        $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
    }

    public function testIpBucketIsSharedAcrossDifferentEmails(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->limiter->reserve("fixture-$i@example.invalid", '192.0.2.7');
        }
        $this->expectException(LoginRateLimitException::class);
        $this->limiter->reserve('other@example.invalid', '192.0.2.7');
    }

    public function testExpiredWindowReleasesTheLimit(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        $this->now += 901;
        // After the window a full budget of three requests is available again.
        for ($i = 0; $i < 3; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        $this->expectException(LoginRateLimitException::class);
        $this->newLimiter()->reserve('fixture@example.invalid', '192.0.2.1');
    }
}
