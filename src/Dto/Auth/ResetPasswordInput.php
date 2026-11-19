<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final readonly class ResetPasswordInput
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        #[\SensitiveParameter] public string $password,
    ) {
    }
}
