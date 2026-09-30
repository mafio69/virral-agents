<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

/**
 * Szyfrowanie sekretów przed zapisem do bazy.
 * Zwraca base64(nonce + ciphertext).
 */
final readonly class SecretEncryptor
{
    public function __construct(private SecretKey $key)
    {
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $ciphertext = sodium_crypto_secretbox(
            $plaintext,
            $nonce,
            $this->key->raw(),
        );

        return base64_encode($nonce.$ciphertext);
    }
}
