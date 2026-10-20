<?php

declare(strict_types=1);

use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Mail\MailService;
use App\Repository\ChatRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\ChatService;
use App\Service\TaskService;
use DI\ContainerBuilder;
use Mariusz\Logger\DualLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        \App\Service\SessionAuthenticatorInterface::class => \DI\get(AuthService::class),
        \App\Controller\AuthController::class => \DI\autowire()->constructorParameter(
            'environment',
            \DI\factory(fn (ContainerInterface $c) => $c->get('settings')['environment'] ?? 'prod'),
        ),
        \App\Service\ModelCatalogInterface::class => \DI\autowire(\App\Service\ModelCatalogService::class),
        \App\Infrastructure\DataProtection\DataCipherInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');
            $value = $settings['user_data']['key'];
            if ($value !== '' && hash_equals(strtolower($settings['ai_registry']['key']), strtolower($value))) {
                throw new \App\Exception\DataProtectionException(\App\Exception\DataProtectionException::KEY_REUSED);
            }
            return new \App\Infrastructure\DataProtection\SodiumDataCipher($value);
        },
        DualLogger::class => function (ContainerInterface $c) {
            $config = $c->get('settings')['logger'];

            return DualLogger::create(
                logDir: $config['log_dir'],
                minLevel: $config['min_level'] ?? 'warning',
                dateFormat: $config['date_format'] ?? 'Y-m-d H:i:s',
                timezone: $config['timezone'] ?? 'Europe/Warsaw',
            );
        },
        LoggerInterface::class => fn(ContainerInterface $c) => $c->get(DualLogger::class),
        PDO::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['db'];

            return (new ConnectionFactory($settings['path']))->create();
        },
        MigrationRunner::class => function (ContainerInterface $c) {
            return new MigrationRunner($c->get(PDO::class));
        },
        OvhAiClient::class => function (ContainerInterface $c) {
            $config = $c->get('settings')['ovh_ai'];

            return new OvhAiClient(
                url: $config['url'],
                token: $config['token'],
                model: $config['model'],
                timeout: $config['timeout'] ?? 300,
            );
        },
        \App\Infrastructure\AI\ChatModelRegistryInterface::class => function (ContainerInterface $c) {
            $key = $c->get('settings')['zai']['key'] ?? '';
            $legacy = new \App\Infrastructure\AI\ZaiChatModelRegistry(
                $c->get(\App\Repository\AiModelRepository::class),
                static fn() => $c->get(\App\Infrastructure\AI\AiClientFactoryInterface::class),
                environmentClient: $key !== '' ? new \App\Infrastructure\AI\OpenAiCompatClient(
                    \App\Infrastructure\AI\ZaiEndpointPolicy::BASE_URI, $key, 'glm-5.3',
                ) : null,
            );
            return new \App\Infrastructure\AI\ProviderRegistry($legacy, [
                'google' => new \App\Infrastructure\AI\GeminiProvider(new \App\Infrastructure\AI\ProviderTransport(
                    'https://generativelanguage.googleapis.com/v1beta/', $c->get('settings')['google']['key'] ?? '',
                    authHeader: 'x-goog-api-key', authPrefix: '',
                )),
                'anthropic' => new \App\Infrastructure\AI\AnthropicProvider(new \App\Infrastructure\AI\ProviderTransport(
                    'https://api.anthropic.com/v1/', $c->get('settings')['anthropic']['key'] ?? '',
                    authHeader: 'x-api-key', authPrefix: '', headers: ['anthropic-version' => '2023-06-01'],
                )),
                'openai' => new \App\Infrastructure\AI\OpenAiProvider(new \App\Infrastructure\AI\ProviderTransport(
                    'https://api.openai.com/v1/', $c->get('settings')['openai']['key'] ?? '',
                )),
                'openrouter' => new \App\Infrastructure\AI\OpenRouterProvider(new \App\Infrastructure\AI\ProviderTransport(
                    \App\Infrastructure\AI\OpenRouterProvider::BASE_URI, $c->get('settings')['openrouter']['key'] ?? '',
                ),\App\Infrastructure\AI\OpenRouterProvider::allowedModels($c->get('settings')['openrouter']['models'] ?? '')),
            ]);
        },
        \App\Infrastructure\AI\AiClientFactoryInterface::class => function (ContainerInterface $c) {
            $keyValue = $c->get('settings')['ai_registry']['key'];
            if (!preg_match('/^[a-fA-F0-9]{64}$/D', $keyValue)) {
                throw new \App\Exception\AiConnectionException('missing_configuration');
            }
            $key = new \App\Infrastructure\Secrets\SecretKey($keyValue);
            $service = new \App\Infrastructure\Secrets\SecretService(
                new \App\Infrastructure\Secrets\SecretEncryptor($key),
                new \App\Infrastructure\Secrets\SecretDecryptor($key),
            );
            return new \App\Infrastructure\AI\AiClientFactory(
                new \App\Repository\AiProviderRepository($c->get(PDO::class), $service),
                $c->get(\App\Repository\AiUrlRepository::class),
                $service,
            );
        },
        MailService::class => function (ContainerInterface $c) {
            $dsn = $c->get('settings')['mail']['dsn'];

            return new MailService($dsn);
        },
        \App\Repository\LoginAttemptRepositoryInterface::class => fn(ContainerInterface $c) => new \App\Repository\LoginAttemptRepository($c->get(PDO::class)),
        \App\Service\LoginAttemptLimiterInterface::class => fn(ContainerInterface $c) => new \App\Service\LoginAttemptLimiter(
            $c->get(\App\Repository\LoginAttemptRepositoryInterface::class),
            $c->get(\App\Infrastructure\DataProtection\DataCipherInterface::class),
        ),
        AuthService::class => function (ContainerInterface $c) {
            return new AuthService(
                userRepository: $c->get(UserRepository::class),
                mailService: $c->get(MailService::class),
                appUrl: $c->get('settings')['app_url'] ?? 'http://localhost:8181',
                logger: $c->get(LoggerInterface::class),
                loginLimiter: $c->get(\App\Service\LoginAttemptLimiterInterface::class),
            );
        },
        ChatService::class => function (ContainerInterface $c) {
            return new ChatService(
                chatRepository: $c->get(ChatRepository::class),
                ovhAiClient: $c->get(OvhAiClient::class),
                modelRegistry: $c->get(\App\Infrastructure\AI\ChatModelRegistryInterface::class),
                logger: $c->get(LoggerInterface::class),
                replyGenerator: $c->get(\App\Service\ChatReplyGeneratorInterface::class),
                imageValidator: $c->get(\App\Service\ChatImageValidator::class),
                modelCatalog: $c->get(\App\Service\ModelCatalogService::class),
                research: $c->get(\App\Service\ResearchServiceInterface::class),
                researchReply: $c->get(\App\Service\ResearchReplyGeneratorInterface::class),
            );
        },
        \App\Infrastructure\Search\SearchClientInterface::class => function (ContainerInterface $c) {
            $search = $c->get('settings')['search'];
            return new \App\Infrastructure\Search\McpSearchClient($search['mcp_url'], token: $search['mcp_token'] ?? '');
        },
        \App\Service\ChatReplyGeneratorInterface::class => function (ContainerInterface $c) {
            return new \App\Service\ChatReplyGenerator($c->get(\App\Infrastructure\Search\SearchClientInterface::class), $c->get(LoggerInterface::class));
        },
        \App\Service\SearchDiagnosticsInterface::class => function (ContainerInterface $c) {
            return new \App\Service\SearchDiagnostics($c->get(\App\Infrastructure\Search\SearchClientInterface::class), $c->get('settings')['search']['mcp_url']);
        },
        \App\Repository\ResearchRepositoryInterface::class => fn(ContainerInterface $c) => new \App\Repository\ResearchRepository($c->get(PDO::class), $c->get(\App\Infrastructure\DataProtection\DataCipherInterface::class)),
        \App\Infrastructure\Search\WebPageFetcherInterface::class => fn() => new \App\Infrastructure\Search\PublicWebPageFetcher(),
        \App\Service\ResearchServiceInterface::class => fn(ContainerInterface $c) => new \App\Service\ResearchService($c->get(\App\Repository\ResearchRepositoryInterface::class), $c->get(ChatRepository::class), $c->get(\App\Infrastructure\Search\SearchClientInterface::class), $c->get(\App\Infrastructure\Search\WebPageFetcherInterface::class)),
        \App\Service\ResearchReplyGeneratorInterface::class => fn(ContainerInterface $c) => new \App\Service\ResearchReplyGenerator($c->get(\App\Service\ResearchServiceInterface::class), $c->get(\App\Service\ChatReplyGeneratorInterface::class)),
        \App\Controller\ChatController::class => function (ContainerInterface $c) {
            return new \App\Controller\ChatController(
                $c->get(\App\Mapper\Chat\CreateChatInputMapper::class),
                $c->get(\App\Mapper\Chat\AddChatMessageInputMapper::class),
                $c->get(ChatService::class), $c->get(\App\Service\ModelCatalogService::class),
                $c->get(\App\Service\SearchDiagnosticsInterface::class),
                $c->get(\App\Service\ResearchServiceInterface::class),
            );
        },
        TaskService::class => function (ContainerInterface $c) {
            return new TaskService(
                taskRepository: $c->get(TaskRepository::class),
                logger: $c->get(LoggerInterface::class),
            );
        },
    ]);
};
