<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AuthController;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Mail\MailService;
use App\Mapper\Auth\LoginInputMapper;
use App\Mapper\Auth\RegisterInputMapper;
use App\Repository\UserRepository;
use App\Service\AuthService;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthHttpTest extends TestCase
{
    private App $app;
    private \PDO $pdo;
    private MailService $mail;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', options: [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        (new MigrationRunner($this->pdo))->run();
        $this->mail = $this->createMock(MailService::class);
        $repository = new UserRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $logger = new NullLogger();
        $service = new AuthService($repository, $this->mail, 'https://fixture.example.invalid', $logger);
        $container = new Container([
            LoggerInterface::class => $logger,
            'settings' => ['displayErrorDetails' => false],
            AuthController::class => new AuthController(new RegisterInputMapper(), new LoginInputMapper(), $service, $repository),
        ]);
        $this->app = AppFactory::create(container: $container);
        (require __DIR__.'/../src/routes.php')($this->app);
        (require __DIR__.'/../src/middleware.php')($this->app);
    }

    private function request(string $method, string $path, ?array $body = null, array $cookies = [], string $scheme = 'http'): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $scheme.'://fixture.example.invalid'.$path)
            ->withCookieParams($cookies);
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

    private function credentials(): array
    {
        return ['email' => 'fixture@example.invalid', 'password' => 'fixture-password'];
    }

    public function testRegisterThenLoginAndAuthenticateUsingResponseCookie(): void
    {
        $this->mail->expects($this->once())->method('send');
        $registered = $this->request('POST', '/api/auth/register', $this->credentials());
        self::assertSame(201, $registered->getStatusCode());
        self::assertTrue($this->data($registered)['confirmation_sent']);
        $login = $this->request('POST', '/api/auth/login', $this->credentials());
        self::assertSame(200, $login->getStatusCode());
        $token = $this->data($login)['token'];
        self::assertStringContainsString('token='.$token.';', $login->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly; SameSite=Lax', $login->getHeaderLine('Set-Cookie'));
        $me = $this->request('GET', '/api/auth/me', cookies: ['token' => $token]);
        self::assertSame(200, $me->getStatusCode());
        self::assertSame('fixture@example.invalid', $this->data($me)['email']);
    }

    public function testHttpsLoginSetsSecureCookie(): void
    {
        $this->request('POST', '/api/auth/register', $this->credentials());
        $response = $this->request('POST', '/api/auth/login', $this->credentials(), scheme: 'https');
        self::assertStringContainsString('SameSite=Strict; Secure', $response->getHeaderLine('Set-Cookie'));
    }

    public function testMailFailureKeepsAccountAvailableForLogin(): void
    {
        $this->mail->expects($this->once())->method('send')->willThrowException(new \RuntimeException('Fixture mail unavailable'));
        $response = $this->request('POST', '/api/auth/register', $this->credentials());
        self::assertSame(201, $response->getStatusCode());
        self::assertFalse($this->data($response)['confirmation_sent']);
        self::assertSame(200, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
    }

    public function testRegistrationTrimsEmailBeforeValidationAndStorage(): void
    {
        $credentials = $this->credentials();
        $credentials['email'] = '  '.$credentials['email'].'  ';
        self::assertSame(201, $this->request('POST', '/api/auth/register', $credentials)->getStatusCode());
        self::assertSame(200, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/auth/register', $this->credentials())->getStatusCode());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testInvalidCredentialsAndExpiredSessionAreRejected(): void
    {
        $this->request('POST', '/api/auth/register', $this->credentials());
        self::assertSame(401, $this->request('POST', '/api/auth/login', [
            'email' => 'fixture@example.invalid', 'password' => 'incorrect-fixture-password',
        ])->getStatusCode());
        $login = $this->request('POST', '/api/auth/login', $this->credentials());
        $this->pdo->exec("UPDATE sessions SET expires_at = '2000-01-01 00:00:00'");
        self::assertSame(401, $this->request('GET', '/api/auth/me', cookies: ['token' => $this->data($login)['token']])->getStatusCode());
    }

    public function testRememberMeCreatesThirtyDaySession(): void
    {
        $this->request('POST', '/api/auth/register', $this->credentials());
        $response = $this->request('POST', '/api/auth/login', $this->credentials() + ['remember_me' => true]);
        self::assertSame(200, $response->getStatusCode());
        $expiry = $this->pdo->query('SELECT expires_at FROM sessions')->fetchColumn();
        self::assertEqualsWithDelta(time() + 30 * 86400, strtotime($expiry), 5);
    }

    public function testInvalidRegistrationDoesNotCreateAccount(): void
    {
        self::assertSame(422, $this->request('POST', '/api/auth/register', ['email' => 'invalid', 'password' => 'fixture-password'])->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/auth/register', ['email' => 'fixture@example.invalid', 'password' => 'short'])->getStatusCode());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}
