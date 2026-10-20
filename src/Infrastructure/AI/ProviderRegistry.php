<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiModel;
use App\Exception\AiConnectionException;

final readonly class ProviderRegistry implements MultiProviderRegistryInterface
{
    /** @param array<string, ChatProviderInterface> $providers */
    public function __construct(private ChatModelRegistryInterface $legacy, private array $providers)
    {
    }

    public function connections(): array
    {
        // Connections without a usable key are hidden; existing chats still resolve them by selector.
        $configured = array_filter($this->providers, static fn (ChatProviderInterface $provider): bool => $provider->isConfigured());
        return array_map(static fn (ChatProviderInterface $provider): array => ['id' => $provider->id(), 'name' => $provider->name()], array_values($configured));
    }

    public function hasConnection(string $connection): bool
    {
        return isset($this->providers[$connection]);
    }

    public function listModels(): array
    {
        return $this->legacy->listModels();
    }

    public function listFor(string $connection): array
    {
        if ($connection === 'zai') {
            return $this->legacy->listModels();
        }
        $provider = $this->providers[$connection] ?? throw new AiConnectionException('model_unavailable');
        return array_map(static fn (array $model): array => [
            'id' => $connection.':'.$model['id'], 'name' => $provider->name().' — '.($model['name'] ?? $model['id']),
            'provider' => $connection, 'configured' => true, 'account_access_verified' => false,
            'supports_images' => $model['supports_images'] ?? false, 'is_default' => false,
            'generates_images' => $model['generates_images'] ?? false,
            'description' => 'Katalog dostawcy; dostęp do modelu jest sprawdzany przy wysyłaniu.',
        ], $provider->listModels());
    }

    public function resolve(string $selector): AiModel
    {
        [$connection, $id] = array_pad(explode(':', $selector, 2), 2, '');
        if (!isset($this->providers[$connection])) {
            return $this->legacy->resolve($selector);
        }
        foreach ($this->providers[$connection]->listModels() as $model) {
            if ($id === $model['id']) {
                return new AiModel(0, 0, $id, $model['name'] ?? $id, 0, null, null, 'llm', null, null, null, $connection);
            }
        }
        throw new AiConnectionException('model_unavailable');
    }

    public function clientFor(AiModel $model): AiClientInterface
    {
        if ($model->connection === null) {
            return $this->legacy->clientFor($model);
        }
        return $this->providers[$model->connection] ?? throw new AiConnectionException('missing_configuration');
    }

    public function supportsImages(string $selector): bool
    {
        $model = $this->resolve($selector);
        if ($model->connection === null) {
            return ZaiEndpointPolicy::supportsImages($model->idModel);
        }
        foreach ($this->providers[$model->connection]->listModels() as $entry) {
            if ($entry['id'] === $model->idModel) {
                return ($entry['supports_images'] ?? false) === true;
            }
        }
        return false;
    }
}
