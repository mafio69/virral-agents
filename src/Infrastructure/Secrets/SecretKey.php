<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use RuntimeException;

/**
 * Value object przechowujący binarny klucz szyfrowania.
 * Bez publicznego gettera poza raw() — klucz nie powinien wyciekać poza moduł.
 */
final readonly class SecretKey
{
    private string $key;

    public function __construct(string $hexToken)
    {
        $bin = hex2bin($hexToken);
        if ($bin === false || strlen($bin) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Invalid HEX key for secrets encryption');
        }

        $this->key = $bin;
    }

    public function raw(): string
    {
        return $this->key;
    }
}
