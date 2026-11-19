<?php

declare(strict_types=1);

namespace App\Exception;

final class LoginRateLimitException extends \RuntimeException
{
    public function __construct(
        public readonly int $retryAfter,
        string $message = 'Zbyt wiele prób logowania. Spróbuj ponownie później.',
    ) {
        parent::__construct($message);
    }
}
