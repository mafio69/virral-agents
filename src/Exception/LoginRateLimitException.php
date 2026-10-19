<?php

declare(strict_types=1);

namespace App\Exception;

final class LoginRateLimitException extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Zbyt wiele prób logowania. Spróbuj ponownie później.');
    }
}
