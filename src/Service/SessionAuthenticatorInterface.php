<?php

declare(strict_types=1);

namespace App\Service;

interface SessionAuthenticatorInterface
{
    public function validateToken(string $token): int;
}
