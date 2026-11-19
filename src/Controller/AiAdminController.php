<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Http\JsonResponder;
use App\Mapper\AI\AiModelInputMapper;
use App\Mapper\AI\AiProviderInputMapper;
use App\Mapper\AI\UpdateProviderInputMapper;
use App\Service\AiProviderService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AiAdminController
{
    public function __construct(
        private readonly AiProviderService $service,
        private readonly AiProviderInputMapper $providerMapper,
        private readonly UpdateProviderInputMapper $updateMapper,
        private readonly AiModelInputMapper $modelMapper,
    ) {
    }

    public function listProviders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::respond($response, $this->service->listProviders());
    }

    public function createProvider(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->providerMapper->map((array)$request->getParsedBody());

        return JsonResponder::respond($response, $this->service->createProvider($input), 201);
    }

    public function updateProvider(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->updateMapper->map((array)$request->getParsedBody());
        $id = (int)$request->getAttribute('id');

        return JsonResponder::respond($response, $this->service->updateProvider($id, $data));
    }

    public function deleteProvider(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->service->deleteProvider((int)$request->getAttribute('id'));

        return JsonResponder::respond($response, ['message' => 'Dostawca usunięty.']);
    }

    public function createModel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->modelMapper->map((array)$request->getParsedBody());

        return JsonResponder::respond($response, $this->service->createModel($input), 201);
    }

    public function deleteModel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->service->deleteModel((int)$request->getAttribute('id'));

        return JsonResponder::respond($response, ['message' => 'Model usunięty.']);
    }
}
