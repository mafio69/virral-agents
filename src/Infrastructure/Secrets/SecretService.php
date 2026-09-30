<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

/**
 * Fasada do przechowywania i odszyfrowywania sekretów w bazie.
 */
final readonly class SecretService
{
    public function __construct(
        private SecretEncryptor $encryptor,
        private SecretDecryptor $decryptor,
    ) {
    }

    public function storeSecret(string $plaintext): string
    {
        return $this->encryptor->encrypt($plaintext);
    }

    public function revealSecret(string $fromDb): string
    {
        return $this->decryptor->decrypt($fromDb);
    }
}
