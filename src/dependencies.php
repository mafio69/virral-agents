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
            $factory = new ConnectionFactory($settings['path']);

            return $factory->create();
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
                timeout: $config['timeout'] ?? 120,
            );
        },
        \App\Infrastructure\AI\ChatModelRegistryInterface::class => function (ContainerInterface $c) {
            $key = $c->get('settings')['zai']['key'] ?? '';
            return new \App\Infrastructure\AI\ZaiChatModelRegistry(
                $c->get(\App\Repository\AiModelRepository::class),
                static fn() => $c->get(\App\Infrastructure\AI\AiClientFactoryInterface::class),
                environmentClient: $key !== '' ? new \App\Infrastructure\AI\OpenAiCompatClient(
                    \App\Infrastructure\AI\ZaiEndpointPolicy::BASE_URI, $key, 'glm-5.3',
                ) : null,
            );
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
        AuthService::class => function (ContainerInterface $c) {
            return new AuthService(
                userRepository: $c->get(UserRepository::class),
                mailService: $c->get(MailService::class),
                appUrl: $c->get('settings')['app_url'] ?? 'http://localhost:8181',
                logger: $c->get(LoggerInterface::class),
            );
        },
        ChatService::class => function (ContainerInterface $c) {
            return new ChatService(
                chatRepository: $c->get(ChatRepository::class),
                ovhAiClient: $c->get(OvhAiClient::class),
                modelRegistry: $c->get(\App\Infrastructure\AI\ChatModelRegistryInterface::class),
                logger: $c->get(LoggerInterface::class),
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
