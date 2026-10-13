<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiModel;
use App\Exception\AiConnectionException;
use App\Repository\AiModelRepository;
use Closure;
use Throwable;

final readonly class ZaiChatModelRegistry implements ChatModelRegistryInterface
{
    /** @param Closure(): AiClientFactoryInterface $factory */
    public function __construct(private AiModelRepository $models, private Closure $factory, private ?ModelCatalogueInterface $catalogue = null)
    {
    }

    public function listModels(): array
    {
        $remote = ($this->catalogue ?? new ZaiModelCatalogue())->listModels();
        $registered = $this->availableModels();
        if ($registered === []) {
            return array_map(static fn(array $entry): array => [
                'id' => 'zai:'.$entry['id'],
                'name' => 'Z.ai — '.$entry['id'],
                'description' => 'Katalog Z.ai z sieci; połączenie wymaga skonfigurowania dostawcy.',
                'category' => $entry['supports_images'] ? 'visual' : 'general',
                'supports_images' => $entry['supports_images'] && ZaiEndpointPolicy::supportsImages($entry['id']),
                'provider' => 'zai', 'is_default' => false, 'configured' => false,
                'catalogue_source' => 'provider_documentation', 'account_access_verified' => false,
            ], $remote);
        }
        $connections = [];
        foreach ($registered as $model) $connections[$model->providerId.':'.$model->endpointUrlId] = $model;
        $result = [];
        foreach ($connections as $template) {
            foreach ($remote as $entry) {
                $model = null;
                foreach ($registered as $existing) {
                    if ($existing->providerId === $template->providerId && $existing->endpointUrlId === $template->endpointUrlId
                        && $existing->idModel === $entry['id']) { $model = $existing; break; }
                }
                // Only public metadata is added. Provider records and encrypted credentials are untouched.
                $model ??= $this->models->findOrCreateDiscovered($template, $entry['id']);
                $result[] = [
                    'id' => $this->selector($model),
                    'name' => 'Z.ai — '.$entry['id'],
                    'description' => 'Katalog Z.ai pobrany z sieci; dostęp konta sprawdzany przy wysyłaniu.',
                    'category' => $entry['supports_images'] ? 'visual' : 'general',
                    'supports_images' => $entry['supports_images'] && ZaiEndpointPolicy::supportsImages($entry['id']),
                    'is_default' => false,
                    'provider' => 'zai',
                    'configured' => true,
                    'catalogue_source' => 'provider_documentation',
                    'account_access_verified' => false,
                ];
            }
        }
        return $result;
    }

    public function resolve(string $selector): AiModel
    {
        foreach ($this->availableModels() as $model) {
            if (hash_equals($this->selector($model), $selector)) return $model;
        }
        // Never move an existing conversation to another provider or replacement model.
        throw new AiConnectionException('model_unavailable');
    }

    public function clientFor(AiModel $model): AiClientInterface
    {
        try {
            return ($this->factory)()->fromModel($model);
        } catch (AiConnectionException $error) {
            throw $error;
        } catch (Throwable) {
            throw new AiConnectionException('missing_configuration');
        }
    }

    /** @return list<AiModel> */
    private function availableModels(): array
    {
        return array_values(array_filter(
            $this->models->findAvailableAtEndpoint(ZaiEndpointPolicy::BASE_URI),
            static fn(AiModel $model): bool => (bool)preg_match('/^glm-[a-z0-9.-]+$/D', $model->idModel),
        ));
    }

    private function selector(AiModel $model): string
    {
        $binding = implode(':', [$model->providerId, $model->endpointUrlId, $model->idModel]);
        return 'db:'.$model->id.':'.substr(hash('sha256', $binding), 0, 24);
    }
}
