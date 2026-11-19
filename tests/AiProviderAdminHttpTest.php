<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AiAdminController;
use App\Controller\AuthController;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Mail\MailService;
use App\Infrastructure\Secrets\SecretDecryptor;
use App\Infrastructure\Secrets\SecretEncryptor;
use App\Infrastructure\Secrets\SecretKey;
use App\Infrastructure\Secrets\SecretService;
use App\Mapper\AI\AiModelInputMapper;
use App\Mapper\AI\AiProviderInputMapper;
use App\Mapper\AI\UpdateProviderInputMapper;
use App\Mapper\Auth\LoginInputMapper;
use App\Mapper\Auth\RegisterInputMapper;
use App\Repository\AiModelRepository;
use App\Repository\AiProviderRepository;
use App\Repository\AiUrlRepository;
use App\Repository\UserRepository;
use App\Service\AiProviderService;
use App\Service\AuthService;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AiProviderAdminHttpTest extends TestCase
{
    private App $app;
    private string $verificationToken;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:', options: [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        (new MigrationRunner($pdo))->run();

        $cipher = ProtectedDataFixture::cipher($pdo);
        $mail = $this->createMock(MailService::class);
        $mail->method('send')->willReturnCallback(function (string $to, string $subject, string $body): void {
            preg_match('/token=([a-f0-9]{64})/', $body, $match);
            $this->verificationToken = $match[1];
        });

        $logger = new NullLogger();
        $auth = new AuthService(
            new UserRepository($pdo, $cipher),
            $mail,
            'https://fixture.example.invalid',
            $logger,
            new \App\Service\LoginAttemptLimiter(new \App\Repository\LoginAttemptRepository($pdo), $cipher),
            new \App\Service\PasswordResetLimiter(new \App\Repository\LoginAttemptRepository($pdo), $cipher),
        );

        $key = new SecretKey(str_repeat('a', 64));
        $secretService = new SecretService(new SecretEncryptor($key), new SecretDecryptor($key));
        $providerService = new AiProviderService(
            new AiProviderRepository($pdo, $secretService),
            new AiModelRepository($pdo),
            new AiUrlRepository($pdo),
            $logger,
        );

        $container = new Container([
            LoggerInterface::class => $logger,
            'settings' => ['displayErrorDetails' => false],
            AuthController::class => new AuthController(
                new RegisterInputMapper(),
                new LoginInputMapper(),
                $auth,
                new \App\Http\SessionTokenResolver(),
                'dev'
            ),
            \App\Service\SessionAuthenticatorInterface::class => $auth,
            AiAdminController::class => new AiAdminController(
                $providerService,
                new AiProviderInputMapper(),
                new UpdateProviderInputMapper(),
                new AiModelInputMapper()
            ),
        ]);
        $this->app = AppFactory::create(container: $container);
        (require __DIR__.'/../src/routes.php')($this->app);
        (require __DIR__.'/../src/middleware.php')($this->app);
    }

    private function request(string $method, string $path, ?array $body = null, string $bearer = ''): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://fixture.example.invalid'.$path, ['REMOTE_ADDR' => '192.0.2.1']);
        if ($bearer !== '') {
            $request = $request->withHeader('Authorization', 'Bearer '.$bearer);
        }
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        }

        return $this->app->handle($request);
    }

    private function data(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Registers, confirms and logs in; returns the session token. */
    private function login(string $email): string
    {
        $credentials = ['email' => $email, 'password' => 'fixture-password'];
        self::assertSame(201, $this->request('POST', '/api/auth/register', $credentials)->getStatusCode());
        self::assertSame(200, $this->request('GET', '/api/auth/verify-email?token='.$this->verificationToken)->getStatusCode());

        return $this->data($this->request('POST', '/api/auth/login', $credentials))['token'];
    }

    public function testAdminCanManageProvidersAndSecretIsNeverExposed(): void
    {
        $admin = $this->login('admin@example.invalid');
        $secret = 'sk-secret-'.bin2hex(random_bytes(8));

        self::assertSame([], $this->data($this->request('GET', '/api/ai-providers', bearer: $admin)));

        $created = $this->request('POST', '/api/ai-providers', [
            'name' => 'Fixture Provider',
            'secret' => $secret,
            'endpoint_url' => 'https://api.fixture.invalid/',
            'model_id' => 'fixture-model',
        ], $admin);
        self::assertSame(201, $created->getStatusCode());
        $provider = $this->data($created);
        self::assertTrue($provider['has_secret']);
        self::assertArrayNotHasKey('providers_secret', $provider);
        self::assertStringNotContainsString($secret, (string)$created->getBody());

        $listed = $this->data($this->request('GET', '/api/ai-providers', bearer: $admin));
        self::assertCount(1, $listed);
        self::assertSame('Fixture Provider', $listed[0]['name']);
        self::assertCount(1, $listed[0]['models']);
        self::assertSame('fixture-model', $listed[0]['models'][0]['id_model']);
        self::assertStringNotContainsString($secret, json_encode($listed, JSON_THROW_ON_ERROR));

        $providerId = $provider['id'];
        self::assertSame(200, $this->request('DELETE', '/api/ai-providers/'.$providerId, bearer: $admin)->getStatusCode());
        self::assertSame([], $this->data($this->request('GET', '/api/ai-providers', bearer: $admin)));
    }

    public function testNonAdminGets404OnAdminEndpoints(): void
    {
        $this->login('admin@example.invalid');
        $member = $this->login('member@example.invalid');

        self::assertSame(404, $this->request('GET', '/api/ai-providers', bearer: $member)->getStatusCode());
        self::assertSame(404, $this->request('POST', '/api/ai-providers', ['name' => 'x', 'secret' => 'y', 'endpoint_url' => 'https://x.invalid'], $member)->getStatusCode());
    }

    public function testAdminEndpointsRequireSession(): void
    {
        self::assertSame(401, $this->request('GET', '/api/ai-providers')->getStatusCode());
        self::assertSame(401, $this->request('POST', '/api/ai-providers', ['name' => 'x', 'secret' => 'y', 'endpoint_url' => 'https://x.invalid'])->getStatusCode());
    }
}
