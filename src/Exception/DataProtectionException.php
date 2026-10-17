<?php

declare(strict_types=1);

namespace App\Exception;

final class DataProtectionException extends \RuntimeException
{
    public const KEY_MISSING = 'user_data_key_missing';
    public const KEY_INVALID = 'user_data_key_invalid';
    public const KEY_REUSED = 'user_data_key_reused';
    public const SODIUM_UNAVAILABLE = 'sodium_unavailable';

    public readonly ?string $reason;

    public function __construct(?string $reason = null)
    {
        $this->reason = in_array($reason, [self::KEY_MISSING, self::KEY_INVALID, self::KEY_REUSED, self::SODIUM_UNAVAILABLE], true)
            ? $reason : null;
        parent::__construct('Data protection unavailable');
    }
}
