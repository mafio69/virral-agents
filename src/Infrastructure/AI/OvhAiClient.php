<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use GuzzleHttp\ClientInterface;

final readonly class OvhAiClient implements AiClientInterface
{
    private OpenAiCompatClient $client;

    public function __construct(
        private string $url,
        string $token,
        private string $model,
        int $timeout = 120,
        ?ClientInterface $httpClient = null,
    ) {
        $this->client = new OpenAiCompatClient($this->getBaseUrl(), $token, $this->getDefaultModel(), $timeout, $httpClient);
    }

    public function getDefaultModel(): string
    {
        return OvhModelDefaults::resolve($this->model);
    }

    public function resolveModel(?string $model): string
    {
        return OvhModelDefaults::resolve($model, $this->getDefaultModel());
    }

    public function generate(array $messages, ?string $model = null): string
    {
        return $this->client->generate($messages, $this->resolveModel($model));
    }

    /** @return list<array<string, mixed>> */
    public function listModels(): array
    {
        return $this->client->listModels();
    }

    public function getBaseUrl(): string
    {
        if (parse_url($this->url, PHP_URL_HOST) === 'qwen3-coder-30b-a3b-instruct.endpoints.kepler.ai.cloud.ovh.net') {
            return 'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/';
        }
        return rtrim($this->url, '/').'/';
    }
}
