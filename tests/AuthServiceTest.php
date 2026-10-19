<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Auth\LoginInput;
use App\Dto\Auth\RegisterInput;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Service\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $authService;
    private \PDO $pdo;
    private \App\Repository\UserRepository $users;
    private string $verificationToken;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create tables
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                email_verification_token TEXT DEFAULT NULL,
                email_confirmed_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ');

        // Create service dependencies (mocked)
        $mailService = $this->createMock(\App\Infrastructure\Mail\MailService::class);
        $userRepository = new \App\Repository\UserRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $this->users = $userRepository;
        $mailService->method('send')->willReturnCallback(function (string $to, string $subject, string $body): void {
            preg_match('/token=([a-f0-9]{64})/', $body, $match);
            $this->verificationToken = $match[1];
        });
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->authService = new AuthService(
            $userRepository,
            $mailService,
            'http://localhost',
            $logger,
            new \App\Service\LoginAttemptLimiter(
                new \App\Repository\LoginAttemptRepository($this->pdo), ProtectedDataFixture::cipher($this->pdo)
            )
        );
    }

    public function testRegisterNewUser(): void
    {
        $input = new RegisterInput('test@example.com', 'password123');
        
        $this->authService->register($input);

        // Verify user was created
        $user = $this->users->findByEmail('test@example.com');

        $this->assertIsArray($user);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertNotEmpty($user['password_hash']);
        $this->assertNotEquals('password123', $user['password_hash']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $input = new RegisterInput('test@example.com', 'password123');
        $this->authService->register($input);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Ten adres email ma już konto. Zaloguj się; jeśli konto nie jest jeszcze potwierdzone, użyj linku z wiadomości potwierdzającej.');
        
        $this->authService->register($input);
    }

    public function testLoginValidCredentials(): void
    {
        // First register a user
        $registerInput = new RegisterInput('test@example.com', 'password123');
        $this->authService->register($registerInput);

        $this->authService->verifyEmail($this->verificationToken);

        // Now login
        $loginInput = new LoginInput('test@example.com', 'password123', false);
        $result = $this->authService->login($loginInput);

        $this->assertIsInt($result->userId);
        $this->assertIsString($result->token);
        $this->assertNotEmpty($result->token);
        $this->assertNotEmpty($result->expiresAt);
    }

    public function testLoginInvalidCredentials(): void
    {
        // First register a user
        $registerInput = new RegisterInput('test@example.com', 'password123');
        $this->authService->register($registerInput);

        // Try login with wrong password
        $loginInput = new LoginInput('test@example.com', 'wrongpassword', false);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Invalid credentials');
        
        $this->authService->login($loginInput);
    }

    public function testLoginNonExistentUser(): void
    {
        $loginInput = new LoginInput('nonexistent@example.com', 'password123', false);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Invalid credentials');
        
        $this->authService->login($loginInput);
    }

    public function testValidateTokenValid(): void
    {
        // Register and login to get a token
        $registerInput = new RegisterInput('test@example.com', 'password123');
        $this->authService->register($registerInput);

        $this->authService->verifyEmail($this->verificationToken);

        $loginInput = new LoginInput('test@example.com', 'password123', false);
        $result = $this->authService->login($loginInput);

        // Validate the token
        $userId = $this->authService->validateToken($result->token);

        $this->assertEquals($result->userId, $userId);
    }

    public function testValidateTokenInvalid(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Invalid or expired token');
        
        $this->authService->validateToken('invalid_token');
    }

    public function testVerifyEmailValidToken(): void
    {
        // Register a user
        $registerInput = new RegisterInput('test@example.com', 'password123');
        $this->authService->register($registerInput);

        // Use the token delivered to the fixture mailbox, not its stored hash
        $token = $this->verificationToken;

        // Verify email
        $this->authService->verifyEmail($token);

        // Check that email_confirmed_at is set
        $user = $this->users->findByEmail('test@example.com');

        $this->assertNotNull($user['email_confirmed_at']);
    }

    public function testVerifyEmailInvalidToken(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid verification token');
        
        $this->authService->verifyEmail('invalid_token');
    }
}