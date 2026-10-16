<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Infrastructure\Http\JsonResponder;
use App\Mapper\Chat\AddChatMessageInputMapper;
use App\Mapper\Chat\CreateChatInputMapper;
use App\Service\AuthService;
use App\Service\ChatService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ChatController
{
    public function __construct(
        private readonly CreateChatInputMapper $createChatInputMapper,
        private readonly AddChatMessageInputMapper $addChatMessageInputMapper,
        private readonly ChatService $chatService,
        private readonly AuthService $authService,
        private readonly ?\App\Service\SearchDiagnosticsInterface $searchDiagnostics = null,
        private readonly ?\App\Service\ResearchServiceInterface $research = null,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->getUserId($request);
        $chats = $this->chatService->getUserChats($userId);

        return JsonResponder::respond($response, $chats);
    }

    private function getUserId(ServerRequestInterface $request): int
    {
        $token = $this->resolveToken($request);

        return $this->authService->validateToken($token);
    }

    private function resolveToken(ServerRequestInterface $request): string
    {
        $header = $request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $cookies = $request->getCookieParams();
        if (!empty($cookies['token'])) {
            return $cookies['token'];
        }

        throw new UnauthorizedException('Missing or invalid Authorization header');
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->getUserId($request);
        $data = (array)$request->getParsedBody();
        $input = $this->createChatInputMapper->map($data, $userId);
        $result = $this->chatService->createChat($input);

        return JsonResponder::respond($response, $result, 201);
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        $userId = $this->getUserId($request);
        $result = $this->chatService->getChatWithMessages($id, $userId);

        return JsonResponder::respond($response, $result);
    }

    public function addMessage(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        $userId = $this->getUserId($request);
        $data = (array)$request->getParsedBody();
        $input = $this->addChatMessageInputMapper->map($data, $id, $userId);
        $result = $this->chatService->addMessage($input);

        return JsonResponder::respond($response, $result, 201);
    }
    
    public function listConnections(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::respond($response, $this->chatService->getConnections());
    }

    public function searchStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->getUserId($request);
        $status = $this->searchDiagnostics?->status() ?? ['available' => false, 'reason' => 'missing_configuration', 'searched' => false];
        return JsonResponder::respond($response, $status);
    }

    public function researchState(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return $this->researchCall('state', $request, $response); }
    public function researchPropose(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return $this->researchCall('propose', $request, $response); }
    public function researchDecide(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return $this->researchCall('decide', $request, $response); }
    public function researchSelect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return $this->researchCall('select', $request, $response); }
    public function researchPrepare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return $this->researchCall('prepare', $request, $response); }

    private function researchCall(string $operation, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->getUserId($request);
        [$scope, $args] = (new \App\Mapper\Chat\ResearchInputMapper())->map($operation, $request->getQueryParams(), (array)$request->getParsedBody());
        $service = $this->research ?? throw new \App\Exception\ValidationException('Badanie źródeł jest niedostępne.');
        return JsonResponder::respond($response, $service->{$operation}($userId, $scope, ...$args));
    }

    public function listModels(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Pobranie modeli wybranego połączenia.
        try {
            $connection = $request->getQueryParams()['connection'] ?? null;
            if ($connection !== null && !is_string($connection)) {
                return JsonResponder::respond($response, ['error' => 'Nieznane połączenie AI.'], 400);
            }
            $models = $this->chatService->getAvailableModels($connection);
            return JsonResponder::respond($response, $models);
        } catch (\App\Exception\ValidationException) {
            return JsonResponder::respond($response, ['error' => 'Nieznane połączenie AI.'], 400);
        } catch (\App\Exception\AiConnectionException $e) {
            return JsonResponder::respond($response, [
                'error' => 'Lista modeli AI jest niedostępna.',
            ], 503)->withHeader('X-AI-Error', $e->reason);
        } catch (\Throwable) {
            return JsonResponder::respond($response, [
                'error' => 'Lista modeli AI jest chwilowo niedostępna.',
            ], 503);
        }
    }
}
