<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';
$_ENV['APP_ENV'] = 'dev';

use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\GeminiProvider;
use App\Infrastructure\AI\ProviderRegistry;
use App\Infrastructure\AI\ProviderTransport;
use App\Infrastructure\DataProtection\DataCipherInterface;
use App\Infrastructure\DataProtection\SodiumDataCipher;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Search\WebPageFetcherInterface;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

// Fixed dummy key protects exclusively synthetic E2E records.
$cipher = new SodiumDataCipher(str_repeat('ab', 32));
$pdo = (new ConnectionFactory(__DIR__.'/runtime/app.sqlite'))->create();
$builder = new ContainerBuilder();
(require __DIR__.'/../../src/dependencies.php')($builder);
$legacy = new class () implements ChatModelRegistryInterface {
    public function listModels(): array
    {
        return [];
    }
    public function resolve(string $selector): \App\Dto\AI\AiModel
    {
        throw new \App\Exception\AiConnectionException('model_unavailable');
    }
    public function clientFor(\App\Dto\AI\AiModel $model): \App\Infrastructure\AI\AiClientInterface
    {
        throw new \App\Exception\AiConnectionException('model_unavailable');
    }
};
$builder->addDefinitions([
    'settings' => [
        'environment' => 'dev', 'displayErrorDetails' => false,
        'app_url' => 'http://127.0.0.1:18181',
        'ovh_ai' => ['url' => 'http://127.0.0.1:18182/unused/', 'token' => 'e2e-dummy', 'model' => 'Qwen3.6-27B'],
        'mail' => ['dsn' => getenv('E2E_SMTP_DSN') ?: 'smtp://mail-trap.dom:1025'],
        'search' => ['mcp_url' => 'http://127.0.0.1:18182/mcp'],
    ],
    PDO::class => $pdo,
    DataCipherInterface::class => $cipher,
    LoggerInterface::class => new NullLogger(),
    ChatModelRegistryInterface::class => new ProviderRegistry($legacy, [
        'google' => new GeminiProvider(new ProviderTransport(
            'http://127.0.0.1:18182/v1beta/',
            'e2e-dummy',
            authHeader: 'x-goog-api-key',
            authPrefix: '',
        )),
    ]),
    WebPageFetcherInterface::class => new class () implements WebPageFetcherInterface {
        public function fetch(string $url): array
        {
            $response = (new \GuzzleHttp\Client())->get('http://127.0.0.1:18182/page', [
                'query' => ['url' => $url], 'allow_redirects' => false, 'timeout' => 5,
            ]);
            return json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        }
    },
]);
$container = $builder->build();
return $container;
