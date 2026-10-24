<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Http\JsonResponder;
use App\Mapper\Chat\AddChatMessageInputMapper;
use App\Mapper\Chat\CreateChatInputMapper;
use App\Service\ChatService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ChatController
{
    public function __construct(
        private readonly CreateChatInputMapper $createChatInputMapper,
        private readonly AddChatMessageInputMapper $addChatMessageInputMapper,
        private readonly ChatService $chatService,
        private readonly \App\Service\ModelCatalogInterface $modelCatalog,
        private readonly ?\App\Service\SearchDiagnosticsInterface $searchDiagnostics = null,
        private readonly ?\App\Service\ResearchServiceInterface $research = null,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $chats = $this->chatService->getUserChats($userId);

        return JsonResponder::respond($response, $chats);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $data = (array)$request->getParsedBody();
        $input = $this->createChatInputMapper->map($data, $userId);
        $result = $this->chatService->createChat($input);

        return JsonResponder::respond($response, $result, 201);
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $result = $this->chatService->getChatWithMessages($id, $userId);

        return JsonResponder::respond($response, $result);
    }

    public function addMessage(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $data = (array)$request->getParsedBody();
        $input = $this->addChatMessageInputMapper->map($data, $id, $userId);
        $result = $this->chatService->addMessage($input);

        return JsonResponder::respond($response, $result, 201);
    }

    public function retryMessage(ServerRequestInterface $request, ResponseInterface $response, int $id, int $messageId): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $input = (new \App\Mapper\Chat\RetryMessageInputMapper())->map($id, $messageId, $userId);
        $result = $this->chatService->retryMessage($input);

        return JsonResponder::respond($response, $result, 201);
    }

    public function continueMessage(ServerRequestInterface $request, ResponseInterface $response, int $id, int $messageId): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $input = (new \App\Mapper\Chat\RetryMessageInputMapper())->map($id, $messageId, $userId);
        $result = $this->chatService->continueMessage($input);

        return JsonResponder::respond($response, $result, 201);
    }

    public function listConnections(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::respond($response, $this->modelCatalog->getConnections());
    }

    public function searchStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $status = $this->searchDiagnostics?->status() ?? ['available' => false, 'reason' => 'missing_configuration', 'searched' => false];
        return JsonResponder::respond($response, $status);
    }

    public function researchState(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->researchCall('state', $request, $response);
    }
    public function researchPropose(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->researchCall('propose', $request, $response);
    }
    public function researchDecide(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->researchCall('decide', $request, $response);
    }
    public function researchSelect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->researchCall('select', $request, $response);
    }
    public function researchPrepare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->researchCall('prepare', $request, $response);
    }

    private function researchCall(string $operation, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        [$scope, $args] = (new \App\Mapper\Chat\ResearchInputMapper())->map($operation, $request->getQueryParams(), (array)$request->getParsedBody());
        $service = $this->research ?? throw new \App\Exception\ServiceUnavailableException('Badanie źródeł jest niedostępne.');
        return JsonResponder::respond($response, $service->{$operation}($userId, $scope, ...$args));
    }

    public function listModels(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = (new \App\Mapper\Chat\ListModelsInputMapper())->map($request->getQueryParams());
        $models = $this->modelCatalog->getAvailableModels($input->connection);

        return JsonResponder::respond($response, $models);
    }
}
