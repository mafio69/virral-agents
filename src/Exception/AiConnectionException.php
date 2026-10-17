<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class AiConnectionException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('AI provider connection failed');
    }
}
