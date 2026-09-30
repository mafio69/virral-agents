<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use RuntimeException;

/**
 * Odszyfrowanie sekretu z bazy (base64(nonce + ciphertext)).
 * Wynik używany wyłącznie w runtime (fabryka klienta AI) — nigdy nie logowany.
 */
final readonly class SecretDecryptor
{
    public function __construct(private SecretKey $key)
    {
    }

    public function decrypt(string $encoded): string
    {
        $data = base64_decode($encoded, true);
        if ($data === false) {
            throw new RuntimeException('Invalid base64 encrypted data');
        }

        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($data) <= $nonceLen) {
            throw new RuntimeException('Encrypted data too short');
        }

        $nonce = substr($data, 0, $nonceLen);
        $ciphertext = substr($data, $nonceLen);

        $plaintext = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $this->key->raw(),
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt (wrong key or corrupted data)');
        }

        return $plaintext;
    }
}
