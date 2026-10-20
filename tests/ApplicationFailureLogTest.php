<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ApplicationFailureLogTest extends TestCase
{
    public function testDatabaseFailureLogsSafeDiagnosticCodesWithoutMessage(): void
    {
        $error = new \PDOException('SQLSTATE[HY000]: General error: 5 database is locked fixture-secret-value');
        $error->errorInfo = ['HY000', 5, 'database is locked'];
        $logger = $this->logger();

        $response = $this->failWith($error, $logger);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Application operation failed', $logger->records[0]['message']);
        $context = $logger->records[0]['context'];
        self::assertSame(\PDOException::class, $context['type']);
        self::assertSame(5, $context['driver_code']);
        self::assertMatchesRegularExpression('/^ApplicationFailureLogTest\.php:\d+$/', $context['origin']);
        self::assertStringNotContainsString('fixture-secret-value', json_encode($logger->records, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('fixture-secret-value', (string)$response->getBody());
    }

    public function testOtherFailuresLogTypeAndOriginOnly(): void
    {
        $logger = $this->logger();

        $this->failWith(new \RuntimeException('fixture-secret-value'), $logger);

        self::assertSame(['type', 'origin'], array_keys($logger->records[0]['context']));
        self::assertStringNotContainsString('fixture-secret-value', json_encode($logger->records, JSON_THROW_ON_ERROR));
    }

    private function failWith(\Throwable $error, LoggerInterface $logger): ResponseInterface
    {
        $app = AppFactory::create(container: new \DI\Container([
            LoggerInterface::class => $logger, 'settings' => ['displayErrorDetails' => false],
        ]));
        $app->get('/fixture-failure', function () use ($error): never {
            throw $error;
        });
        (require __DIR__.'/../src/middleware.php')($app);

        return $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://fixture.example/fixture-failure'));
    }

    private function logger(): AbstractLogger
    {
        return new class () extends AbstractLogger {
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['message' => (string)$message, 'context' => $context];
            }
        };
    }
}
