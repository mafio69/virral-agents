<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Auth\ForgotPasswordInput;
use App\Dto\Auth\LoginInput;
use App\Dto\Auth\RegisterInput;
use App\Dto\Auth\ResetPasswordInput;
use App\Dto\Auth\VerifyEmailInput;
use App\Exception\ValidationException;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Mail\MailService;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\LoginAttemptLimiter;
use App\Service\PasswordResetLimiter;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AuthServiceResetPasswordTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepository;
    private AuthService $authService;
    private ?string $lastSentBody = null;
    private ?string $lastSentTo = null;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        (new MigrationRunner($this->pdo))->run();

        $cipher = ProtectedDataFixture::cipher($this->pdo);
        $this->userRepository = new UserRepository($this->pdo, $cipher);

        $mailService = $this->createMock(MailService::class);
        $mailService->method('send')->willReturnCallback(function (string $to, string $subject, string $body): void {
            $this->lastSentTo = $to;
            $this->lastSentBody = $body;
        });

        $this->authService = new AuthService(
            $this->userRepository,
            $mailService,
            'http://localhost',
            $this->createMock(LoggerInterface::class),
            new LoginAttemptLimiter(new LoginAttemptRepository($this->pdo), $cipher),
            new PasswordResetLimiter(new LoginAttemptRepository($this->pdo), $cipher)
        );
    }

    public function testForgotPasswordNonExistentEmailReturnsTrueWithoutSending(): void
    {
        $this->lastSentBody = null;
        $result = $this->authService->forgotPassword(new ForgotPasswordInput('nonexistent@example.com'));

        $this->assertTrue($result);
        $this->assertNull($this->lastSentBody);
    }

    public function testForgotPasswordSendsResetLink(): void
    {
        $this->authService->register(new RegisterInput('user@example.com', 'securepass123'));
        preg_match('/token=([a-f0-9]{64})/', (string)$this->lastSentBody, $regMatch);
        $this->authService->verifyEmail(new VerifyEmailInput($regMatch[1]));

        $this->lastSentBody = null;
        $result = $this->authService->forgotPassword(new ForgotPasswordInput('user@example.com'));

        $this->assertTrue($result);
        $this->assertSame('user@example.com', $this->lastSentTo);
        $this->assertNotNull($this->lastSentBody);
        $this->assertMatchesRegularExpression('/\?reset_token=([a-f0-9]{64})/', $this->lastSentBody);
    }

    public function testForgotPasswordIsRateLimitedAfterThreeRequests(): void
    {
        $input = new ForgotPasswordInput('nonexistent@example.com');
        for ($i = 0; $i < 3; ++$i) {
            self::assertTrue($this->authService->forgotPassword($input));
        }
        $this->expectException(\App\Exception\LoginRateLimitException::class);
        $this->authService->forgotPassword($input);
    }

    public function testForgotPasswordLimitDoesNotAffectLogin(): void
    {
        $this->authService->register(new RegisterInput('user@example.com', 'securepass123'));
        preg_match('/token=([a-f0-9]{64})/', (string)$this->lastSentBody, $regMatch);
        $this->authService->verifyEmail(new VerifyEmailInput($regMatch[1]));

        for ($i = 0; $i < 3; ++$i) {
            $this->authService->forgotPassword(new ForgotPasswordInput('user@example.com'));
        }
        $result = $this->authService->login(new LoginInput('user@example.com', 'securepass123'));
        self::assertNotEmpty($result->token);
    }

    public function testResetPasswordUpdatesPasswordAndClearsTokenAndSessions(): void
    {
        $this->authService->register(new RegisterInput('user@example.com', 'oldpassword123'));
        preg_match('/token=([a-f0-9]{64})/', (string)$this->lastSentBody, $regMatch);
        $this->authService->verifyEmail(new VerifyEmailInput($regMatch[1]));

        // Login to create a session
        $loginResult = $this->authService->login(new LoginInput('user@example.com', 'oldpassword123'));
        $this->assertNotEmpty($loginResult->token);
        $this->assertSame(1, (int)$this->authService->validateToken($loginResult->token));

        // Request reset
        $this->authService->forgotPassword(new ForgotPasswordInput('user@example.com'));
        preg_match('/\?reset_token=([a-f0-9]{64})/', (string)$this->lastSentBody, $resetMatch);
        $resetToken = $resetMatch[1];

        // Reset password
        $this->authService->resetPassword(new ResetPasswordInput($resetToken, 'newpassword456'));

        // Old session must be invalid
        $this->expectException(\App\Exception\UnauthorizedException::class);
        $this->authService->validateToken($loginResult->token);
    }

    public function testLoginWithNewPasswordSucceedsAfterReset(): void
    {
        $this->authService->register(new RegisterInput('user@example.com', 'oldpassword123'));
        preg_match('/token=([a-f0-9]{64})/', (string)$this->lastSentBody, $regMatch);
        $this->authService->verifyEmail(new VerifyEmailInput($regMatch[1]));

        $this->authService->forgotPassword(new ForgotPasswordInput('user@example.com'));
        preg_match('/\?reset_token=([a-f0-9]{64})/', (string)$this->lastSentBody, $resetMatch);

        $this->authService->resetPassword(new ResetPasswordInput($resetMatch[1], 'newpassword456'));

        // Login with old password fails
        try {
            $this->authService->login(new LoginInput('user@example.com', 'oldpassword123'));
            $this->fail('Old password should not work');
        } catch (\App\Exception\UnauthorizedException) {
            // expected
        }

        // Login with new password succeeds
        $result = $this->authService->login(new LoginInput('user@example.com', 'newpassword456'));
        $this->assertNotEmpty($result->token);
    }

    public function testResetPasswordConfirmsUnconfirmedEmail(): void
    {
        $this->authService->register(new RegisterInput('user@example.com', 'oldpassword123'));

        $this->authService->forgotPassword(new ForgotPasswordInput('user@example.com'));
        preg_match('/\?reset_token=([a-f0-9]{64})/', (string)$this->lastSentBody, $resetMatch);
        $this->authService->resetPassword(new ResetPasswordInput($resetMatch[1], 'newpassword456'));

        $user = $this->userRepository->findByEmail('user@example.com');
        self::assertNotEmpty($user['email_confirmed_at']);
        self::assertNull($user['email_verification_token']);
        $result = $this->authService->login(new LoginInput('user@example.com', 'newpassword456'));
        self::assertNotEmpty($result->token);
    }

    public function testResetTokenCanBeUsedOnlyOnce(): void
    {
        $this->authService->register(new RegisterInput('user@example.com', 'oldpassword123'));
        $this->authService->forgotPassword(new ForgotPasswordInput('user@example.com'));
        preg_match('/\?reset_token=([a-f0-9]{64})/', (string)$this->lastSentBody, $resetMatch);

        $this->authService->resetPassword(new ResetPasswordInput($resetMatch[1], 'newpassword456'));
        $this->expectException(ValidationException::class);
        $this->authService->resetPassword(new ResetPasswordInput($resetMatch[1], 'otherpassword789'));
    }

    public function testResetPasswordWithInvalidTokenThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->authService->resetPassword(new ResetPasswordInput(str_repeat('a', 64), 'newpassword456'));
    }

    public function testResetPasswordWithExpiredTokenThrowsException(): void
    {
        $this->authService->register(new RegisterInput('user@example.com', 'oldpassword123'));
        preg_match('/token=([a-f0-9]{64})/', (string)$this->lastSentBody, $regMatch);
        $this->authService->verifyEmail(new VerifyEmailInput($regMatch[1]));

        $this->authService->forgotPassword(new ForgotPasswordInput('user@example.com'));
        preg_match('/\?reset_token=([a-f0-9]{64})/', (string)$this->lastSentBody, $resetMatch);
        $resetToken = $resetMatch[1];

        // Expire the token manually in SQLite
        $this->pdo->exec("UPDATE users SET password_reset_expires_at = datetime('now', '-10 minutes')");

        $this->expectException(ValidationException::class);
        $this->authService->resetPassword(new ResetPasswordInput($resetToken, 'newpassword456'));
    }
}
