<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\DataProtectionException;
use App\Infrastructure\DataProtection\DataProtectionMigration;
use App\Infrastructure\DataProtection\SodiumDataCipher;
use App\Infrastructure\Database\MigrationRunner;
use App\Repository\ChatRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class DataProtectionTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        (new MigrationRunner($pdo))->run();
        return $pdo;
    }

    private function cipher(): SodiumDataCipher
    {
        return new SodiumDataCipher(bin2hex(random_bytes(32)));
    }

    public function testRandomizedEncryptionPreservesEmptyAndUnicodeValues(): void
    {
        $cipher = $this->cipher();
        foreach (['', 'Zażółć gęślą jaźń', 'enc:v1:fixture text'] as $value) {
            $first = $cipher->encrypt($value, 'fixture');
            self::assertNotSame($first, $cipher->encrypt($value, 'fixture'));
            self::assertSame($value, $cipher->decrypt($first, 'fixture'));
        }
    }

    public function testCiphertextCannotMoveBetweenContexts(): void
    {
        $cipher = $this->cipher();
        $encrypted = $cipher->encrypt('fixture content', 'chat_messages.content:1');
        $this->expectException(DataProtectionException::class);
        $cipher->decrypt($encrypted, 'chat_messages.content:2');
    }

    public function testModifiedCiphertextIsRejected(): void
    {
        $cipher = $this->cipher();
        $encrypted = $cipher->encrypt('fixture content', 'fixture');
        $bytes = base64_decode(substr($encrypted, 7));
        $bytes[30] = chr(ord($bytes[30]) ^ 1);
        $this->expectException(DataProtectionException::class);
        $cipher->decrypt('enc:v1:'.base64_encode($bytes), 'fixture');
    }

    public function testPlaintextFallbackIsForbidden(): void
    {
        $this->expectException(DataProtectionException::class);
        $this->cipher()->decrypt('old plaintext', 'fixture');
    }

    public function testMissingKeyIsRejected(): void
    {
        $this->expectException(DataProtectionException::class);
        new SodiumDataCipher('');
    }

    public function testRepositoriesRequireMigration(): void
    {
        $this->expectException(DataProtectionException::class);
        new UserRepository($this->database(), $this->cipher());
    }

    public function testSensitiveFieldsNeverAppearInStoredRows(): void
    {
        $pdo = $this->database();
        $cipher = ProtectedDataFixture::cipher($pdo);
        $users = new UserRepository($pdo, $cipher);
        $chats = new ChatRepository($pdo, $cipher);
        $tasks = new TaskRepository($pdo, $cipher);
        $userId = $users->createUser('private-fixture@example.invalid', 'fixture password hash');
        $chatId = $chats->create($userId, 'private fixture chat title');
        $image = 'data:image/png;base64,fixture-image-data';
        $chats->addMessage($chatId, 'user', 'private fixture message', [$image]);
        $taskId = $tasks->create($userId, 'private fixture task title', 'private fixture description', 'todo');
        $stored = '';
        foreach (['users', 'chats', 'chat_messages', 'chat_message_images', 'tasks'] as $table) {
            $stored .= json_encode($pdo->query('SELECT * FROM '.$table)->fetchAll(), JSON_THROW_ON_ERROR);
        }
        foreach (['private-fixture@example.invalid', 'private fixture', $image] as $plaintext) {
            self::assertStringNotContainsString($plaintext, $stored);
        }
        self::assertSame('private-fixture@example.invalid', $users->findByEmail('private-fixture@example.invalid')['email']);
        self::assertSame('private fixture chat title', $chats->findById($chatId)['title']);
        self::assertSame('private fixture message', $chats->findMessagesByChatId($chatId)[0]['content']);
        self::assertSame([$image], $chats->findMessagesByChatId($chatId)[0]['images']);
        self::assertSame('private fixture description', $tasks->findById($taskId)['description']);
    }

    public function testSessionAndVerificationHashesAreNotBearerCredentials(): void
    {
        $pdo = $this->database();
        $users = new UserRepository($pdo, ProtectedDataFixture::cipher($pdo));
        $id = $users->createUser('fixture@example.invalid', 'fixture hash');
        $session = bin2hex(random_bytes(32));
        $verification = bin2hex(random_bytes(32));
        $users->createSession($id, $session, date('Y-m-d H:i:s', time() + 3600));
        $users->setVerificationToken($id, $verification);
        $storedSession = $pdo->query('SELECT token FROM sessions')->fetchColumn();
        $storedVerification = $pdo->query('SELECT email_verification_token FROM users')->fetchColumn();
        self::assertNotSame($session, $storedSession);
        self::assertNotSame($verification, $storedVerification);
        self::assertSame($id, (int)$users->findSessionByToken($session)['user_id']);
        self::assertSame($id, (int)$users->findByVerificationToken($verification)['id']);
        self::assertNull($users->findSessionByToken($storedSession));
        self::assertNull($users->findByVerificationToken($storedVerification));
    }

    public function testBatchAndUpdatePathsEncryptAndPreserveNull(): void
    {
        $pdo = $this->database();
        $cipher = ProtectedDataFixture::cipher($pdo);
        $users = new UserRepository($pdo, $cipher);
        $id = $users->createUser('fixture@example.invalid', 'fixture hash');
        $chats = new ChatRepository($pdo, $cipher);
        $chat = $chats->create($id, 'fixture chat');
        $chats->addMessagesBatch([['chat_id' => $chat, 'role' => 'user', 'content' => 'batch fixture content', 'created_at' => 'now']]);
        $tasks = new TaskRepository($pdo, $cipher);
        $ids = $tasks->createBatch([['user_id' => $id, 'title' => 'batch fixture title', 'description' => null, 'status' => 'todo', 'created_at' => 'now', 'updated_at' => 'now']]);
        self::assertNull($tasks->findById($ids[0])['description']);
        $tasks->update($ids[0], 'updated fixture title', 'updated fixture description', 'done');
        self::assertSame('updated fixture title', $tasks->findById($ids[0])['title']);
        self::assertSame('batch fixture content', $chats->findMessagesByChatId($chat)[0]['content']);
        self::assertStringStartsWith('enc:v1:', $pdo->query('SELECT title FROM tasks')->fetchColumn());
        self::assertStringStartsWith('enc:v1:', $pdo->query('SELECT content FROM chat_messages')->fetchColumn());
    }

    public function testChangingRecordOwnerBreaksAuthentication(): void
    {
        $pdo = $this->database();
        $cipher = ProtectedDataFixture::cipher($pdo);
        $chats = new ChatRepository($pdo, $cipher);
        $id = $chats->create(1, 'fixture title');
        $pdo->exec('UPDATE chats SET user_id = 2');
        $this->expectException(DataProtectionException::class);
        $chats->findById($id);
    }

    public function testMigrationPreservesLegacyDataAndIsIdempotent(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO users (id,email,password_hash,email_verification_token,created_at) VALUES (1,'legacy@example.invalid','fixture hash','fixture verification','now')");
        $pdo->exec("INSERT INTO chats (id,user_id,title,created_at,model) VALUES (1,1,'legacy title','now','fixture-model')");
        $pdo->exec("INSERT INTO chat_messages (id,chat_id,role,content,created_at) VALUES (1,1,'user','legacy message','now')");
        $pdo->exec("INSERT INTO chat_message_images (message_id,data_url) VALUES (1,'legacy image')");
        $pdo->exec("INSERT INTO tasks (id,user_id,title,description,status,created_at,updated_at) VALUES (1,1,'legacy task','legacy description','todo','now','now')");
        $pdo->exec("INSERT INTO sessions (user_id,token,created_at,expires_at) VALUES (1,'fixture session','now','later')");
        $cipher = $this->cipher();
        $migration = new DataProtectionMigration($pdo, $cipher);
        $migration->run();
        $before = $pdo->query('SELECT email_ciphertext FROM users')->fetchColumn();
        $migration->run();
        self::assertSame($before, $pdo->query('SELECT email_ciphertext FROM users')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
        $users = new UserRepository($pdo, $cipher);
        self::assertSame('legacy@example.invalid', $users->findByEmail('legacy@example.invalid')['email']);
        self::assertSame('fixture hash', $users->findById(1)['password_hash']);
        self::assertSame(1, (int)$users->findByVerificationToken('fixture verification')['id']);
        $chats = new ChatRepository($pdo, $cipher);
        self::assertSame('legacy title', $chats->findById(1)['title']);
        self::assertSame('legacy message', $chats->findMessagesByChatId(1)[0]['content']);
        self::assertSame(['legacy image'], $chats->findMessagesByChatId(1)[0]['images']);
        self::assertSame('legacy description', (new TaskRepository($pdo, $cipher))->findById(1)['description']);
    }

    public function testWrongKeyCannotReadOrRemigrateProtectedDatabase(): void
    {
        $pdo = $this->database();
        ProtectedDataFixture::cipher($pdo);
        $this->expectException(DataProtectionException::class);
        (new DataProtectionMigration($pdo, $this->cipher()))->run();
    }

    public function testFailedMigrationRollsBackAllDataAndMarker(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO users (id,email,password_hash,created_at) VALUES (1,'legacy@example.invalid','fixture hash','now')");
        $pdo->exec("INSERT INTO chats (id,user_id,title,created_at) VALUES (1,1,'legacy title','now')");
        $pdo->exec("CREATE TRIGGER fixture_failure BEFORE UPDATE ON chats BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
        try {
            (new DataProtectionMigration($pdo, $this->cipher()))->run();
            self::fail('Migration should fail');
        } catch (\PDOException) {
            self::assertSame('legacy@example.invalid', $pdo->query('SELECT email FROM users')->fetchColumn());
            self::assertNull($pdo->query('SELECT email_ciphertext FROM users')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM data_protection_state')->fetchColumn());
        }
    }

    public function testSwappingCiphertextBetweenRecordsIsRejected(): void
    {
        $pdo = $this->database();
        $cipher = ProtectedDataFixture::cipher($pdo);
        $chats = new ChatRepository($pdo, $cipher);
        $chats->create(1, 'first fixture');
        $second = $chats->create(1, 'second fixture');
        $pdo->exec('UPDATE chats SET title = (SELECT title FROM chats WHERE id = 1) WHERE id = 2');
        $this->expectException(DataProtectionException::class);
        $chats->findById($second);
    }

    public function testFailedProtectedInsertDoesNotLeavePlaceholder(): void
    {
        $pdo = $this->database();
        $cipher = ProtectedDataFixture::cipher($pdo);
        $chats = new ChatRepository($pdo, $cipher);
        $chat = $chats->create(1, 'fixture title');
        $pdo->exec("CREATE TRIGGER fixture_failure BEFORE UPDATE ON chat_message_images BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
        try {
            $chats->addMessage($chat, 'user', 'fixture content', ['fixture image']);
            self::fail('Insert should fail');
        } catch (\PDOException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM chat_message_images')->fetchColumn());
        }
    }

    public function testProductionContainerWiresMandatoryCipher(): void
    {
        $pdo = $this->database();
        $key = bin2hex(random_bytes(32));
        (new DataProtectionMigration($pdo, new SodiumDataCipher($key)))->run();
        $builder = new \DI\ContainerBuilder();
        (require __DIR__.'/../src/dependencies.php')($builder);
        $builder->addDefinitions([
            'settings' => ['user_data' => ['key' => $key], 'ai_registry' => ['key' => '']],
            PDO::class => $pdo,
        ]);
        $users = $builder->build()->get(UserRepository::class);
        $id = $users->createUser('container@example.invalid', 'fixture hash');
        self::assertSame('container@example.invalid', $users->findById($id)['email']);
        self::assertNotSame('container@example.invalid', $pdo->query('SELECT email FROM users')->fetchColumn());
    }

    public function testReusingProviderKeyIsRejectedByProductionFactory(): void
    {
        $key = bin2hex(random_bytes(32));
        $builder = new \DI\ContainerBuilder();
        (require __DIR__.'/../src/dependencies.php')($builder);
        $builder->addDefinitions(['settings' => ['user_data' => ['key' => $key], 'ai_registry' => ['key' => strtoupper($key)]]]);
        $this->expectException(DataProtectionException::class);
        $builder->build()->get(\App\Infrastructure\DataProtection\DataCipherInterface::class);
    }
}
