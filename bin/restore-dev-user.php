<?php

declare(strict_types=1);

// Development-only owner tool: restores the starter admin account. Never runs in production.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const DEV_ADMIN_EMAIL = 'admin@mafio.pl';

try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $builder = new \DI\ContainerBuilder();
    (require dirname(__DIR__).'/src/settings.php')($builder);
    (require dirname(__DIR__).'/src/dependencies.php')($builder);
    $container = $builder->build();

    if (($container->get('settings')['environment'] ?? 'prod') !== 'dev') {
        fwrite(STDERR, "prod operacja nie dozwolona\n");
        exit(1);
    }

    $password = '';
    foreach ([getenv('DEV_ADMIN_PASSWORD'), $_ENV['DEV_ADMIN_PASSWORD'] ?? null, $_SERVER['DEV_ADMIN_PASSWORD'] ?? null] as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '' && !str_starts_with($candidate, 'encrypted:')) {
            $password = $candidate;
            break;
        }
    }
    if (strlen($password) < 10) {
        fwrite(STDERR, "Ustaw DEV_ADMIN_PASSWORD (minimum 10 znaków) w lokalnym środowisku przed uruchomieniem skryptu.\n");
        exit(1);
    }

    $pdo = $container->get(PDO::class);
    $cipher = $container->get(\App\Infrastructure\DataProtection\DataCipherInterface::class);
    (new \App\Infrastructure\DataProtection\DataProtectionMigration($pdo, $cipher))->assertReady();
    $users = $container->get(\App\Repository\UserRepository::class);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    unset($password);
    $pdo->beginTransaction();
    $user = $users->findByEmail(DEV_ADMIN_EMAIL);
    if ($user === null) {
        $id = $users->createUser(DEV_ADMIN_EMAIL, $hash);
        $action = 'Utworzono konto';
    } else {
        $id = (int)$user['id'];
        $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $update->execute(['hash' => $hash, 'id' => $id]);
        $action = 'Zaktualizowano hasło konta';
    }
    // Login requires a confirmed email; the dev account must not depend on SMTP.
    $users->verifyEmail($id);
    $pdo->prepare('DELETE FROM sessions WHERE user_id = :id')->execute(['id' => $id]);
    // Failed-login limits are keyed by hashed email/IP; in dev a full reset avoids a lockout.
    $pdo->exec('DELETE FROM login_attempts');
    $pdo->commit();

    fwrite(STDOUT, $action.' '.DEV_ADMIN_EMAIL.". Sesje i blokady logowania wyczyszczone. Zmień hasło po zalogowaniu.\n");
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // PDO messages may contain operation data; do not print them or the trace.
    fwrite(STDERR, "Nie udało się przywrócić konta dev. Sprawdź konfigurację i przygotowanie bazy.\n");
    exit(1);
}
