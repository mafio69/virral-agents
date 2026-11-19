<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final readonly class ForgotPasswordInput
{
    public function __construct(
        public string $email,
    ) {
    }
}
