<?php

declare(strict_types=1);

namespace App\Mapper\Auth;

use App\Dto\Auth\ResetPasswordInput;
use App\Exception\ValidationException;

final readonly class ResetPasswordInputMapper
{
    public function map(array $data): ResetPasswordInput
    {
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new ValidationException('Invalid reset token');
        }

        if (!is_string($password) || mb_strlen($password, 'UTF-8') < 10) {
            throw new ValidationException('Password must be at least 10 characters');
        }
        if (strlen($password) > 72) {
            // bcrypt truncates at 72 bytes; a longer password would silently lose its tail and match a different password with the same prefix.
            throw new ValidationException('Password must be at most 72 bytes');
        }

        return new ResetPasswordInput($token, $password);
    }
}
