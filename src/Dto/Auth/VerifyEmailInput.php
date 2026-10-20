<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final readonly class VerifyEmailInput
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
    ) {
    }
}
