<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use OpenAI;
use RuntimeException;
use Throwable;

/**
 * Uniwersalny klient OpenAI-compatible (Z.AI, OpenRouter, OVH, ...).
 * Budowany z danych rekordu ai_models + ai_providers (baza),
 * token odszyfrowany przez SecretService — nigdy nie logowany.
 */
final readonly class OpenAiCompatClient implements AiClientInterface
{
    public function __construct(
        private string $baseUri,
        private string $apiKey,
        private string $defaultModel,
        private int $timeout = 120,
    ) {
    }

    public function generate(array $messages, ?string $model = null): string
    {
        try {
            $client = OpenAI::factory()
                ->withBaseUri($this->baseUri)
                ->withApiKey($this->apiKey)
                ->withHttpClient(new \GuzzleHttp\Client(['timeout' => $this->timeout]))
                ->make();

            $response = $client->chat()->create([
                'model' => $model ?? $this->defaultModel,
                'messages' => $messages,
            ]);

            return (string)($response->choices[0]->message->content ?? '');
        } catch (Throwable $e) {
            throw new RuntimeException('AI request failed: '.$e->getMessage(), 0, $e);
        }
    }
}
