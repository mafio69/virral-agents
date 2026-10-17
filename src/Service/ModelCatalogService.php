<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\ZaiEndpointPolicy;
use Psr\Log\LoggerInterface;

final readonly class ModelCatalogService implements ModelCatalogInterface
{
    public function __construct(
        private OvhAiClient $ovhAiClient,
        private ChatModelRegistryInterface $modelRegistry,
        private LoggerInterface $logger,
    ) {
    }

    public function getConnections(): array
    {
        return [
            ['id' => 'ovh', 'name' => 'OVH'],
            ['id' => 'zai', 'name' => 'Z.ai / GLM'],
            ...($this->modelRegistry instanceof \App\Infrastructure\AI\MultiProviderRegistryInterface ? $this->modelRegistry->connections() : []),
        ];
    }

    public function getAvailableModels(?string $connection = null): array
    {
        if ($connection !== null && !in_array($connection, array_column($this->getConnections(), 'id'), true)) {
            throw new ValidationException('Nieznane połączenie AI.');
        }
        if ($connection !== null && $connection !== 'ovh') {
            $registry = $this->modelRegistry;
            return $registry instanceof \App\Infrastructure\AI\MultiProviderRegistryInterface ? $registry->listFor($connection) : $registry->listModels();
        }
        // The legacy endpoint without a connection parameter remains the OVH catalogue.
        try {
            $catalogue = $this->ovhAiClient->listModels();

            // Filter and format models
            $models = [];
            foreach ($catalogue as $model) {
                // Keep chat models; image understanding is a separate capability.
                if (preg_match('/embedding|stable-diffusion|bge-|whisper|guard|tts|^ppl$/i', $model['id'])) {
                    continue;
                }

                $models[] = [
                    'id' => $model['id'],
                    'provider' => 'ovh',
                    'name' => $model['id'],
                    'description' => $this->getModelDescription($model['id']),
                    'category' => $this->getModelCategory($model['id']),
                    'supports_images' => $this->supportsImages($model['id']),
                    'is_default' => $model['id'] === \App\Infrastructure\AI\OvhModelDefaults::resolve($this->ovhAiClient->getDefaultModel()),
                    'context_length' => $model['context_length'] ?? 0,
                    'max_completion_tokens' => $model['max_completion_tokens'] ?? 0,
                ];
            }

            return $models;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch models from OVH AI API');
            throw $e;
        }
    }

    private function getModelDescription(string $modelId): string
    {
        $descriptions = [
            'Qwen3.8-27B' => 'Coding and reasoning with image understanding',
            'Qwen3-Coder-30B-A3B-Instruct' => 'Advanced coding model optimized for code generation and debugging',
            'Qwen3-32B' => 'General purpose large language model',
            'Qwen3.5-397B-A17B' => 'Large reasoning model for complex tasks',
            'Qwen3.6-27B' => 'Visual language model with multimodal capabilities',
            'Qwen3.5-9B' => 'Efficient model for general tasks',
            'Mistral-7B-Instruct-v0.3' => 'Balanced model for general tasks',
            'Mistral-Small-3.2-24B-Instruct-2506' => 'Latest Mistral model with improved performance',
            'Mistral-Nemo-Instruct-2407' => 'Mistral Nemo model for general tasks',
            'Meta-Llama-3_3-70B-Instruct' => 'Meta Llama 3.3 70B model for general tasks',
            'Llama-3.1-8B-Instruct' => 'Efficient Llama model for general tasks',
            'gpt-oss-20b' => 'OpenAI-style 20B model for general tasks',
            'gpt-oss-120b' => 'Large OpenAI-style 120B model for complex tasks',
            'Qwen2.5-VL-72B-Instruct' => 'Visual language model with image understanding',
            'whisper-large-v3' => 'Speech to text model',
            'whisper-large-v3-turbo' => 'Fast speech to text model',
        ];

        return $descriptions[$modelId] ?? 'AI model for various tasks';
    }

    public function supportsImages(string $modelId): bool
    {
        if ((new \App\Dto\Chat\ModelSelector($modelId))->isRegistry()) {
            if ($this->modelRegistry instanceof \App\Infrastructure\AI\MultiProviderRegistryInterface) {
                return $this->modelRegistry->supportsImages($modelId);
            }
            return ZaiEndpointPolicy::supportsImages($this->modelRegistry->resolve($modelId)->idModel);
        }
        return in_array($modelId, [
            'Qwen3.8-27B', 'Qwen3.6-27B', 'Qwen3.5-397B-A17B',
            'Qwen3.5-9B', 'Qwen2.5-VL-72B-Instruct',
        ], true);
    }

    private function getModelCategory(string $modelId): string
    {
        if ($modelId === 'Qwen3.8-27B' || str_contains($modelId, 'Coder')) {
            return 'code';
        }
        if ($this->supportsImages($modelId)) {
            return 'visual';
        }
        if (str_contains($modelId, 'whisper')) {
            return 'audio';
        }
        if (str_contains($modelId, 'Guard')) {
            return 'guard';
        }
        return 'general';
    }
}
