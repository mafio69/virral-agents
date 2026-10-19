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
    private string $verificationToken;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', options: [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        (new MigrationRunner($this->pdo))->run();
        $this->mail = $this->createMock(MailService::class);
        $this->mail->method('send')->willReturnCallback(function (string $to, string $subject, string $body): void {
            preg_match('/token=([a-f0-9]{64})/', $body, $match);
            $this->verificationToken = $match[1];
        });
        $repository = new UserRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $logger = new NullLogger();
        $service = new AuthService($repository, $this->mail, 'https://fixture.example.invalid', $logger, new \App\Service\LoginAttemptLimiter(
            new \App\Repository\LoginAttemptRepository($this->pdo), ProtectedDataFixture::cipher($this->pdo)
        ));
        $container = new Container([
            LoggerInterface::class => $logger,
            'settings' => ['displayErrorDetails' => false],
            AuthController::class => new AuthController(new RegisterInputMapper(), new LoginInputMapper(), $service, $repository, new \App\Http\SessionTokenResolver()),
            \App\Service\SessionAuthenticatorInterface::class => $service,
        ]);
        $this->app = AppFactory::create(container: $container);
        (require __DIR__.'/../src/routes.php')($this->app);
        (require __DIR__.'/../src/middleware.php')($this->app);
    }

    private function request(string $method, string $path, ?array $body = null, array $cookies = [], string $scheme = 'http', string $bearer = '', string $ip = '192.0.2.1', string $forwarded = ''): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $scheme.'://fixture.example.invalid'.$path, ['REMOTE_ADDR' => $ip])
            ->withCookieParams($cookies)
            ->withHeader('X-Forwarded-For', $forwarded);
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

    private function credentials(): array
    {
        return ['email' => 'fixture@example.invalid', 'password' => 'fixture-password'];
    }

    private function confirmEmail(): void
    {
        self::assertSame(200, $this->request(
            'GET', '/api/auth/verify-email?token='.$this->verificationToken
        )->getStatusCode());
    }

    private function registerConfirmed(): void
    {
        self::assertSame(201, $this->request('POST', '/api/auth/register', $this->credentials())->getStatusCode());
        $this->confirmEmail();
    }

    public function testEveryPrivateRouteRejectsMissingSessionBeforeControllerResolution(): void
    {
        foreach ([['GET', '/api/auth/me'], ['GET', '/api/tasks'], ['POST', '/api/tasks'],
            ['PUT', '/api/tasks/1'], ['DELETE', '/api/tasks/1'], ['GET', '/api/chats'],
            ['POST', '/api/chats'], ['GET', '/api/chats/1'], ['POST', '/api/chats/1/messages'],
            ['GET', '/api/search/status'], ['GET', '/api/research'],
            ['POST', '/api/research/actions'], ['POST', '/api/research/decisions'],
            ['PUT', '/api/research/sources'], ['POST', '/api/research/prepare']] as [$method, $path]) {
            self::assertSame(401, $this->request($method, $path)->getStatusCode(), $path);
        }
    }

    public function testBearerTakesPrecedenceOverCookieAndMalformedCookieIsRejected(): void
    {
        $this->registerConfirmed();
        $token = $this->data($this->request('POST', '/api/auth/login', $this->credentials()))['token'];
        self::assertSame(200, $this->request('GET', '/api/auth/me', bearer: $token)->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/auth/me', cookies: ['token' => $token], bearer: 'stale-fixture')->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/auth/me', cookies: ['token' => ['bad']])->getStatusCode());
    }

    public function testRegisterThenLoginAndAuthenticateUsingResponseCookie(): void
    {
        $this->mail->expects($this->once())->method('send');
        $registered = $this->request('POST', '/api/auth/register', $this->credentials());
        self::assertSame(201, $registered->getStatusCode());
        self::assertTrue($this->data($registered)['confirmation_sent']);
        self::assertSame(403, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
        $this->confirmEmail();
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
        $this->registerConfirmed();
        $response = $this->request('POST', '/api/auth/login', $this->credentials(), scheme: 'https');
        self::assertStringContainsString('SameSite=Strict; Secure', $response->getHeaderLine('Set-Cookie'));
    }

    public function testLogoutRevokesOnlyPresentedSessionAndClearsHttpOnlyCookie(): void
    {
        $this->registerConfirmed();
        $first = $this->data($this->request('POST', '/api/auth/login', $this->credentials()))['token'];
        $second = $this->data($this->request('POST', '/api/auth/login', $this->credentials()))['token'];
        $logout = $this->request('POST', '/api/auth/logout', cookies: ['token' => $first], scheme: 'https');
        self::assertSame(204, $logout->getStatusCode());
        self::assertStringContainsString('token=; Expires=Thu, 01 Jan 1970', $logout->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly; SameSite=Strict; Secure', $logout->getHeaderLine('Set-Cookie'));
        self::assertSame('no-store', $logout->getHeaderLine('Cache-Control'));
        self::assertSame(401, $this->request('GET', '/api/auth/me', cookies: ['token' => $first])->getStatusCode());
        self::assertSame(200, $this->request('GET', '/api/auth/me', cookies: ['token' => $second])->getStatusCode());
        self::assertSame(204, $this->request('POST', '/api/auth/logout', cookies: ['token' => $first])->getStatusCode());
        self::assertSame(204, $this->request('POST', '/api/auth/logout')->getStatusCode());
    }

    public function testMailFailureKeepsAccountButDoesNotAllowLogin(): void
    {
        $this->mail->expects($this->once())->method('send')->willThrowException(new \RuntimeException('Fixture mail unavailable'));
        $response = $this->request('POST', '/api/auth/register', $this->credentials());
        self::assertSame(201, $response->getStatusCode());
        self::assertFalse($this->data($response)['confirmation_sent']);
        self::assertSame(403, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
    }

    public function testLogoutClearsCookieSessionEvenWhenBrowserHasStaleBearer(): void
    {
        $this->registerConfirmed();
        $token = $this->data($this->request('POST', '/api/auth/login', $this->credentials()))['token'];
        self::assertSame(204, $this->request('POST', '/api/auth/logout', cookies: ['token' => $token], bearer: 'stale-fixture')->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/auth/me', cookies: ['token' => $token])->getStatusCode());
    }

    public function testRegistrationTrimsEmailBeforeValidationAndStorage(): void
    {
        $credentials = $this->credentials();
        $credentials['email'] = '  '.$credentials['email'].'  ';
        self::assertSame(201, $this->request('POST', '/api/auth/register', $credentials)->getStatusCode());
        $this->confirmEmail();
        self::assertSame(200, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/auth/register', $this->credentials())->getStatusCode());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testInvalidCredentialsAndExpiredSessionAreRejected(): void
    {
        $this->registerConfirmed();
        self::assertSame(401, $this->request('POST', '/api/auth/login', [
            'email' => 'fixture@example.invalid', 'password' => 'incorrect-fixture-password',
        ])->getStatusCode());
        $login = $this->request('POST', '/api/auth/login', $this->credentials());
        $this->pdo->exec("UPDATE sessions SET expires_at = '2000-01-01 00:00:00'");
        self::assertSame(401, $this->request('GET', '/api/auth/me', cookies: ['token' => $this->data($login)['token']])->getStatusCode());
    }

    public function testRememberMeCreatesThirtyDaySession(): void
    {
        $this->registerConfirmed();
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
    public function testSixthFailedLoginReturns429AndDoesNotCreateSession(): void
    {
        $this->registerConfirmed();
        $bad = ['email' => $this->credentials()['email'], 'password' => 'incorrect-fixture'];
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            self::assertSame(401, $this->request('POST', '/api/auth/login', $bad)->getStatusCode());
        }
        $blocked = $this->request('POST', '/api/auth/login', $this->credentials());
        self::assertSame(429, $blocked->getStatusCode());
        self::assertGreaterThan(0, (int)$blocked->getHeaderLine('Retry-After'));
        self::assertLessThanOrEqual(900, (int)$blocked->getHeaderLine('Retry-After'));
        self::assertSame('no-store', $blocked->getHeaderLine('Cache-Control'));
        self::assertSame('', $blocked->getHeaderLine('Set-Cookie'));
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
        self::assertStringNotContainsString($bad['email'], (string)$blocked->getBody());
    }

    public function testIpLimitCannotBeBypassedUsingDifferentEmailsOrForwardedHeaders(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            self::assertSame(401, $this->request('POST', '/api/auth/login', [
                'email' => "missing-$attempt@example.invalid", 'password' => 'incorrect-fixture',
            ], forwarded: "192.0.2.$attempt")->getStatusCode());
        }
        self::assertSame(429, $this->request('POST', '/api/auth/login', [
            'email' => 'another@example.invalid', 'password' => 'incorrect-fixture',
        ], forwarded: '198.51.100.1')->getStatusCode());
    }

    public function testEmailLimitPersistsAcrossDifferentIps(): void
    {
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertSame(401, $this->request('POST', '/api/auth/login', [
                'email' => 'missing@example.invalid', 'password' => 'incorrect-fixture',
            ], ip: "192.0.2.$attempt")->getStatusCode());
        }
        self::assertSame(429, $this->request('POST', '/api/auth/login', [
            'email' => 'missing@example.invalid', 'password' => 'incorrect-fixture',
        ], ip: '198.51.100.1')->getStatusCode());
    }

    public function testRegistrationRequiresTenCharactersIncludingUnicode(): void
    {
        foreach (['123456789', str_repeat('ą', 9)] as $password) {
            self::assertSame(422, $this->request('POST', '/api/auth/register', [
                'email' => 'short@example.invalid', 'password' => $password,
            ])->getStatusCode());
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertSame(201, $this->request('POST', '/api/auth/register', [
            'email' => 'long@example.invalid', 'password' => str_repeat('ą', 10),
        ])->getStatusCode());
    }

    public function testSuccessfulLoginDoesNotConsumeFailureBudget(): void
    {
        $this->registerConfirmed();
        for ($i = 0; $i < 7; ++$i) {
            self::assertSame(200, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn());
    }

    public function testUnconfirmedAccountGets403WithoutSessionAndWrongPasswordStays401(): void
    {
        $this->request('POST', '/api/auth/register', $this->credentials());
        $wrong = $this->request('POST', '/api/auth/login', [
            'email' => $this->credentials()['email'], 'password' => 'incorrect-fixture',
        ]);
        self::assertSame(401, $wrong->getStatusCode());
        self::assertArrayNotHasKey('code', $this->data($wrong));
        $denied = $this->request('POST', '/api/auth/login', $this->credentials());
        self::assertSame(403, $denied->getStatusCode());
        self::assertSame('email_confirmation_required', $this->data($denied)['code']);
        self::assertSame('no-store', $denied->getHeaderLine('Cache-Control'));
        self::assertSame('', $denied->getHeaderLine('Set-Cookie'));
        self::assertArrayNotHasKey('token', $this->data($denied));
        self::assertStringNotContainsString($this->credentials()['email'], (string)$denied->getBody());
        self::assertStringNotContainsString($this->verificationToken, (string)$denied->getBody());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
    }

    public function testConfirmationLinkIsSingleUseAndInvalidLinkDoesNotUnlockLogin(): void
    {
        $this->request('POST', '/api/auth/register', $this->credentials());
        self::assertSame(422, $this->request('GET', '/api/auth/verify-email?token=invalid-fixture')->getStatusCode());
        self::assertSame(403, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
        $this->confirmEmail();
        self::assertSame(422, $this->request('GET', '/api/auth/verify-email?token='.$this->verificationToken)->getStatusCode());
        self::assertSame(200, $this->request('POST', '/api/auth/login', $this->credentials())->getStatusCode());
    }

    public function testLegacySessionCannotBypassEmailConfirmationAndCanBeLoggedOut(): void
    {
        $this->request('POST', '/api/auth/register', $this->credentials());
        $repository = new UserRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $user = $repository->findByEmail($this->credentials()['email']);
        $session = 'synthetic-legacy-session';
        $repository->createSession((int)$user['id'], $session, date('Y-m-d H:i:s', time() + 3600));
        foreach ([['token' => $session], []] as $cookies) {
            $denied = $this->request('GET', '/api/auth/me', cookies: $cookies, bearer: $cookies === [] ? $session : '');
            self::assertSame(403, $denied->getStatusCode());
            self::assertSame('email_confirmation_required', $this->data($denied)['code']);
        }
        self::assertSame(204, $this->request('POST', '/api/auth/logout', cookies: ['token' => $session])->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/auth/me', cookies: ['token' => $session])->getStatusCode());
    }

}
