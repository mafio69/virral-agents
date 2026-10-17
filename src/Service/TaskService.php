<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Task\CreateTaskInput;
use App\Dto\Task\TaskResult;
use App\Dto\Task\UpdateTaskInput;
use App\Exception\NotFoundException;
use App\Repository\TaskRepository;
use Psr\Log\LoggerInterface;

final readonly class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createTask(CreateTaskInput $input): TaskResult
    {
        $id = $this->taskRepository->create(
            $input->userId,
            $input->title,
            $input->description,
            $input->status,
        );

        $this->logger->info('Task created', ['task_id' => $id, 'user_id' => $input->userId]);

        return new TaskResult(
            id: $id,
            userId: $input->userId,
            title: $input->title,
            description: $input->description,
            status: $input->status,
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s'),
        );
    }

    public function getUserTasks(int $userId): array
    {
        $rows = $this->taskRepository->findByUserId($userId);

        return array_map(
            fn (array $row) => new TaskResult(
                id: (int)$row['id'],
                userId: (int)$row['user_id'],
                title: $row['title'],
                description: $row['description'],
                status: $row['status'],
                createdAt: $row['created_at'],
                updatedAt: $row['updated_at'],
            ),
            $rows
        );
    }

    public function getTaskById(int $taskId, int $userId): TaskResult
    {
        $task = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new NotFoundException('Task not found');
        }

        if ((int)$task['user_id'] !== $userId) {
            throw new NotFoundException('Task not found');
        }

        return new TaskResult(
            id: (int)$task['id'],
            userId: (int)$task['user_id'],
            title: $task['title'],
            description: $task['description'],
            status: $task['status'],
            createdAt: $task['created_at'],
            updatedAt: $task['updated_at'],
        );
    }

    public function updateTask(UpdateTaskInput $input): TaskResult
    {
        $task = $this->taskRepository->findById($input->taskId);

        if (!$task) {
            throw new NotFoundException('Task not found');
        }

        if ((int)$task['user_id'] !== $input->userId) {
            throw new NotFoundException('Task not found');
        }

        $this->taskRepository->update(
            $input->taskId,
            $input->title,
            $input->description,
            $input->status,
        );

        $this->logger->info('Task updated', ['task_id' => $input->taskId, 'user_id' => $input->userId]);

        return new TaskResult(
            id: $input->taskId,
            userId: $input->userId,
            title: $input->title,
            description: $input->description,
            status: $input->status,
            createdAt: $task['created_at'],
            updatedAt: date('Y-m-d H:i:s'),
        );
    }

    public function deleteTask(int $taskId, int $userId): void
    {
        $task = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new NotFoundException('Task not found');
        }

        if ((int)$task['user_id'] !== $userId) {
            throw new NotFoundException('Task not found');
        }

        $this->taskRepository->delete($taskId);

        $this->logger->info('Task deleted', ['task_id' => $taskId, 'user_id' => $userId]);
    }
}
