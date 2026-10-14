<?php

declare(strict_types=1);

namespace App\Exception;

final class DataProtectionException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Data protection unavailable');
    }
}
