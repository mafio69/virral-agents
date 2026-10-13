<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Auth\LoginInput;
use App\Dto\Auth\LoginResult;
use App\Dto\Auth\RegisterInput;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Infrastructure\Mail\MailService;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

final readonly class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly string $appUrl,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function register(RegisterInput $input): bool
    {
        $existingUser = $this->userRepository->findByEmail($input->email);
        if ($existingUser) {
            $this->logger->warning('Register failed: email taken');
            throw new ValidationException('Ten adres email ma już konto. Zaloguj się.');
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
        } catch (\Throwable) {
            // Konto jest już zapisane. Błąd SMTP nie oznacza nieudanej rejestracji.
            // Nie zapisujemy adresu, linku aktywacyjnego ani wyjątku transportu w logach.
            $this->logger->warning('Registration confirmation delivery failed');
        }
        $this->logger->info('User registered');
        return $confirmationSent;
    }

    public function login(LoginInput $input): LoginResult
    {
        $user = $this->userRepository->findByEmail($input->email);

        if (!$user || !password_verify($input->password, $user['password_hash'])) {
            $this->logger->warning('Login failed');
            throw new UnauthorizedException('Invalid credentials');
        }

        $token = bin2hex(random_bytes(32));
        $hours = $input->rememberMe ? 720 : 24;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$hours hours"));

        $this->userRepository->createSession((int)$user['id'], $token, $expiresAt);

        return new LoginResult((int)$user['id'], $token, $expiresAt);
    }

    public function validateToken(string $token): int
    {
        $session = $this->userRepository->findSessionByToken($token);
        if (!$session) {
            throw new UnauthorizedException('Invalid or expired token');
        }

        return (int)$session['user_id'];
    }

    public function verifyEmail(string $token): void
    {
        $user = $this->userRepository->findByVerificationToken($token);
        if (!$user) {
            throw new ValidationException('Invalid verification token');
        }
        $this->userRepository->verifyEmail((int)$user['id']);
    }
}
