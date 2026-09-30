<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiModel;
use App\Exception\NotFoundException;
use App\Infrastructure\Secrets\SecretService;
use App\Repository\AiProviderRepository;
use App\Repository\AiUrlRepository;

/**
 * Fabryka klientów AI: z rekordu ai_models (i jego dostawcy) tworzy klienta.
 * Sekret odszyfrowywany w runtime, używany tylko do budowy klienta —
 * nigdy nie logowany i nigdy nie zwracany przez API.
 */
final readonly class AiClientFactory implements AiClientFactoryInterface
{
    public function __construct(
        private AiProviderRepository $providerRepository,
        private AiUrlRepository $urlRepository,
        private SecretService $secretService,
    ) {
    }

    public function fromModel(AiModel $model): AiClientInterface
    {
        $provider = $this->providerRepository->findById($model->providerId);
        if ($provider === null) {
            throw new NotFoundException('AI provider not found');
        }

        $endpoint = $this->urlRepository->findById($model->endpointUrlId);
        if ($endpoint === null) {
            throw new NotFoundException('AI endpoint URL not found');
        }

        return new OpenAiCompatClient(
            baseUri: $endpoint->url,
            apiKey: $this->secretService->revealSecret($provider->providersSecret),
            defaultModel: $model->idModel,
        );
    }
}
