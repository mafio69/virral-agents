<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AI\AiModel;
use App\Dto\AI\AiModelInput;
use App\Dto\AI\AiProvider;
use App\Dto\AI\AiProviderInput;
use App\Exception\NotFoundException;
use App\Repository\AiModelRepository;
use App\Repository\AiProviderRepository;
use App\Repository\AiUrlRepository;
use Psr\Log\LoggerInterface;

/**
 * Zarządzanie dostawcami AI (admin). Sekret dostawcy nigdy nie jest zwracany —
 * klient widzi wyłącznie has_secret; plaintext jest szyfrowany w AiProviderRepository.
 */
final readonly class AiProviderService
{
    public function __construct(
        private AiProviderRepository $providers,
        private AiModelRepository $models,
        private AiUrlRepository $urls,
        private LoggerInterface $logger,
    ) {
    }

    /** @return list<array{id:int,name:string,description:?string,has_secret:bool,models:list<array>}> */
    public function listProviders(): array
    {
        $result = [];
        foreach ($this->providers->findAll() as $provider) {
            $public = $this->toPublic($provider);
            $public['models'] = array_map(
                fn (AiModel $m) => $this->modelToPublic($m),
                $this->models->findByProviderId($provider->id)
            );
            $result[] = $public;
        }

        return $result;
    }

    /** @return array{id:int,name:string,description:?string,has_secret:bool} */
    public function createProvider(AiProviderInput $input): array
    {
        $url = $this->urls->findOrCreate($input->endpointUrl);
        $provider = $this->providers->create($input->name, $input->secret, $input->description, $url->id, null);

        if ($input->modelId !== null) {
            $this->models->create($provider->id, $input->modelId, $input->modelName ?? $input->modelId, $url->id);
        }

        $this->logger->info('AI provider created', ['provider_id' => $provider->id]);

        return $this->toPublic($provider);
    }

    /**
     * @param array{name:string, description:?string, secret:?string} $data secret = null keeps the current value
     *
     * @return array{id:int,name:string,description:?string,has_secret:bool}
     */
    public function updateProvider(int $id, array $data): array
    {
        $provider = $this->providers->findById($id);
        if ($provider === null) {
            throw new NotFoundException('Provider not found');
        }

        $updated = new AiProvider(
            id: $provider->id,
            name: $data['name'],
            description: $data['description'],
            providersSecret: $provider->providersSecret,
            urlId: $provider->urlId,
            docsUrlId: $provider->docsUrlId,
        );
        $this->providers->update($updated, $data['secret']);

        $this->logger->info('AI provider updated', ['provider_id' => $id]);

        return $this->toPublic($updated);
    }

    public function deleteProvider(int $id): void
    {
        if ($this->providers->findById($id) === null) {
            throw new NotFoundException('Provider not found');
        }
        foreach ($this->models->findByProviderId($id) as $model) {
            $this->models->softDelete($model->id);
        }
        $this->providers->softDelete($id);

        $this->logger->info('AI provider deleted', ['provider_id' => $id]);
    }

    /** @return array{id:int,provider_id:int,id_model:string,name_display:string} */
    public function createModel(AiModelInput $input): array
    {
        $url = $this->urls->findOrCreate($input->endpointUrl);
        $model = $this->models->create($input->providerId, $input->idModel, $input->nameDisplay, $url->id);

        $this->logger->info('AI model created', ['model_id' => $model->id, 'provider_id' => $input->providerId]);

        return $this->modelToPublic($model);
    }

    public function deleteModel(int $id): void
    {
        $this->models->softDelete($id);
        $this->logger->info('AI model deleted', ['model_id' => $id]);
    }

    /** @return array{id:int,name:string,description:?string,has_secret:bool} */
    private function toPublic(AiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'description' => $provider->description,
            'has_secret' => $provider->providersSecret !== '',
        ];
    }

    /** @return array{id:int,provider_id:int,id_model:string,name_display:string} */
    private function modelToPublic(AiModel $model): array
    {
        return [
            'id' => $model->id,
            'provider_id' => $model->providerId,
            'id_model' => $model->idModel,
            'name_display' => $model->nameDisplay,
        ];
    }
}
