<?php

declare(strict_types=1);

namespace App\Mapper\Auth;

use App\Dto\Auth\RegisterInput;
use App\Exception\ValidationException;

final readonly class RegisterInputMapper
{
    public function map(array $data): RegisterInput
    {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!is_string($email) || trim($email) === '') {
            throw new ValidationException('Email is required');
        }

        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }

        if (!is_string($password) || strlen($password) < 6) {
            throw new ValidationException('Password must be at least 6 characters');
        }

        return new RegisterInput(trim($email), $password);
    }
}
