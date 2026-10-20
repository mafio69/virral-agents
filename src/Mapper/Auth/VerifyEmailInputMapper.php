<?php

declare(strict_types=1);

namespace App\Mapper\Auth;

use App\Dto\Auth\VerifyEmailInput;
use App\Exception\ValidationException;

final readonly class VerifyEmailInputMapper
{
    public function map(array $query): VerifyEmailInput
    {
        $token = $query['token'] ?? '';
        if (!is_string($token) || $token === '') {
            throw new ValidationException('Token is required');
        }

        return new VerifyEmailInput($token);
    }
}
