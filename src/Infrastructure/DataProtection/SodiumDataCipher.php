<?php

declare(strict_types=1);

namespace App\Infrastructure\DataProtection;

use App\Exception\DataProtectionException;

final readonly class SodiumDataCipher implements DataCipherInterface
{
    private const PREFIX = 'enc:v1:';
    private string $key;
    private string $lookupKey;

    public function __construct(#[\SensitiveParameter] string $hexKey)
    {
        if (!extension_loaded('sodium') || !preg_match('/^[a-fA-F0-9]{64}$/D', $hexKey)) {
            throw new DataProtectionException();
        }
        $this->key = hex2bin($hexKey);
        $this->lookupKey = hash_hkdf('sha256', $this->key, 32, 'virralAI/email-index/v1');
    }

    public function encrypt(#[\SensitiveParameter] string $value, string $context): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($value, self::PREFIX.$context, $nonce, $this->key);
        return self::PREFIX.base64_encode($nonce.$ciphertext);
    }

    public function decrypt(#[\SensitiveParameter] string $value, string $context): string
    {
        if (!str_starts_with($value, self::PREFIX)) throw new DataProtectionException();
        $bytes = base64_decode(substr($value, strlen(self::PREFIX)), true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($bytes === false || strlen($bytes) < $nonceLength + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new DataProtectionException();
        }
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($bytes, $nonceLength), self::PREFIX.$context, substr($bytes, 0, $nonceLength), $this->key,
        );
        if ($plaintext === false) throw new DataProtectionException();
        return $plaintext;
    }

    public function emailIndex(#[\SensitiveParameter] string $email): string
    {
        return hash_hmac('sha256', $email, $this->lookupKey);
    }
}
