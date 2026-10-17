<?php

declare(strict_types=1);

// Narzędzie właściciela uruchamiane lokalnie. Nie jest endpointem aplikacji ani narzędziem modelu.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!stream_isatty(STDIN)) {
    fwrite(STDERR, "Uruchom skrypt w interaktywnym terminalu. Nie przekazuj hasła argumentem ani potokiem.\n");
    exit(1);
}

function terminalMode(array $arguments): string
{
    $process = proc_open(['stty', ...$arguments], [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Nie można zabezpieczyć pola hasła.');
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) throw new RuntimeException('Nie można zabezpieczyć pola hasła.');
    return trim($result);
}

function hiddenPassword(string $prompt): string
{
    $mode = terminalMode(['-g']);
    if (!preg_match('/^[a-fA-F0-9:]+$/D', $mode)) throw new RuntimeException('Nieobsługiwany terminal.');
    terminalMode(['-echo']);
    $restored = false;
    $restore = static function () use ($mode, &$restored): void {
        if (!$restored) {
            $restored = true;
            terminalMode([$mode]);
        }
    };
    register_shutdown_function($restore);
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static function (): void { exit(130); });
        pcntl_signal(SIGTERM, static function (): void { exit(143); });
    }
    fwrite(STDOUT, $prompt);
    try {
        $value = fgets(STDIN);
        if ($value === false) throw new RuntimeException('Przerwano wpisywanie hasła.');
        return rtrim($value, "\r\n");
    } finally {
        $restore();
        fwrite(STDOUT, "\n");
    }
}

try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $builder = new \DI\ContainerBuilder();
    (require dirname(__DIR__).'/src/settings.php')($builder);
    (require dirname(__DIR__).'/src/dependencies.php')($builder);
    $container = $builder->build();
    $cipher = $container->get(\App\Infrastructure\DataProtection\DataCipherInterface::class);
    $settings = $container->get('settings');
    $path = $settings['db']['path'];
    if (!is_file($path)) throw new RuntimeException('Brak istniejącej bazy aplikacji. Nie utworzono nowej.');
    $pdo = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    (new \App\Infrastructure\DataProtection\DataProtectionMigration($pdo, $cipher))->assertReady();
    fwrite(STDOUT, "Email istniejącego konta: ");
    $line = fgets(STDIN);
    if ($line === false) throw new RuntimeException('Przerwano.');
    $email = trim($line);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Nieprawidłowy email.');
    $find = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $find->execute(['email' => $cipher->emailIndex($email)]);
    $id = $find->fetchColumn();
    if ($id === false) throw new RuntimeException('Nie znaleziono konta. Utwórz je przez formularz rejestracji.');

    $password = hiddenPassword('Nowe hasło (minimum 6 znaków): ');
    $confirmation = hiddenPassword('Powtórz nowe hasło: ');
    if (strlen($password) < 6 || trim($password) === '') throw new RuntimeException('Hasło jest za krótkie.');
    if (!hash_equals($password, $confirmation)) throw new RuntimeException('Hasła nie są takie same.');
    if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password) > 72) throw new RuntimeException('Hasło może mieć najwyżej 72 bajty dla obecnego algorytmu.');
    $hash = password_hash($password, PASSWORD_DEFAULT);
    unset($password, $confirmation);
    $pdo->beginTransaction();
    $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $update->execute(['hash' => $hash, 'id' => (int)$id]);
    if ($update->rowCount() !== 1) throw new RuntimeException('Konto nie jest już dostępne. Nie zapisano zmiany.');
    $sessions = $pdo->prepare('DELETE FROM sessions WHERE user_id = :id');
    $sessions->execute(['id' => (int)$id]);
    $pdo->commit();
    unset($hash);
    fwrite(STDOUT, "Hasło zmienione. Poprzednie sesje unieważnione. Możesz się zalogować.\n");
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    // Komunikaty PDO mogą zawierać dane operacji — nie wypisujemy ich ani trace.
    $message = $error instanceof PDOException ? 'Nie udało się zapisać zmiany w bazie.'
        : ($error::class === RuntimeException::class ? $error->getMessage() : 'Nie udało się wykonać resetu. Sprawdź konfigurację i przygotowanie bazy.');
    fwrite(STDERR, $message."\n");
    exit(1);
}
