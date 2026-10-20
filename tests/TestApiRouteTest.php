<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class TestApiRouteTest extends TestCase
{
    public static function nonDevelopmentEnvironments(): array
    {
        return [['prod'], ['production'], ['staging'], ['test'], ['DEV'], [''], [null]];
    }

    #[DataProvider('nonDevelopmentEnvironments')]
    public function testPanelIsNotRegisteredOutsideExplicitDevelopment(?string $environment): void
    {
        $response = $this->request('/test-api', $environment);
        self::assertSame(404, $response->getStatusCode());
        self::assertStringNotContainsString('Panel API', (string)$response->getBody());
    }

    #[DataProvider('nonDevelopmentEnvironments')]
    public function testDashboardHidesPanelLinksOutsideDevelopment(?string $environment): void
    {
        $body = (string)$this->request('/', $environment)->getBody();
        self::assertStringNotContainsString('href="/test-api', $body);
        self::assertStringContainsString('href="/chat"', $body);
        self::assertStringContainsString('id="dashboard-view"', $body);
    }

    public function testDashboardShowsPanelLinksInDevelopment(): void
    {
        $body = (string)$this->request('/', 'dev')->getBody();
        self::assertStringContainsString('href="/test-api#auth"', $body);
        self::assertStringContainsString('href="/test-api#tasks"', $body);
        self::assertStringContainsString('href="/chat"', $body);
    }

    public function testDevelopmentServesPanelWithoutPublishingItsTemplate(): void
    {
        $response = $this->request('/test-api', 'dev');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Panel API', (string)$response->getBody());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertFileDoesNotExist(__DIR__.'/../public/ui/index.html');
        self::assertFileExists(__DIR__.'/../resources/views/test-api.html');
        self::assertSame(404, $this->request('/ui/index.html', 'dev')->getStatusCode());
        self::assertSame(404, $this->request('/ui/index.html', 'prod')->getStatusCode());
        self::assertSame(404, $this->request('/resources/views/test-api.html', 'prod')->getStatusCode());
    }

    private function request(string $path, ?string $environment): \Psr\Http\Message\ResponseInterface
    {
        $settings = ['displayErrorDetails' => false];
        if ($environment !== null) {
            $settings['environment'] = $environment;
        }
        $container = new Container([
            'settings' => $settings,
            LoggerInterface::class => new NullLogger(),
        ]);
        $app = AppFactory::create(container: $container);
        (require __DIR__.'/../src/routes.php')($app);
        (require __DIR__.'/../src/middleware.php')($app);
        return $app->handle((new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://fixture.example.invalid'.$path
        ));
    }
}
