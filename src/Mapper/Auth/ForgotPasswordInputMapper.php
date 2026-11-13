<?php

declare(strict_types=1);

namespace App\Mapper\Auth;

use App\Dto\Auth\ForgotPasswordInput;
use App\Exception\ValidationException;

final readonly class ForgotPasswordInputMapper
{
    public function map(array $data): ForgotPasswordInput
    {
        $email = $data['email'] ?? '';

        if (!is_string($email) || trim($email) === '') {
            throw new ValidationException('Email is required');
        }

        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }

        return new ForgotPasswordInput($email);
    }
}
