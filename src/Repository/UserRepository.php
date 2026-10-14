<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\DataProtection\ProtectedStore;

final readonly class UserRepository
{
    private ProtectedStore $store;

    public function __construct(private readonly PDO $pdo, private DataCipherInterface $cipher)
    {
        $this->store = new ProtectedStore($pdo, $cipher);
    }

    public function createUser(string $email, string $passwordHash): int
    {
        return $this->store->insert('users', [
            'email' => $this->cipher->emailIndex($email),
            'email_ciphertext' => $email,
            'password_hash' => $passwordHash,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $this->cipher->emailIndex($email)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->decodeUser($result) : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->decodeUser($result) : null;
    }

    public function createSession(int $userId, string $token, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (user_id, token, created_at, expires_at) VALUES (:user_id, :token, :created_at, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
        ]);
    }

    public function findSessionByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sessions WHERE token = :token AND expires_at > :now');
        $stmt->execute([
            'token' => hash('sha256', $token),
            'now' => date('Y-m-d H:i:s'),
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function setVerificationToken(int $userId, string $token): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET email_verification_token = :token WHERE id = :id');
        $stmt->execute(['token' => hash('sha256', $token), 'id' => $userId]);
    }

    public function findByVerificationToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email_verification_token = :token');
        $stmt->execute(['token' => hash('sha256', $token)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->decodeUser($result) : null;
    }

    private function decodeUser(array $row): array
    {
        $row = $this->store->decrypt('users', $row);
        if (!isset($row['email_ciphertext']) || !hash_equals($row['email'], $this->cipher->emailIndex($row['email_ciphertext']))) {
            throw new \App\Exception\DataProtectionException();
        }
        $row['email'] = $row['email_ciphertext'];
        unset($row['email_ciphertext']);
        return $row;
    }

    public function verifyEmail(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_confirmed_at = :now, email_verification_token = NULL WHERE id = :id'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'id' => $userId]);
    }
}
