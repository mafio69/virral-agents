<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\LoginRateLimitException;
use PDO;
use Throwable;

final readonly class LoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function reserve(string $emailKey, string $ipKey, int $now, int $limit, int $window): int
    {
        // Serialize the check and reservation across PHP workers, before password verification.
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $cleanup = $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at <= :cutoff');
            $cleanup->execute(['cutoff' => $now - $window]);
            $retryAfter = 0;
            foreach (['email_key' => $emailKey, 'ip_key' => $ipKey] as $column => $key) {
                $query = $this->pdo->prepare(
                    "SELECT COUNT(*) AS total, MIN(attempted_at) AS oldest FROM login_attempts WHERE $column = :key"
                );
                $query->execute(['key' => $key]);
                $row = $query->fetch(PDO::FETCH_ASSOC);
                if ((int)$row['total'] >= $limit) {
                    $retryAfter = max($retryAfter, (int)$row['oldest'] + $window - $now);
                }
            }
            if ($retryAfter > 0) {
                $this->pdo->exec('COMMIT');
                throw new LoginRateLimitException($retryAfter);
            }
            $insert = $this->pdo->prepare(
                'INSERT INTO login_attempts (email_key, ip_key, attempted_at) VALUES (:email, :ip, :now)'
            );
            $insert->execute(['email' => $emailKey, 'ip' => $ipKey, 'now' => $now]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->exec('COMMIT');
            return $id;
        } catch (LoginRateLimitException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->pdo->exec('ROLLBACK');
            throw $exception;
        }
    }

    public function release(int $attemptId): void
    {
        $delete = $this->pdo->prepare('DELETE FROM login_attempts WHERE id = :id');
        $delete->execute(['id' => $attemptId]);
    }
}
