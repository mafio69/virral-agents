<?php
declare(strict_types=1);

/**
 * Konfiguracja – docelowo lepiej mieć to w .env
 * niż w kodzie. Tu, dla przykładu, w HEX.
 */
const SECRET_TOKEN_HEX = '0000000000000000000000000000000000000000000000000000000000000000';

/**
 * Mała klasa‑wartość na klucz – nie ma żadnego gettera,
 * służy tylko do wstrzykiwania do serwisów.
 */
final class SecretKey
{
    /** @var string binarny klucz dla sodium */
    private string $key;

    public function __construct(string $hexToken)
    {
        $bin = hex2bin($hexToken);
        if ($bin === false || strlen($bin) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Nieprawidłowy token HEX dla klucza');
        }

        $this->key = $bin;
    }

    /**
     * Tylko dla zaprzyjaźnionych klas w tym pliku.
     * Nie rób tu public gettera.
     */
    public function raw(): string
    {
        return $this->key;
    }
}

/**
 * Klasa odpowiedzialna wyłącznie za SZYFROWANIE.
 * Zwraca ciąg base64(nonce + ciphertext) – gotowy do zapisania w bazie.
 */
final class SecretEncryptor
{
    private SecretKey $key;

    public function __construct(SecretKey $key)
    {
        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $ciphertext = sodium_crypto_secretbox(
            $plaintext,
            $nonce,
            $this->key->raw()
        );

        return base64_encode($nonce . $ciphertext);
    }
}

/**
 * Klasa odpowiedzialna wyłącznie za ODSZYFROWANIE.
 * Przyjmuje ciąg base64(nonce + ciphertext) z bazy i
 * zwraca oryginalny tekst.
 */
final class SecretDecryptor
{
    private SecretKey $key;

    public function __construct(SecretKey $key)
    {
        $this->key = $key;
    }

    public function decrypt(string $encoded): string
    {
        $data = base64_decode($encoded, true);
        if ($data === false) {
            throw new RuntimeException('Nieprawidłowe dane base64');
        }

        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($data) <= $nonceLen) {
            throw new RuntimeException('Za krótkie dane zaszyfrowane');
        }

        $nonce      = substr($data, 0, $nonceLen);
        $ciphertext = substr($data, $nonceLen);

        $plaintext = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $this->key->raw()
        );

        if ($plaintext === false) {
            throw new RuntimeException('Nie można odszyfrować (zły klucz lub uszkodzone dane)');
        }

        return $plaintext;
    }
}

/**
 * Przykładowy „ScriptService”, który korzysta z obu serwisów.
 * Tu masz wzór użycia w aplikacji (np. przy pracy z bazą).
 */
final class ScriptSecretService
{
    private SecretEncryptor $encryptor;
    private SecretDecryptor $decryptor;

    public function __construct(SecretEncryptor $encryptor, SecretDecryptor $decryptor)
    {
        $this->encryptor = $encryptor;
        $this->decryptor = $decryptor;
    }

    /**
     * Szyfruje sekret przed zapisem do bazy.
     */
    public function storeSecret(string $plaintext): string
    {
        return $this->encryptor->encrypt($plaintext);
    }

    /**
     * Odszyfrowuje sekret z bazy do użycia w kodzie.
     */
    public function revealSecret(string $fromDb): string
    {
        return $this->decryptor->decrypt($fromDb);
    }
}

// ===== PRZYKŁAD KONSTRUKCJI SERWISU I UŻYCIA =====

// W „prawdziwym” kodzie klucz brałbyś z ENV:
$key      = new SecretKey(SECRET_TOKEN_HEX);
$encryptor = new SecretEncryptor($key);
$decryptor = new SecretDecryptor($key);

$service = new ScriptSecretService($encryptor, $decryptor);

// Szyfrowanie przed zapisem:
$apiKey        = 'sk_live_super_tajny_klucz_ai';
$encryptedForDb = $service->storeSecret($apiKey);

// Odczyt i odszyfrowanie:
$decoded = $service->revealSecret($encryptedForDb);

echo "Oryginał : {$apiKey}\n";
echo "Z bazy   : {$decoded}\n";
