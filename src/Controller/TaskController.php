<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Http\JsonResponder;
use App\Mapper\Task\CreateTaskInputMapper;
use App\Mapper\Task\UpdateTaskInputMapper;
use App\Service\TaskService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TaskController
{
    public function __construct(
        private readonly CreateTaskInputMapper $createTaskInputMapper,
        private readonly UpdateTaskInputMapper $updateTaskInputMapper,
        private readonly TaskService $taskService,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $tasks = $this->taskService->getUserTasks($userId);

        return JsonResponder::respond($response, $tasks);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $data = (array)$request->getParsedBody();
        $input = $this->createTaskInputMapper->map($data, $userId);
        $result = $this->taskService->createTask($input);

        return JsonResponder::respond($response, $result, 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $data = (array)$request->getParsedBody();
        $input = $this->updateTaskInputMapper->map($data, $id, $userId);
        $result = $this->taskService->updateTask($input);

        return JsonResponder::respond($response, $result);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');
        $this->taskService->deleteTask($id, $userId);

        return JsonResponder::respond($response, ['message' => 'Task deleted successfully']);
    }
}
