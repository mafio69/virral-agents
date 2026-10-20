<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\LoginRateLimitException;
use App\Repository\LoginAttemptRepository;
use App\Service\LoginAttemptLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

final class LoginAttemptLimiterTest extends TestCase
{
    private PDO $pdo;
    private int $now = 10000;
    private LoginAttemptLimiter $limiter;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->limiter = $this->newLimiter();
    }

    private function newLimiter(): LoginAttemptLimiter
    {
        return new LoginAttemptLimiter(
            new LoginAttemptRepository($this->pdo),
            ProtectedDataFixture::cipher($this->pdo),
            fn () => $this->now
        );
    }

    public function testLimitSurvivesNewServiceInstanceAndExpiresAtWindowBoundary(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        $this->now += 899;
        try {
            $this->newLimiter()->reserve('fixture@example.invalid', '192.0.2.1');
            self::fail('Sixth attempt must be blocked');
        } catch (LoginRateLimitException $error) {
            self::assertSame(1, $error->retryAfter);
        }
        ++$this->now;
        self::assertGreaterThan(0, $this->newLimiter()->reserve('fixture@example.invalid', '192.0.2.1'));
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn());
    }

    public function testSuccessfulAttemptOnlyReleasesItsOwnReservation(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        for ($i = 0; $i < 8; ++$i) {
            $id = $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
            $this->limiter->release($id);
        }
        self::assertSame(4, (int)$this->pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn());
        $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        $this->expectException(LoginRateLimitException::class);
        $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
    }

    public function testIdentifiersAreKeyedAndScopesDoNotCollide(): void
    {
        $email = 'fixture@example.invalid';
        $ip = '192.0.2.1';
        $this->limiter->reserve($email, $ip);
        $row = $this->pdo->query('SELECT * FROM login_attempts')->fetch(PDO::FETCH_ASSOC);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $row['email_key']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $row['ip_key']);
        self::assertNotSame(hash('sha256', $email), $row['email_key']);
        self::assertNotSame(hash('sha256', $ip), $row['ip_key']);
        self::assertStringNotContainsString($email, json_encode($row));
        self::assertStringNotContainsString($ip, json_encode($row));
        self::assertNotSame(ProtectedDataFixture::cipher($this->pdo)->emailIndex($email), $row['email_key']);
    }

    public function testEmailCaseCannotBypassLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->limiter->reserve('Fixture@example.invalid', '2001:db8::1');
        }
        $this->expectException(LoginRateLimitException::class);
        $this->newLimiter()->reserve(' fixture@EXAMPLE.invalid ', '192.0.2.2');
    }

    public function testIndependentUsersAndIpsRemainAvailable(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', '192.0.2.1');
        }
        self::assertGreaterThan(0, $this->limiter->reserve('other@example.invalid', '192.0.2.2'));
    }

    public function testMissingOrInvalidIpUsesOneConservativeBucket(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->limiter->reserve("fixture-$i@example.invalid", '');
        }
        $this->expectException(LoginRateLimitException::class);
        $this->newLimiter()->reserve('other@example.invalid', 'invalid-address');
    }
    public function testEquivalentIpv6AddressesShareIpLimitAcrossDifferentEmails(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->limiter->reserve("fixture-$i@example.invalid", '2001:db8::1');
        }
        $this->expectException(LoginRateLimitException::class);
        $this->newLimiter()->reserve('other@example.invalid', '2001:0db8:0:0:0:0:0:1');
    }

    public function testRetryAfterWaitsForBothIndependentLimits(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->limiter->reserve('fixture@example.invalid', "192.0.2.$i");
        }
        $this->now += 60;
        for ($i = 0; $i < 5; ++$i) {
            $this->limiter->reserve("other-$i@example.invalid", '198.51.100.1');
        }
        $this->now += 30;
        try {
            $this->limiter->reserve('fixture@example.invalid', '198.51.100.1');
            self::fail('Both limits must be respected');
        } catch (LoginRateLimitException $error) {
            self::assertSame(870, $error->retryAfter);
        }
    }

    public function testProductionContainerWiresTheRequiredLimiter(): void
    {
        $builder = new \DI\ContainerBuilder();
        (require __DIR__.'/../src/dependencies.php')($builder);
        $builder->addDefinitions([
            PDO::class => $this->pdo,
            \App\Infrastructure\DataProtection\DataCipherInterface::class => ProtectedDataFixture::cipher($this->pdo),
            \App\Infrastructure\Mail\MailService::class => $this->createMock(\App\Infrastructure\Mail\MailService::class),
            \Psr\Log\LoggerInterface::class => new \Psr\Log\NullLogger(),
            'settings' => ['app_url' => 'https://fixture.example.invalid'],
        ]);
        $service = $builder->build()->get(\App\Service\AuthService::class);
        for ($i = 0; $i < 5; ++$i) {
            try {
                $service->login(new \App\Dto\Auth\LoginInput('fixture@example.invalid', 'incorrect-fixture', false), '192.0.2.1');
                self::fail('Unknown account must be rejected');
            } catch (\App\Exception\UnauthorizedException) {
                // Failed attempts must remain in the shared persistent limiter.
            }
        }
        $this->expectException(LoginRateLimitException::class);
        $service->login(new \App\Dto\Auth\LoginInput('fixture@example.invalid', 'incorrect-fixture', false), '192.0.2.1');
    }

    public function testClientsBehindTrustedProxyAreLimitedSeparately(): void
    {
        $builder = new \DI\ContainerBuilder();
        (require __DIR__.'/../src/dependencies.php')($builder);
        $builder->addDefinitions([
            PDO::class => $this->pdo,
            \App\Infrastructure\DataProtection\DataCipherInterface::class => ProtectedDataFixture::cipher($this->pdo),
            \App\Infrastructure\Mail\MailService::class => $this->createMock(\App\Infrastructure\Mail\MailService::class),
            \Psr\Log\LoggerInterface::class => new \Psr\Log\NullLogger(),
            'settings' => ['app_url' => 'https://fixture.example.invalid', 'trusted_proxies' => ['172.18.0.1']],
        ]);
        $controller = $builder->build()->get(\App\Controller\AuthController::class);
        $attempt = function (string $client, string $email) use ($controller): void {
            $request = (new \Slim\Psr7\Factory\ServerRequestFactory())
                ->createServerRequest('POST', 'http://fixture.example/api/auth/login', ['REMOTE_ADDR' => '172.18.0.1'])
                ->withHeader('X-Forwarded-For', $client)
                ->withParsedBody(['email' => $email, 'password' => 'incorrect-fixture']);
            $controller->login($request, new \Slim\Psr7\Response());
        };
        for ($i = 0; $i < 5; ++$i) {
            try {
                $attempt('203.0.113.7', 'fixture-'.$i.'@example.invalid');
                self::fail('Unknown account must be rejected');
            } catch (\App\Exception\UnauthorizedException) {
                // Five failures from one client behind the shared proxy.
            }
        }
        try {
            $attempt('203.0.113.8', 'other@example.invalid');
            self::fail('Unknown account must be rejected');
        } catch (\App\Exception\UnauthorizedException) {
            // Another client behind the same proxy is not locked out.
        }
        $this->expectException(LoginRateLimitException::class);
        $attempt('203.0.113.7', 'fixture-next@example.invalid');
    }

}
