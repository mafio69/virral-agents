<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Auth\ForgotPasswordInput;
use App\Dto\Auth\LoginInput;
use App\Dto\Auth\LoginResult;
use App\Dto\Auth\RegisterInput;
use App\Dto\Auth\ResetPasswordInput;
use App\Dto\Auth\VerifyEmailInput;
use App\Exception\EmailConfirmationRequiredException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Infrastructure\Mail\MailService;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

final readonly class AuthService implements SessionAuthenticatorInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly string $appUrl,
        private readonly LoggerInterface $logger,
        private LoginAttemptLimiterInterface $loginLimiter,
        private PasswordResetLimiter $passwordResetLimiter,
    ) {
    }

    public function register(RegisterInput $input): bool
    {
        $existingUser = $this->userRepository->findByEmail($input->email);
        if ($existingUser) {
            $this->logger->warning('Register failed: email taken');
            // Same message for confirmed and unconfirmed accounts: no confirmation state is disclosed.
            throw new ValidationException('Ten adres email ma już konto. Zaloguj się; jeśli konto nie jest jeszcze potwierdzone, użyj linku z wiadomości potwierdzającej.');
        }

        $passwordHash = password_hash($input->password, PASSWORD_DEFAULT);
        $userId = $this->userRepository->createUser($input->email, $passwordHash);

        $token = bin2hex(random_bytes(32));
        $this->userRepository->setVerificationToken($userId, $token);

        $link = $this->appUrl.'/api/auth/verify-email?token='.$token;
        $body = '<p>Kliknij w link aby potwierdzić email:</p><p><a href="'.$link.'">'.$link.'</a></p>';
        $confirmationSent = false;
        try {
            $this->mailService->send($input->email, 'Potwierdź rejestrację', $body);
            $confirmationSent = true;
        } catch (\Throwable $e) {
            // Konto jest już zapisane. Błąd SMTP nie oznacza nieudanej rejestracji.
            // Nie zapisujemy adresu, linku aktywacyjnego ani wyjątku transportu w logach.
            $this->logger->warning('Registration confirmation delivery failed', [
                'user_id' => $userId,
                'type' => get_class($e),
                'origin' => basename($e->getFile()).':'.$e->getLine(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ]);
        }
        $this->logger->info('User registered');
        return $confirmationSent;
    }

    public function login(LoginInput $input, string $clientIp = 'unknown'): LoginResult
    {
        $attemptId = $this->loginLimiter->reserve($input->email, $clientIp);
        $user = $this->userRepository->findByEmail($input->email);

        if (!$user || !password_verify($input->password, $user['password_hash'])) {
            $this->logger->warning('Login failed');
            throw new UnauthorizedException('Invalid credentials');
        }

        $this->requireConfirmedEmail($user);

        $token = bin2hex(random_bytes(32));
        $hours = $input->rememberMe ? 720 : 24;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$hours hours"));

        if (!$this->userRepository->createSessionIfPasswordUnchanged((int)$user['id'], $user['password_hash'], $token, $expiresAt)) {
            // A password reset committed between the credential check and this session creation.
            $this->logger->warning('Login failed: password changed during sign-in');
            throw new UnauthorizedException('Invalid credentials');
        }

        $this->loginLimiter->release($attemptId);
        return new LoginResult((int)$user['id'], $token, $expiresAt);
    }

    public function validateToken(string $token): int
    {
        $session = $this->userRepository->findSessionByToken($token);
        if (!$session) {
            throw new UnauthorizedException('Invalid or expired token');
        }

        $user = $this->userRepository->findById((int)$session['user_id']);
        if (!$user) {
            throw new UnauthorizedException('Invalid or expired token');
        }
        $this->requireConfirmedEmail($user);

        return (int)$user['id'];
    }

    public function roleOf(int $userId): string
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new UnauthorizedException('Invalid or expired token');
        }

        return (string)($user['role'] ?? 'user');
    }

    public function logout(string $token): void
    {
        $this->userRepository->deleteSession($token);
    }

    /** @return array{id: int, email: string, role: string} */
    public function currentUser(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new UnauthorizedException('Invalid or expired token');
        }

        return ['id' => (int)$user['id'], 'email' => (string)$user['email'], 'role' => (string)($user['role'] ?? 'user')];
    }

    public function verifyEmail(VerifyEmailInput $input): void
    {
        $user = $this->userRepository->findByVerificationToken($input->token);
        if (!$user) {
            throw new ValidationException('Invalid verification token');
        }
        $this->userRepository->verifyEmail((int)$user['id']);
    }

    public function forgotPassword(ForgotPasswordInput $input, string $clientIp = 'unknown'): bool
    {
        // The limit applies before the lookup, so blocked responses do not reveal whether the account exists.
        $this->passwordResetLimiter->reserve($input->email, $clientIp);
        $user = $this->userRepository->findByEmail($input->email);
        if (!$user) {
            $this->logger->info('Password reset requested for non-existent email');
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->userRepository->setPasswordResetToken((int)$user['id'], $token, $expiresAt);

        $link = $this->appUrl.'/?reset_token='.$token;
        $body = '<p>Otrzymaliśmy prośbę o zresetowanie hasła.</p>'
            .'<p>Kliknij w poniższy link, aby ustawić nowe hasło (link jest ważny przez 1 godzinę):</p>'
            .'<p><a href="'.$link.'">'.$link.'</a></p>'
            .'<p>Jeśli to nie Ty wysłałeś prośbę, możesz zignorować tę wiadomość.</p>';

        $userId = (int)$user['id'];
        $email = $input->email;
        $deliver = function () use ($userId, $email, $body): void {
            try {
                $this->mailService->send($email, 'Reset hasła w Virral Agents', $body);
                $this->logger->info('Password reset email sent');
            } catch (\Throwable $e) {
                $this->logger->warning('Password reset delivery failed', [
                    'user_id' => $userId,
                    'type' => get_class($e),
                    'origin' => basename($e->getFile()).':'.$e->getLine(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ]);
            }
        };

        if (\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg') {
            // CLI/tests: deliver synchronously so the caller observes the mail.
            $deliver();
        } else {
            // FPM: deliver after the response is flushed (public/index.php calls fastcgi_finish_request),
            // so an existing and a missing account answer with the same timing (no enumeration signal).
            register_shutdown_function($deliver);
        }

        return true;
    }

    public function resetPassword(ResetPasswordInput $input): void
    {
        $user = $this->userRepository->findByPasswordResetToken($input->token);
        if (!$user) {
            $this->logger->warning('Password reset failed: invalid or expired token');
            throw new ValidationException('Nieprawidłowy lub wygasły link resetowania hasła.');
        }

        $passwordHash = password_hash($input->password, PASSWORD_DEFAULT);
        // A concurrent request may have consumed the same token in the meantime.
        if (!$this->userRepository->resetPasswordWithToken((int)$user['id'], $input->token, $passwordHash)) {
            $this->logger->warning('Password reset failed: token already used');
            throw new ValidationException('Nieprawidłowy lub wygasły link resetowania hasła.');
        }

        $this->logger->info('Password successfully reset');
    }

    private function requireConfirmedEmail(array $user): void
    {
        if (empty($user['email_confirmed_at'])) {
            throw new EmailConfirmationRequiredException();
        }
    }
}
